<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Vaccination\VaccinationValidator;
use App\Tests\Support\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class VaccinationValidatorTest extends TestCase
{
    private function validator(): VaccinationValidator
    {
        return new VaccinationValidator(new FixedClock(new DateTimeImmutable('2026-09-14 12:00:00')));
    }

    /** @return array<string,string> */
    private function validInput(): array
    {
        return [
            'vaccine_name' => 'Aftosa',
            'dose' => '1a dose',
            'applied_at' => '2026-08-01',
            'notes' => 'lote 123',
        ];
    }

    public function testValidInputHasNoErrors(): void
    {
        self::assertSame([], $this->validator()->validate($this->validInput()));
    }

    public function testBlankVaccineNameIsRejected(): void
    {
        $input = $this->validInput();
        $input['vaccine_name'] = '';
        self::assertArrayHasKey('vaccine_name', $this->validator()->validate($input));
    }

    public function testMissingAppliedAtIsRejected(): void
    {
        $input = $this->validInput();
        $input['applied_at'] = '';
        self::assertArrayHasKey('applied_at', $this->validator()->validate($input));
    }

    public function testFutureAppliedAtIsRejected(): void
    {
        $input = $this->validInput();
        $input['applied_at'] = '2026-09-15';
        self::assertArrayHasKey('applied_at', $this->validator()->validate($input));
    }

    public function testInvalidAppliedAtFormatIsRejected(): void
    {
        $input = $this->validInput();
        $input['applied_at'] = '01/08/2026';
        self::assertArrayHasKey('applied_at', $this->validator()->validate($input));
    }

    public function testTooLongVaccineNameIsRejected(): void
    {
        $input = $this->validInput();
        $input['vaccine_name'] = str_repeat('a', 81);
        self::assertArrayHasKey('vaccine_name', $this->validator()->validate($input));
    }
}
