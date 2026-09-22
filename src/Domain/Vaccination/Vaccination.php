<?php

declare(strict_types=1);

namespace App\Domain\Vaccination;

use DateTimeImmutable;

// registro de uma vacina aplicada em um animal
final readonly class Vaccination
{
    public function __construct(
        public ?int $id,
        public int $animalId,
        public string $vaccineName,
        public ?string $dose,
        public DateTimeImmutable $appliedAt,
        public ?string $notes,
        public ?DateTimeImmutable $createdAt = null,
    ) {
    }
}
