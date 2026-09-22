<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\User\Role;
use App\Domain\User\User;
use App\Support\Clock;
use PDO;

// acesso aos usuários; todas as queries são parametrizadas (defesa contra injection)
final class UserRepository
{
    private const COLUMNS = 'id, name, email, role, password_hash, created_at';

    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);

        return $this->hydrate($stmt->fetch());
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $this->hydrate($stmt->fetch());
    }

    /**
     * @return list<User>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT ' . self::COLUMNS . ' FROM users ORDER BY id ASC');
        $rows = $stmt === false ? [] : $stmt->fetchAll();

        $users = [];
        foreach ($rows as $row) {
            $user = $this->hydrate($row);
            if ($user !== null) {
                $users[] = $user;
            }
        }

        return $users;
    }

    public function create(string $name, string $email, string $passwordHash, Role $role): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role, created_at)'
            . ' VALUES (:name, :email, :hash, :role, :created_at)',
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':hash' => $passwordHash,
            ':role' => $role->value,
            ':created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $email, Role $role): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET name = :name, email = :email, role = :role WHERE id = :id',
        );
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':role' => $role->value,
            ':id' => $id,
        ]);
    }

    public function changePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([':hash' => $passwordHash, ':id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    // usada na validação de unicidade; o exceptId ignora o próprio registro numa atualização
    public function existsByEmail(string $email, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
            $stmt->execute([':email' => $email, ':id' => $exceptId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');

        return $stmt === false ? 0 : (int) $stmt->fetchColumn();
    }

    /**
     * @param mixed $row
     */
    private function hydrate($row): ?User
    {
        if (!is_array($row)) {
            return null;
        }

        return new User(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            Role::from((string) $row['role']),
            (string) $row['password_hash'],
            (string) $row['created_at'],
        );
    }
}
