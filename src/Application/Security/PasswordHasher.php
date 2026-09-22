<?php

declare(strict_types=1);

namespace App\Application\Security;

// encapsula o hashing de senha usando o bcrypt, que aplica salt automático; e nunca guardar senha em texto puro
final class PasswordHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
