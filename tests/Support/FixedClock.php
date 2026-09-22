<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Support\Clock;
use DateTimeImmutable;

// relógio de teste: sempre devolve um instante fixo, deixando as regras que dependem de data determinísticas de nascimento, aplicação de vacina
final class FixedClock implements Clock
{
    public function __construct(private DateTimeImmutable $fixed)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->fixed;
    }
}
