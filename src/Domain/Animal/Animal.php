<?php

declare(strict_types=1);

namespace App\Domain\Animal;

use DateTimeImmutable;

// entidade de animal do rebanho; imutável, montada a partir do banco ou do formulario
final readonly class Animal
{
    public function __construct(
        public ?int $id,
        public string $tag,
        public Species $species,
        public Sex $sex,
        public ?DateTimeImmutable $birthDate,
        public float $weightKg,
        public ?string $notes,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {
    }
}
