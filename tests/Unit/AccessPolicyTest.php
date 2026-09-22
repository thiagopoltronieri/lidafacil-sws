<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Security\AccessPolicy;
use App\Application\Security\AuthenticatedUser;
use App\Domain\User\Role;
use App\Domain\User\User;
use PHPUnit\Framework\TestCase;

final class AccessPolicyTest extends TestCase
{
    private AccessPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new AccessPolicy();
    }

    private function actor(int $id, Role $role): AuthenticatedUser
    {
        return new AuthenticatedUser($id, $role);
    }

    private function target(int $id, Role $role): User
    {
        return new User($id, 'Alvo', 'alvo' . $id . '@lidafacil.local', $role, 'hash', '2026-01-01 00:00:00');
    }

    public function testOnlyAdminCanCreateUsers(): void
    {
        self::assertTrue($this->policy->canCreateUser(Role::ADMIN));
        self::assertFalse($this->policy->canCreateUser(Role::OPERATOR));
        self::assertFalse($this->policy->canCreateUser(Role::CLIENT));
    }

    public function testOnlyAdminCanDeleteUsers(): void
    {
        self::assertTrue($this->policy->canDeleteUser(Role::ADMIN));
        self::assertFalse($this->policy->canDeleteUser(Role::OPERATOR));
        self::assertFalse($this->policy->canDeleteUser(Role::CLIENT));
    }

    public function testAdminAndOperatorCanListUsersButClientCannot(): void
    {
        self::assertTrue($this->policy->canListUsers(Role::ADMIN));
        self::assertTrue($this->policy->canListUsers(Role::OPERATOR));
        self::assertFalse($this->policy->canListUsers(Role::CLIENT));
    }

    public function testOnlyAdminCanAssignRole(): void
    {
        self::assertTrue($this->policy->canAssignRole(Role::ADMIN));
        self::assertFalse($this->policy->canAssignRole(Role::OPERATOR));
        self::assertFalse($this->policy->canAssignRole(Role::CLIENT));
    }

    public function testClientCanViewOnlyItsOwnRecord(): void
    {
        $client = $this->actor(5, Role::CLIENT);

        self::assertTrue($this->policy->canViewUser($client, 5));
        self::assertFalse($this->policy->canViewUser($client, 6));
    }

    public function testAdminAndOperatorCanViewAnyUser(): void
    {
        self::assertTrue($this->policy->canViewUser($this->actor(1, Role::ADMIN), 999));
        self::assertTrue($this->policy->canViewUser($this->actor(2, Role::OPERATOR), 999));
    }

    public function testClientCanUpdateOnlyItsOwnRecord(): void
    {
        $client = $this->actor(5, Role::CLIENT);

        self::assertTrue($this->policy->canUpdateUser($client, $this->target(5, Role::CLIENT)));
        self::assertFalse($this->policy->canUpdateUser($client, $this->target(6, Role::CLIENT)));
    }

    public function testOperatorUpdatesClientsAndSelfButNotAdminNorOtherOperator(): void
    {
        $operator = $this->actor(2, Role::OPERATOR);

        self::assertTrue($this->policy->canUpdateUser($operator, $this->target(9, Role::CLIENT)));
        self::assertTrue($this->policy->canUpdateUser($operator, $this->target(2, Role::OPERATOR)));
        // trava do escalonamento vertical: operador não edita admin nem outro operador
        self::assertFalse($this->policy->canUpdateUser($operator, $this->target(1, Role::ADMIN)));
        self::assertFalse($this->policy->canUpdateUser($operator, $this->target(3, Role::OPERATOR)));
    }

    public function testAdminCanUpdateAnyUser(): void
    {
        $admin = $this->actor(1, Role::ADMIN);

        self::assertTrue($this->policy->canUpdateUser($admin, $this->target(1, Role::ADMIN)));
        self::assertTrue($this->policy->canUpdateUser($admin, $this->target(2, Role::OPERATOR)));
        self::assertTrue($this->policy->canUpdateUser($admin, $this->target(9, Role::CLIENT)));
    }

    public function testOnlyAdminOrOwnerCanSetPassword(): void
    {
        self::assertTrue($this->policy->canSetPassword($this->actor(1, Role::ADMIN), 9));
        self::assertTrue($this->policy->canSetPassword($this->actor(5, Role::CLIENT), 5));
        self::assertTrue($this->policy->canSetPassword($this->actor(2, Role::OPERATOR), 2));
        // operador não redefine a senha de terceiros; trava de takeover por reset de credencial
        self::assertFalse($this->policy->canSetPassword($this->actor(2, Role::OPERATOR), 9));
        self::assertFalse($this->policy->canSetPassword($this->actor(5, Role::CLIENT), 6));
    }

    public function testAdminAndOperatorCanManageAnimalsButClientCannot(): void
    {
        self::assertTrue($this->policy->canManageAnimals(Role::ADMIN));
        self::assertTrue($this->policy->canManageAnimals(Role::OPERATOR));
        self::assertFalse($this->policy->canManageAnimals(Role::CLIENT));
    }
}
