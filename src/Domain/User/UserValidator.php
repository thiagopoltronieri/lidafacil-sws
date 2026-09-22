<?php

declare(strict_types=1);

namespace App\Domain\User;

// valida os dados de entrada de usuários da api antes de persistir
final class UserValidator
{
    private const int NAME_MIN = 2;
    private const int NAME_MAX = 255;
    private const int EMAIL_MAX = 255;
    private const int PASSWORD_MIN = 8;
    // bcrypt trunca a senha acima de 72 bytes; barramos antes para não dar falsa sensação de senha longa
    private const int PASSWORD_MAX_BYTES = 72;

    /**
     * @param array<string,mixed> $input
     *
     * @return array<string,string> erros por campo; array vazio significa entrada válida
     */
    public function validateForCreate(array $input): array
    {
        $errors = [];

        $this->checkName($input, $errors);
        $this->checkEmail($input, $errors);
        $this->checkPassword($input, $errors, true);
        $this->checkRole($input, $errors, true);

        return $errors;
    }

    /**
     * na atualização a senha é opcional e o perfil só é validado quando o ator pode atribuí-lo
     *
     * @param array<string,mixed> $input
     *
     * @return array<string,string>
     */
    public function validateForUpdate(array $input, bool $canAssignRole): array
    {
        $errors = [];

        $this->checkName($input, $errors);
        $this->checkEmail($input, $errors);
        $this->checkPassword($input, $errors, false);
        if ($canAssignRole) {
            $this->checkRole($input, $errors, false);
        }

        return $errors;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    private function checkName(array $input, array &$errors): void
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Informe o nome do usuário.';
        } elseif (mb_strlen($name) < self::NAME_MIN) {
            $errors['name'] = 'O nome deve ter pelo menos ' . self::NAME_MIN . ' caracteres.';
        } elseif (mb_strlen($name) > self::NAME_MAX) {
            $errors['name'] = 'O nome deve ter no máximo ' . self::NAME_MAX . ' caracteres.';
        }
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    private function checkEmail(array $input, array &$errors): void
    {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Informe o e-mail.';
        } elseif (mb_strlen($email) > self::EMAIL_MAX) {
            $errors['email'] = 'O e-mail deve ter no máximo ' . self::EMAIL_MAX . ' caracteres.';
        } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Informe um e-mail válido.';
        }
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    private function checkPassword(array $input, array &$errors, bool $required): void
    {
        $password = (string) ($input['password'] ?? '');

        if ($password === '') {
            if ($required) {
                $errors['password'] = 'Informe uma senha.';
            }

            return;
        }

        if (mb_strlen($password) < self::PASSWORD_MIN) {
            $errors['password'] = 'A senha deve ter pelo menos ' . self::PASSWORD_MIN . ' caracteres.';
        } elseif (strlen($password) > self::PASSWORD_MAX_BYTES) {
            $errors['password'] = 'A senha deve ter no máximo ' . self::PASSWORD_MAX_BYTES . ' bytes.';
        }
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,string> $errors
     */
    private function checkRole(array $input, array &$errors, bool $required): void
    {
        $role = trim((string) ($input['role'] ?? ''));

        if ($role === '') {
            if ($required) {
                $errors['role'] = 'Selecione um perfil de acesso.';
            }

            return;
        }

        if (Role::tryFrom($role) === null) {
            $errors['role'] = 'Selecione um perfil de acesso válido: ' . implode(', ', Role::values()) . '.';
        }
    }
}
