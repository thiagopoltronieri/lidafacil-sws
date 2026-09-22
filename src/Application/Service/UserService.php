<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Security\PasswordHasher;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Domain\User\UserValidator;
use App\Infrastructure\Repository\RefreshTokenRepository;
use App\Infrastructure\Repository\UserRepository;
use PDOException;
use RuntimeException;

// orquestra validação, unicidade de e-mail, hashing de senha e persistência dos usuários
final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly UserValidator $validator,
        private readonly PasswordHasher $hasher,
        private readonly RefreshTokenRepository $refreshTokens,
    ) {
    }

    /**
     * @return list<User>
     */
    public function list(): array
    {
        return $this->users->all();
    }

    public function get(int $id): ?User
    {
        return $this->users->findById($id);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function create(array $input): User
    {
        $errors = $this->validator->validateForCreate($input);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($this->users->existsByEmail($email)) {
            throw new ConflictException('Já existe um usuário com esse e-mail.');
        }

        // o unique do banco é a salvaguarda final contra corrida entre a checagem acima e a inserção
        $id = $this->guardUniqueEmail(fn (): int => $this->users->create(
            trim((string) $input['name']),
            $email,
            $this->hasher->hash((string) $input['password']),
            Role::from((string) $input['role']),
        ));

        return $this->reload($id);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function update(int $id, array $input, bool $canAssignRole, bool $canSetPassword, User $current): User
    {
        $errors = $this->validator->validateForUpdate($input, $canAssignRole);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $email = trim((string) ($input['email'] ?? ''));
        if ($this->users->existsByEmail($email, $id)) {
            throw new ConflictException('Já existe um usuário com esse e-mail.');
        }

        // o perfil só muda quando o ator pode atribuir e o campo veio no corpo; senão mantém o atual
        $role = $current->role;
        $roleRaw = trim((string) ($input['role'] ?? ''));
        if ($canAssignRole && $roleRaw !== '') {
            $role = Role::from($roleRaw);
        }

        $this->guardUniqueEmail(function () use ($id, $input, $email, $role): int {
            $this->users->update($id, trim((string) $input['name']), $email, $role);

            return $id;
        });

        // troca de senha só quando permitido (admin ou o próprio usuário); do contrário o campo é ignorado
        $password = (string) ($input['password'] ?? '');
        if ($canSetPassword && $password !== '') {
            $this->users->changePassword($id, $this->hasher->hash($password));
        }

        return $this->reload($id);
    }

    public function delete(int $id): void
    {
        // revoga os refresh tokens do usuário para cortar a renovação de acesso após a exclusão
        $this->refreshTokens->revokeAllForUser($id);
        $this->users->delete($id);
    }

    /**
     * @param callable():int $write
     */
    private function guardUniqueEmail(callable $write): int
    {
        try {
            return $write();
        } catch (PDOException $exception) {
            // sqlstate 23000 = violação de integridade (unique do e-mail); traduz para conflito 409
            if ($exception->getCode() === '23000') {
                throw new ConflictException('Já existe um usuário com esse e-mail.');
            }
            throw $exception;
        }
    }

    private function reload(int $id): User
    {
        $user = $this->users->findById($id);
        if ($user === null) {
            // não deve ocorrer logo após a escrita; o tratamento existe para satisfazer o tipo de retorno
            throw new RuntimeException('usuário não encontrado após a operação');
        }

        return $user;
    }
}
