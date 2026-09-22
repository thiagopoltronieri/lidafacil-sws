<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Animal\Sex;
use PHPUnit\Framework\TestCase;

final class SexTest extends TestCase
{
    public function testHasTwoValues(): void
    {
        self::assertCount(2, Sex::cases());
    }

    public function testValuesAreStable(): void
    {
        self::assertSame('macho', Sex::MACHO->value);
        self::assertSame('femea', Sex::FEMEA->value);
    }

    public function testLabelsCarryAccents(): void
    {
        self::assertSame('Macho', Sex::MACHO->label());
        self::assertSame('Fêmea', Sex::FEMEA->label());
    }
}
