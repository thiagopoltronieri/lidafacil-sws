<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Vaccination\Vaccination;
use App\Support\Clock;
use DateTimeImmutable;
use PDO;

// persistência do histórico de vacinação
final class VaccinationRepository
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return list<Vaccination>
     */
    public function forAnimal(int $animalId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM vaccinations WHERE animal_id = :id ORDER BY applied_at DESC, id DESC',
        );
        $stmt->execute([':id' => $animalId]);

        $vaccinations = [];
        foreach ($stmt as $row) {
            /** @var array<string,mixed> $row */
            $vaccinations[] = $this->map($row);
        }

        return $vaccinations;
    }

    public function find(int $id): ?Vaccination
    {
        $stmt = $this->pdo->prepare('SELECT * FROM vaccinations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $this->map($row) : null;
    }

    public function create(Vaccination $vaccination): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO vaccinations (animal_id, vaccine_name, dose, applied_at, notes, created_at)
             VALUES (:animal_id, :name, :dose, :applied_at, :notes, :created_at)',
        );
        $stmt->execute([
            ':animal_id' => $vaccination->animalId,
            ':name' => $vaccination->vaccineName,
            ':dose' => $vaccination->dose,
            ':applied_at' => $vaccination->appliedAt->format('Y-m-d'),
            ':notes' => $vaccination->notes,
            ':created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM vaccinations WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    /**
     * @param array<string,mixed> $row
     */
    private function map(array $row): Vaccination
    {
        $dose = $row['dose'] ?? null;
        $notes = $row['notes'] ?? null;

        return new Vaccination(
            (int) $row['id'],
            (int) $row['animal_id'],
            (string) $row['vaccine_name'],
            $dose !== null ? (string) $dose : null,
            new DateTimeImmutable((string) $row['applied_at']),
            $notes !== null ? (string) $notes : null,
            new DateTimeImmutable((string) $row['created_at']),
        );
    }
}
