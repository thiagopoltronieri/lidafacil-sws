<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\User\UserValidator;
use PHPUnit\Framework\TestCase;

final class UserValidatorTest extends TestCase
{
    private UserValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UserValidator();
    }

    /** @return array<string,mixed> */
    private function validInput(): array
    {
        return [
            'name' => 'Ada Lovelace',
            'email' => 'ada@lidafacil.local',
            'password' => 'senha-bem-forte-2026',
            'role' => 'operator',
        ];
    }

    public function testValidCreateInputHasNoErrors(): void
    {
        self::assertSame([], $this->validator->validateForCreate($this->validInput()));
    }

    public function testBlankNameIsRejected(): void
    {
        $input = $this->validInput();
        $input['name'] = '  ';
        self::assertArrayHasKey('name', $this->validator->validateForCreate($input));
    }

    public function testInvalidEmailIsRejected(): void
    {
        $input = $this->validInput();
        $input['email'] = 'nao-e-email';
        self::assertArrayHasKey('email', $this->validator->validateForCreate($input));
    }

    public function testShortPasswordIsRejected(): void
    {
        $input = $this->validInput();
        $input['password'] = 'curta';
        self::assertArrayHasKey('password', $this->validator->validateForCreate($input));
    }

    public function testPasswordAboveBcryptLimitIsRejected(): void
    {
        $input = $this->validInput();
        // bcrypt trunca acima de 72 bytes, então barramos antes para não dar falsa sensação de senha longa
        $input['password'] = str_repeat('a', 73);
        self::assertArrayHasKey('password', $this->validator->validateForCreate($input));
    }

    public function testUnknownRoleIsRejected(): void
    {
        $input = $this->validInput();
        $input['role'] = 'superuser';
        self::assertArrayHasKey('role', $this->validator->validateForCreate($input));
    }

    public function testMissingPasswordOnCreateIsRejected(): void
    {
        $input = $this->validInput();
        unset($input['password']);
        self::assertArrayHasKey('password', $this->validator->validateForCreate($input));
    }

    public function testUpdateWithoutPasswordIsValid(): void
    {
        $input = $this->validInput();
        unset($input['password']);
        self::assertSame([], $this->validator->validateForUpdate($input, true));
    }

    public function testUpdateWithWeakPasswordIsRejected(): void
    {
        $input = $this->validInput();
        $input['password'] = '123';
        self::assertArrayHasKey('password', $this->validator->validateForUpdate($input, true));
    }

    public function testUpdateIgnoresRoleWhenActorCannotAssignIt(): void
    {
        $input = $this->validInput();
        $input['role'] = 'valor-invalido';
        // quando o ator não pode atribuir perfil, o campo role é ignorado e não gera erro
        self::assertArrayNotHasKey('role', $this->validator->validateForUpdate($input, false));
    }

    public function testUpdateValidatesRoleWhenActorCanAssignIt(): void
    {
        $input = $this->validInput();
        $input['role'] = 'valor-invalido';
        self::assertArrayHasKey('role', $this->validator->validateForUpdate($input, true));
    }
}
