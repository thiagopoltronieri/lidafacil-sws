<?php

declare(strict_types=1);

namespace App\Domain\Animal;

// sexo do animal
enum Sex: string
{
    case MACHO = 'macho';
    case FEMEA = 'femea';

    public function label(): string
    {
        return match ($this) {
            self::MACHO => 'Macho',
            self::FEMEA => 'Fêmea',
        };
    }
}
