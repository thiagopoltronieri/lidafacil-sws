<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Support\Clock;
use PDO;

// persiste os refresh tokens; guarda só o hash, nunca o token cru; queries parametrizadas
final class RefreshTokenRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {
    }

    public function create(int $userId, string $tokenHash, string $expiresAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at, created_at)'
            . ' VALUES (:user_id, :token_hash, :expires_at, :created_at)',
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt,
            ':created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array{id:int,user_id:int,expires_at:string,revoked_at:?string}|null
     */
    public function findByHash(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, expires_at, revoked_at FROM refresh_tokens WHERE token_hash = :token_hash',
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'expires_at' => (string) $row['expires_at'],
            'revoked_at' => $row['revoked_at'] === null ? null : (string) $row['revoked_at'],
        ];
    }

    // revoga marcando a data; só afeta tokens ainda ativos. devolve quantas linhas mudaram, o que permite rotação atômica
    public function revokeByHash(string $tokenHash): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked_at = :revoked_at WHERE token_hash = :token_hash AND revoked_at IS NULL',
        );
        $stmt->execute([
            ':revoked_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ':token_hash' => $tokenHash,
        ]);

        return $stmt->rowCount();
    }

    // revoga todos os refresh tokens ativos de um usuário, por exemplo ao excluí-lo
    public function revokeAllForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE refresh_tokens SET revoked_at = :revoked_at WHERE user_id = :user_id AND revoked_at IS NULL',
        );
        $stmt->execute([
            ':revoked_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ':user_id' => $userId,
        ]);
    }
}
