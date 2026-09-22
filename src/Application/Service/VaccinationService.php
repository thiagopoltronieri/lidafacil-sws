<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Vaccination\Vaccination;
use App\Domain\Vaccination\VaccinationValidator;
use App\Infrastructure\Repository\VaccinationRepository;
use App\Support\IsoDate;
use RuntimeException;

// registra e lista o histórico de vacinação de um animal
final class VaccinationService
{
    public function __construct(
        private readonly VaccinationRepository $vaccinations,
        private readonly VaccinationValidator $validator,
    ) {
    }

    /**
     * @return list<Vaccination>
     */
    public function forAnimal(int $animalId): array
    {
        return $this->vaccinations->forAnimal($animalId);
    }

    public function find(int $id): ?Vaccination
    {
        return $this->vaccinations->find($id);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function create(int $animalId, array $input): Vaccination
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $appliedAt = IsoDate::parse(trim((string) ($input['applied_at'] ?? '')));
        if ($appliedAt === null) {
            // salvaguarda; o validador já garante o formato antes de chegar aqui
            throw new ValidationException(['applied_at' => 'Use uma data válida no formato AAAA-MM-DD.']);
        }

        $dose = trim((string) ($input['dose'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        $id = $this->vaccinations->create(new Vaccination(
            null,
            $animalId,
            trim((string) ($input['vaccine_name'] ?? '')),
            $dose !== '' ? $dose : null,
            $appliedAt,
            $notes !== '' ? $notes : null,
        ));

        $vaccination = $this->vaccinations->find($id);
        if ($vaccination === null) {
            throw new RuntimeException('vacinação não encontrada após o registro');
        }

        return $vaccination;
    }

    public function delete(int $id): void
    {
        $this->vaccinations->delete($id);
    }
}
