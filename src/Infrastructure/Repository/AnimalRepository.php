<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Animal\Animal;
use App\Domain\Animal\Sex;
use App\Domain\Animal\Species;
use App\Support\Clock;
use DateTimeImmutable;
use PDO;

// persistência de animais; consultas sempre parametrizadas
final class AnimalRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<Animal>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM animals ORDER BY created_at DESC, id DESC');

        $animals = [];
        if ($stmt !== false) {
            foreach ($stmt as $row) {
                /** @var array<string,mixed> $row */
                $animals[] = $this->map($row);
            }
        }

        return $animals;
    }

    public function find(int $id): ?Animal
    {
        $stmt = $this->pdo->prepare('SELECT * FROM animals WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function existsByTag(string $tag, ?int $exceptId = null): bool
    {
        if ($exceptId === null) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM animals WHERE tag = :tag');
            $stmt->execute([':tag' => $tag]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM animals WHERE tag = :tag AND id <> :id');
            $stmt->execute([':tag' => $tag, ':id' => $exceptId]);
        }

        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(Animal $animal): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'INSERT INTO animals (tag, species, sex, birth_date, weight_kg, notes, created_at, updated_at)
             VALUES (:tag, :species, :sex, :birth_date, :weight_kg, :notes, :created_at, :updated_at)',
        );
        $stmt->execute([
            ':tag' => $animal->tag,
            ':species' => $animal->species->value,
            ':sex' => $animal->sex->value,
            ':birth_date' => $animal->birthDate?->format('Y-m-d'),
            ':weight_kg' => $animal->weightKg,
            ':notes' => $animal->notes,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, Animal $animal): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE animals
             SET tag = :tag, species = :species, sex = :sex, birth_date = :birth_date,
                 weight_kg = :weight_kg, notes = :notes, updated_at = :updated_at
             WHERE id = :id',
        );
        $stmt->execute([
            ':tag' => $animal->tag,
            ':species' => $animal->species->value,
            ':sex' => $animal->sex->value,
            ':birth_date' => $animal->birthDate?->format('Y-m-d'),
            ':weight_kg' => $animal->weightKg,
            ':notes' => $animal->notes,
            ':updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ':id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM animals WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM animals');
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string,mixed> $row
     */
    private function map(array $row): Animal
    {
        $birth = $row['birth_date'] ?? null;
        $notes = $row['notes'] ?? null;

        return new Animal(
            (int) $row['id'],
            (string) $row['tag'],
            Species::from((string) $row['species']),
            Sex::from((string) $row['sex']),
            is_string($birth) && $birth !== '' ? new DateTimeImmutable($birth) : null,
            (float) $row['weight_kg'],
            $notes !== null ? (string) $notes : null,
            new DateTimeImmutable((string) $row['created_at']),
            new DateTimeImmutable((string) $row['updated_at']),
        );
    }
}
