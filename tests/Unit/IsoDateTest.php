<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\IsoDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IsoDateTest extends TestCase
{
    public function testParsesValidIsoDateAtMidnight(): void
    {
        $date = IsoDate::parse('2026-02-15');

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        // o marcador ! zera o horário, então a data sempre nasce à meia-noite
        self::assertSame('2026-02-15 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    public function testRejectsSlashSeparators(): void
    {
        self::assertNull(IsoDate::parse('2026/02/15'));
    }

    public function testRejectsGarbageString(): void
    {
        self::assertNull(IsoDate::parse('abc'));
    }

    public function testRejectsEmptyString(): void
    {
        self::assertNull(IsoDate::parse(''));
    }

    public function testRejectsMonthOverflow(): void
    {
        // o createFromFormat aceita mês 13 e rola para o ano seguinte; o aviso denuncia
        self::assertNull(IsoDate::parse('2026-13-01'));
    }

    public function testRejectsDayOverflow(): void
    {
        self::assertNull(IsoDate::parse('2026-02-30'));
    }

    public function testAcceptsLeapDayInLeapYear(): void
    {
        $date = IsoDate::parse('2024-02-29');

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2024-02-29', $date->format('Y-m-d'));
    }

    public function testRejectsLeapDayInNonLeapYear(): void
    {
        self::assertNull(IsoDate::parse('2025-02-29'));
    }
}
