<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Security\AccessPolicy;
use App\Application\Service\AnimalService;
use App\Application\Service\VaccinationService;
use App\Application\Service\ValidationException;
use App\Domain\Vaccination\Vaccination;
use App\Infrastructure\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// histórico de vacinação como sub-recurso do animal; mesma regra de acesso do rebanho
final class VaccinationApiController
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly AnimalService $animals,
        private readonly VaccinationService $vaccinations,
        private readonly AccessPolicy $policy,
    ) {
    }

    /**
     * @param array<string,string> $args
     */
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $animalId = (int) $args['id'];
        if ($this->animals->get($animalId) === null) {
            return Json::error($response, 'Animal não encontrado.', 404);
        }

        $data = array_map(
            fn (Vaccination $vaccination): array => $this->present($vaccination),
            $this->vaccinations->forAnimal($animalId),
        );

        return Json::write($response, ['data' => $data], 200);
    }

    /**
     * @param array<string,string> $args
     */
    public function store(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $animalId = (int) $args['id'];
        if ($this->animals->get($animalId) === null) {
            return Json::error($response, 'Animal não encontrado.', 404);
        }

        try {
            $vaccination = $this->vaccinations->create($animalId, (array) $request->getParsedBody());
        } catch (ValidationException $exception) {
            return Json::error($response, 'Dados inválidos.', 422, $exception->errors());
        }

        return Json::write($response, ['data' => $this->present($vaccination)], 201);
    }

    /**
     * @param array<string,string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->policy->canManageAnimals($this->actor($request)->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $animalId = (int) $args['id'];
        $vaccinationId = (int) $args['vaccinationId'];

        $vaccination = $this->vaccinations->find($vaccinationId);
        // confere que a vacina existe e pertence ao animal da url (evita apagar registro de outro animal)
        if ($vaccination === null || $vaccination->animalId !== $animalId) {
            return Json::error($response, 'Registro de vacinação não encontrado.', 404);
        }

        $this->vaccinations->delete($vaccinationId);

        return $response->withStatus(204);
    }

    /**
     * @return array<string,mixed>
     */
    private function present(Vaccination $vaccination): array
    {
        return [
            'id' => $vaccination->id,
            'animal_id' => $vaccination->animalId,
            'vaccine_name' => $vaccination->vaccineName,
            'dose' => $vaccination->dose,
            'applied_at' => $vaccination->appliedAt->format('Y-m-d'),
            'notes' => $vaccination->notes,
            'created_at' => $vaccination->createdAt?->format('Y-m-d H:i:s'),
        ];
    }
}
