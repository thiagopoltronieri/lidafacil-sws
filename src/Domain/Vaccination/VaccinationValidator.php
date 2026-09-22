<?php

declare(strict_types=1);

namespace App\Domain\Vaccination;

use App\Support\Clock;
use App\Support\IsoDate;

// valida o registro de vacinação antes de gravar no histórico do animal
final class VaccinationValidator
{
    private const int NAME_MAX = 80;
    private const int DOSE_MAX = 40;
    private const int NOTES_MAX = 500;

    public function __construct(private readonly Clock $clock)
    {
    }

    /**
     * @param array<string,mixed> $input
     *
     * @return array<string,string> erros por campo; array vazio significa entrada válida
     */
    public function validate(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['vaccine_name'] ?? ''));
        if ($name === '') {
            $errors['vaccine_name'] = 'Informe o nome da vacina.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['vaccine_name'] = 'O nome da vacina deve ter no máximo ' . self::NAME_MAX . ' caracteres.';
        }

        if (mb_strlen((string) ($input['dose'] ?? '')) > self::DOSE_MAX) {
            $errors['dose'] = 'A dose deve ter no máximo ' . self::DOSE_MAX . ' caracteres.';
        }

        $appliedAt = trim((string) ($input['applied_at'] ?? ''));
        if ($appliedAt === '') {
            $errors['applied_at'] = 'Informe a data de aplicação.';
        } else {
            $parsed = IsoDate::parse($appliedAt);
            if ($parsed === null) {
                $errors['applied_at'] = 'Use uma data válida no formato AAAA-MM-DD.';
            } elseif ($parsed->format('Y-m-d') > $this->clock->now()->format('Y-m-d')) {
                $errors['applied_at'] = 'A data de aplicação não pode estar no futuro.';
            }
        }

        if (mb_strlen((string) ($input['notes'] ?? '')) > self::NOTES_MAX) {
            $errors['notes'] = 'As observações devem ter no máximo ' . self::NOTES_MAX . ' caracteres.';
        }

        return $errors;
    }
}
