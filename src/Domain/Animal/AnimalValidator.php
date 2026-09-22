<?php

declare(strict_types=1);

namespace App\Domain\Animal;

use App\Support\Clock;
use App\Support\IsoDate;

// valida os dados de entrada do cadastro de animais antes de persistir
final class AnimalValidator
{
    private const int TAG_MAX = 30;
    private const int NOTES_MAX = 500;
    private const float WEIGHT_MAX = 2000.0;

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

        $tag = trim((string) ($input['tag'] ?? ''));
        if ($tag === '') {
            $errors['tag'] = 'Informe o brinco do animal.';
        } elseif (mb_strlen($tag) > self::TAG_MAX) {
            $errors['tag'] = 'O brinco deve ter no máximo ' . self::TAG_MAX . ' caracteres.';
        } elseif (preg_match('/^[A-Za-z0-9-]+$/', $tag) !== 1) {
            $errors['tag'] = 'O brinco aceita apenas letras, números e hífen.';
        }

        if (Species::tryFrom((string) ($input['species'] ?? '')) === null) {
            $errors['species'] = 'Selecione uma espécie válida.';
        }

        if (Sex::tryFrom((string) ($input['sex'] ?? '')) === null) {
            $errors['sex'] = 'Selecione o sexo do animal.';
        }

        $weightRaw = (string) ($input['weight_kg'] ?? '');
        if (!is_numeric($weightRaw)) {
            $errors['weight_kg'] = 'Informe o peso em quilos.';
        } else {
            $weight = (float) $weightRaw;
            if ($weight <= 0.0) {
                $errors['weight_kg'] = 'O peso deve ser maior que zero.';
            } elseif ($weight > self::WEIGHT_MAX) {
                $errors['weight_kg'] = 'Peso acima do limite plausível de ' . self::WEIGHT_MAX . ' kg.';
            }
        }

        $birthDate = trim((string) ($input['birth_date'] ?? ''));
        if ($birthDate !== '') {
            $parsed = IsoDate::parse($birthDate);
            if ($parsed === null) {
                $errors['birth_date'] = 'Use uma data válida no formato AAAA-MM-DD.';
            } elseif ($parsed->format('Y-m-d') > $this->clock->now()->format('Y-m-d')) {
                $errors['birth_date'] = 'A data de nascimento não pode estar no futuro.';
            }
        }

        if (mb_strlen((string) ($input['notes'] ?? '')) > self::NOTES_MAX) {
            $errors['notes'] = 'As observações devem ter no máximo ' . self::NOTES_MAX . ' caracteres.';
        }

        return $errors;
    }
}
