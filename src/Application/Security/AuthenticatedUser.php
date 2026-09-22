<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\Role;

// identidade extraída de um token válido; carrega só o necessário para autorização, id e perfil, sem pii
final readonly class AuthenticatedUser
{
    public function __construct(
        public int $id,
        public Role $role,
    ) {
    }

    public function is(Role $role): bool
    {
        return $this->role === $role;
    }
}
