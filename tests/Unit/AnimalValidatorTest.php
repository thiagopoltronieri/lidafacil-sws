<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Animal\AnimalValidator;
use App\Tests\Support\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AnimalValidatorTest extends TestCase
{
    private function validator(): AnimalValidator
    {
        // hoje fixado para tornar as regras de data previsíveis
        return new AnimalValidator(new FixedClock(new DateTimeImmutable('2026-09-14 12:00:00')));
    }

    /** @return array<string,string> */
    private function validInput(): array
    {
        return [
            'tag' => 'BOV-001',
            'species' => 'bovino',
            'sex' => 'femea',
            'birth_date' => '2024-01-10',
            'weight_kg' => '450.5',
            'notes' => 'novilha em bom estado',
        ];
    }

    public function testValidInputHasNoErrors(): void
    {
        self::assertSame([], $this->validator()->validate($this->validInput()));
    }

    public function testBlankTagIsRejected(): void
    {
        $input = $this->validInput();
        $input['tag'] = '   ';
        self::assertArrayHasKey('tag', $this->validator()->validate($input));
    }

    public function testTagWithInvalidCharactersIsRejected(): void
    {
        $input = $this->validInput();
        $input['tag'] = 'BOV 001@';
        self::assertArrayHasKey('tag', $this->validator()->validate($input));
    }

    public function testUnknownSpeciesIsRejected(): void
    {
        $input = $this->validInput();
        $input['species'] = 'cavalo';
        self::assertArrayHasKey('species', $this->validator()->validate($input));
    }

    public function testUnknownSexIsRejected(): void
    {
        $input = $this->validInput();
        $input['sex'] = 'x';
        self::assertArrayHasKey('sex', $this->validator()->validate($input));
    }

    public function testZeroWeightIsRejected(): void
    {
        $input = $this->validInput();
        $input['weight_kg'] = '0';
        self::assertArrayHasKey('weight_kg', $this->validator()->validate($input));
    }

    public function testNegativeWeightIsRejected(): void
    {
        $input = $this->validInput();
        $input['weight_kg'] = '-5';
        self::assertArrayHasKey('weight_kg', $this->validator()->validate($input));
    }

    public function testNonNumericWeightIsRejected(): void
    {
        $input = $this->validInput();
        $input['weight_kg'] = 'abc';
        self::assertArrayHasKey('weight_kg', $this->validator()->validate($input));
    }

    public function testImplausiblyHighWeightIsRejected(): void
    {
        $input = $this->validInput();
        $input['weight_kg'] = '5000';
        self::assertArrayHasKey('weight_kg', $this->validator()->validate($input));
    }

    public function testFutureBirthDateIsRejected(): void
    {
        $input = $this->validInput();
        $input['birth_date'] = '2026-09-15';
        self::assertArrayHasKey('birth_date', $this->validator()->validate($input));
    }

    public function testBirthDateTodayIsAccepted(): void
    {
        $input = $this->validInput();
        $input['birth_date'] = '2026-09-14';
        self::assertArrayNotHasKey('birth_date', $this->validator()->validate($input));
    }

    public function testEmptyBirthDateIsAccepted(): void
    {
        $input = $this->validInput();
        $input['birth_date'] = '';
        self::assertArrayNotHasKey('birth_date', $this->validator()->validate($input));
    }

    public function testInvalidBirthDateFormatIsRejected(): void
    {
        $input = $this->validInput();
        $input['birth_date'] = '10/01/2024';
        self::assertArrayHasKey('birth_date', $this->validator()->validate($input));
    }

    public function testTooLongNotesAreRejected(): void
    {
        $input = $this->validInput();
        $input['notes'] = str_repeat('a', 501);
        self::assertArrayHasKey('notes', $this->validator()->validate($input));
    }
}
