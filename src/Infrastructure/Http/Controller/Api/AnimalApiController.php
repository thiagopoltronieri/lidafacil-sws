<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Security\AccessPolicy;
use App\Application\Service\AnimalService;
use App\Application\Service\ConflictException;
use App\Application\Service\ValidationException;
use App\Domain\Animal\Animal;
use App\Infrastructure\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// endpoints protegidos de gestão do rebanho; disponíveis para admin e operador; cliente recebe 403
final class AnimalApiController
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly AnimalService $animals,
        private readonly AccessPolicy $policy,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $data = array_map(fn (Animal $animal): array => $this->present($animal), $this->animals->list());

        return Json::write($response, ['data' => $data], 200);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        try {
            $animal = $this->animals->create((array) $request->getParsedBody());
        } catch (ValidationException $exception) {
            return Json::error($response, 'Dados inválidos.', 422, $exception->errors());
        } catch (ConflictException $exception) {
            return Json::error($response, $exception->getMessage(), 409);
        }

        return Json::write($response, ['data' => $this->present($animal)], 201);
    }

    /**
     * @param array<string,string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $animal = $this->animals->get((int) $args['id']);
        if ($animal === null) {
            return Json::error($response, 'Animal não encontrado.', 404);
        }

        return Json::write($response, ['data' => $this->present($animal)], 200);
    }

    /**
     * @param array<string,string> $args
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $id = (int) $args['id'];
        if ($this->animals->get($id) === null) {
            return Json::error($response, 'Animal não encontrado.', 404);
        }

        try {
            $animal = $this->animals->update($id, (array) $request->getParsedBody());
        } catch (ValidationException $exception) {
            return Json::error($response, 'Dados inválidos.', 422, $exception->errors());
        } catch (ConflictException $exception) {
            return Json::error($response, $exception->getMessage(), 409);
        }

        return Json::write($response, ['data' => $this->present($animal)], 200);
    }

    /**
     * @param array<string,string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $id = (int) $args['id'];
        if ($this->animals->get($id) === null) {
            return Json::error($response, 'Animal não encontrado.', 404);
        }

        $this->animals->delete($id);

        return $response->withStatus(204);
    }

    /**
     * @return array<string,mixed>
     */
    private function present(Animal $animal): array
    {
        return [
            'id' => $animal->id,
            'tag' => $animal->tag,
            'species' => $animal->species->value,
            'sex' => $animal->sex->value,
            'birth_date' => $animal->birthDate?->format('Y-m-d'),
            'weight_kg' => $animal->weightKg,
            'notes' => $animal->notes,
            'created_at' => $animal->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $animal->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
