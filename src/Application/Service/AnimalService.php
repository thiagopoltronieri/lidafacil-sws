<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Animal\Animal;
use App\Domain\Animal\AnimalValidator;
use App\Domain\Animal\Sex;
use App\Domain\Animal\Species;
use App\Infrastructure\Repository\AnimalRepository;
use App\Support\IsoDate;
use PDOException;
use RuntimeException;

// orquestra validação, unicidade do brinco e persistência dos animasi
final class AnimalService
{
    public function __construct(
        private readonly AnimalRepository $animals,
        private readonly AnimalValidator $validator,
    ) {
    }

    /**
     * @return list<Animal>
     */
    public function list(): array
    {
        return $this->animals->all();
    }

    public function get(int $id): ?Animal
    {
        return $this->animals->find($id);
    }

    public function count(): int
    {
        return $this->animals->countAll();
    }

    /**
     * @param array<string,mixed> $input
     */
    public function create(array $input): Animal
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $tag = trim((string) ($input['tag'] ?? ''));
        if ($this->animals->existsByTag($tag)) {
            throw new ConflictException('Já existe um animal com esse brinco.');
        }

        return $this->reload($this->guardUniqueTag(fn (): int => $this->animals->create($this->fromInput(null, $input))));
    }

    /**
     * @param array<string,mixed> $input
     */
    public function update(int $id, array $input): Animal
    {
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $tag = trim((string) ($input['tag'] ?? ''));
        if ($this->animals->existsByTag($tag, $id)) {
            throw new ConflictException('Já existe um animal com esse brinco.');
        }

        $this->guardUniqueTag(function () use ($id, $input): int {
            $this->animals->update($id, $this->fromInput($id, $input));

            return $id;
        });

        return $this->reload($id);
    }

    public function delete(int $id): void
    {
        $this->animals->delete($id);
    }

    /**
     * @param callable():int $write
     */
    private function guardUniqueTag(callable $write): int
    {
        try {
            return $write();
        } catch (PDOException $exception) {
            // sqlstate 23000 = violação de integridade (unique do brinco); traduz para conflito 409
            if ($exception->getCode() === '23000') {
                throw new ConflictException('Já existe um animal com esse brinco.');
            }
            throw $exception;
        }
    }

    private function reload(int $id): Animal
    {
        $animal = $this->animals->find($id);
        if ($animal === null) {
            throw new RuntimeException('animal não encontrado após a operação');
        }

        return $animal;
    }

    /**
     * Monta a entidade a partir do input já validado.
     *
     * @param array<string,mixed> $input
     */
    private function fromInput(?int $id, array $input): Animal
    {
        $birthRaw = trim((string) ($input['birth_date'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));

        return new Animal(
            $id,
            trim((string) ($input['tag'] ?? '')),
            Species::from((string) ($input['species'] ?? '')),
            Sex::from((string) ($input['sex'] ?? '')),
            $birthRaw !== '' ? IsoDate::parse($birthRaw) : null,
            (float) ($input['weight_kg'] ?? 0),
            $notes !== '' ? $notes : null,
        );
    }
}
