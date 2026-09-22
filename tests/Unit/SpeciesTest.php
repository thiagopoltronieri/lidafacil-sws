<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Animal\Species;
use PHPUnit\Framework\TestCase;

final class SpeciesTest extends TestCase
{
    public function testHasThreeSpecies(): void
    {
        self::assertCount(3, Species::cases());
    }

    public function testValuesAreStable(): void
    {
        self::assertSame('bovino', Species::BOVINO->value);
        self::assertSame('suino', Species::SUINO->value);
        self::assertSame('ovino', Species::OVINO->value);
    }

    public function testLabelsCarryAccents(): void
    {
        self::assertSame('Bovino', Species::BOVINO->label());
        self::assertSame('Suíno', Species::SUINO->label());
        self::assertSame('Ovino', Species::OVINO->label());
    }
}
