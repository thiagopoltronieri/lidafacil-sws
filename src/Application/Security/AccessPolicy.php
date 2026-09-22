<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\Role;
use App\Domain\User\User;

// centraliza as decisões de autorização do rbac; deny-by-default e checagem a nível de objeto
final class AccessPolicy
{
    public function canListUsers(Role $role): bool
    {
        return $role === Role::ADMIN || $role === Role::OPERATOR;
    }

    public function canCreateUser(Role $role): bool
    {
        return $role === Role::ADMIN;
    }

    public function canDeleteUser(Role $role): bool
    {
        return $role === Role::ADMIN;
    }

    // só o administrador define ou altera o perfil de um usuário; impede escalonamento de privilégio
    public function canAssignRole(Role $role): bool
    {
        return $role === Role::ADMIN;
    }

    // admin e operador veem qualquer usuário; o cliente só o próprio registro visando anti-IDOR
    public function canViewUser(AuthenticatedUser $actor, int $targetId): bool
    {
        return match ($actor->role) {
            Role::ADMIN, Role::OPERATOR => true,
            Role::CLIENT => $actor->id === $targetId,
        };
    }

    // o alvo entra na decisão: o operador só edita clientes ou o próprio cadastro, nunca admin ou outro operador, isso evita takeover vertical
    public function canUpdateUser(AuthenticatedUser $actor, User $target): bool
    {
        return match ($actor->role) {
            Role::ADMIN => true,
            Role::OPERATOR => $target->role === Role::CLIENT || $target->id === $actor->id,
            Role::CLIENT => $target->id === $actor->id,
        };
    }

    // redefinir senha de terceiros é privilégio do admin; qualquer um pode trocar a própria
    public function canSetPassword(AuthenticatedUser $actor, int $targetId): bool
    {
        return $actor->role === Role::ADMIN || $actor->id === $targetId;
    }

    // gestão do rebanho com animais e vacinações fica com admin e operador;
    public function canManageAnimals(Role $role): bool
    {
        return $role === Role::ADMIN || $role === Role::OPERATOR;
    }
}
