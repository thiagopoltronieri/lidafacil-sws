<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Security\AccessPolicy;
use App\Application\Service\ConflictException;
use App\Application\Service\UserService;
use App\Application\Service\ValidationException;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Infrastructure\Http\Json;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// endpoints protegidos de gestão de usuários; a autorização segue a matriz rbac da AccessPolicy
final class UserApiController
{
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly UserService $users,
        private readonly AccessPolicy $policy,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $actor = $this->actor($request);
        if (!$this->policy->canListUsers($actor->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $data = array_map(static fn (User $user): array => $user->toPublicArray(), $this->users->list());

        return Json::write($response, ['data' => $data], 200);
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $actor = $this->actor($request);
        if (!$this->policy->canCreateUser($actor->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        try {
            $user = $this->users->create((array) $request->getParsedBody());
        } catch (ValidationException $exception) {
            return Json::error($response, 'Dados inválidos.', 422, $exception->errors());
        } catch (ConflictException $exception) {
            return Json::error($response, $exception->getMessage(), 409);
        }

        return Json::write($response, ['data' => $user->toPublicArray()], 201);
    }

    /**
     * @param array<string,string> $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $this->actor($request);
        $id = (int) $args['id'];

        // checa a permissão antes da existência: um cliente não descobre se outro id existe
        if (!$this->policy->canViewUser($actor, $id)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $user = $this->users->get($id);
        if ($user === null) {
            return Json::error($response, 'Usuário não encontrado.', 404);
        }

        return Json::write($response, ['data' => $user->toPublicArray()], 200);
    }

    /**
     * @param array<string,string> $args
     */
    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $this->actor($request);
        $id = (int) $args['id'];

        // cliente só pode o próprio: barra antes de tocar o banco, sem vazar a existência de outros ids
        if ($actor->role === Role::CLIENT && $actor->id !== $id) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $current = $this->users->get($id);
        if ($current === null) {
            return Json::error($response, 'Usuário não encontrado.', 404);
        }

        // a decisão considera o perfil do alvo: operador não edita admin nem outro operador
        if (!$this->policy->canUpdateUser($actor, $current)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        try {
            $user = $this->users->update(
                $id,
                (array) $request->getParsedBody(),
                $this->policy->canAssignRole($actor->role),
                $this->policy->canSetPassword($actor, $id),
                $current,
            );
        } catch (ValidationException $exception) {
            return Json::error($response, 'Dados inválidos.', 422, $exception->errors());
        } catch (ConflictException $exception) {
            return Json::error($response, $exception->getMessage(), 409);
        }

        return Json::write($response, ['data' => $user->toPublicArray()], 200);
    }

    /**
     * @param array<string,string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $actor = $this->actor($request);
        if (!$this->policy->canDeleteUser($actor->role)) {
            return Json::error($response, 'Acesso negado.', 403);
        }

        $id = (int) $args['id'];
        if ($this->users->get($id) === null) {
            return Json::error($response, 'Usuário não encontrado.', 404);
        }

        // impede o admin de excluir a própria conta e se trancar para fora do sistema; regra de autorização: 403
        if ($actor->id === $id) {
            return Json::error($response, 'Você não pode excluir a própria conta.', 403);
        }

        $this->users->delete($id);

        return $response->withStatus(204);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $actor = $this->actor($request);
        $user = $this->users->get($actor->id);
        if ($user === null) {
            return Json::error($response, 'Usuário não encontrado.', 404);
        }

        return Json::write($response, ['data' => $user->toPublicArray()], 200);
    }
}
