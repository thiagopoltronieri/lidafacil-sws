<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Security\PasswordHasher;
use App\Domain\User\User;
use App\Infrastructure\Repository\UserRepository;

// autenticação por e-mail e senha
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
    ) {
    }

    public function attempt(string $email, string $password): ?User
    {
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            // gasta tempo parecido ao de uma verificação real para não vazar a existência do e-mail por timing
            $this->hasher->hash($password);

            return null;
        }

        if (!$this->hasher->verify($password, $user->passwordHash)) {
            return null;
        }

        // aproveita o único momento com a senha em claro para regravar o hash se o custo do bcrypt mudou
        if ($this->hasher->needsRehash($user->passwordHash)) {
            $this->users->changePassword($user->id, $this->hasher->hash($password));
        }

        return $user;
    }
}
