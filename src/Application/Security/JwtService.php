<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\Role;
use App\Domain\User\User;
use App\Support\Clock;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

// emite e valida tokens de acesso jwt assinados com hs256; o segredo vive só no ambiente
final class JwtService
{
    // algoritmo fixo: o decode nunca confia no header alg do token, o que evita confusão de algoritmo e alg=none
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $accessTtl,
        private readonly Clock $clock,
    ) {
    }

    public function accessTtl(): int
    {
        return $this->accessTtl;
    }

    public function issueAccessToken(User $user): string
    {
        if ($this->secret === '') {
            // falha alto em vez de emitir token assinado com chave vazia; misconfig do ambiente
            throw new InvalidTokenException('segredo do jwt não configurado');
        }

        $now = $this->clock->now()->getTimestamp();

        $payload = [
            'iss' => $this->issuer,
            'sub' => (string) $user->id,
            // sem nome nem e-mail no token: minimização de pii; o front usa /users/me para exibir o nome
            'role' => $user->role->value,
            'typ' => 'access',
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
        ];

        return JWT::encode($payload, $this->secret, self::ALGORITHM);
    }

    public function parse(string $token): AuthenticatedUser
    {
        if ($this->secret === '') {
            throw new InvalidTokenException('segredo do jwt não configurado');
        }

        if (trim($token) === '') {
            throw new InvalidTokenException('token ausente');
        }

        // fixa o instante do validador de expiração no relógio injetado, deixando o teste determinístico
        JWT::$timestamp = $this->clock->now()->getTimestamp();

        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (Throwable $error) {
            throw new InvalidTokenException('token inválido: ' . $error->getMessage(), 0, $error);
        } finally {
            JWT::$timestamp = null;
        }

        /** @var array<string,mixed> $claims */
        $claims = (array) $decoded;

        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw new InvalidTokenException('emissor do token inválido');
        }

        if (($claims['typ'] ?? null) !== 'access') {
            throw new InvalidTokenException('tipo de token inválido');
        }

        $role = Role::tryFrom((string) ($claims['role'] ?? ''));
        if ($role === null) {
            throw new InvalidTokenException('perfil de acesso inválido no token');
        }

        return new AuthenticatedUser((int) ($claims['sub'] ?? 0), $role);
    }
}
