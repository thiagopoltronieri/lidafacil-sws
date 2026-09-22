<?php

declare(strict_types=1);

namespace App\Domain\Animal;

// espécies suportadas no controle de rebanho
enum Species: string
{
    case BOVINO = 'bovino';
    case SUINO = 'suino';
    case OVINO = 'ovino';

    // rótulo acentuado para exibição na interface
    public function label(): string
    {
        return match ($this) {
            self::BOVINO => 'Bovino',
            self::SUINO => 'Suíno',
            self::OVINO => 'Ovino',
        };
    }
}
