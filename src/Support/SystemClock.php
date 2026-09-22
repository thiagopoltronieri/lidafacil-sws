<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

// relógio usado em produção
final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}
