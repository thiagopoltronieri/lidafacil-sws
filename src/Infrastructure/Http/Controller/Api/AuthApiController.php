<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Security\InvalidTokenException;
use App\Application\Security\JwtService;
use App\Application\Security\RefreshTokenService;
use App\Application\Service\AuthService;
use App\Domain\User\User;
use App\Infrastructure\Http\Json;
use App\Infrastructure\Repository\UserRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// endpoints públicos de autenticação: login, renovação e logout de token
final class AuthApiController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refresh,
        private readonly UserRepository $users,
    ) {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            return Json::error($response, 'Informe e-mail e senha.', 422);
        }

        $user = $this->auth->attempt($email, $password);
        if ($user === null) {
            // mensagem única para senha errada ou e-mail inexistente: não vaza quais e-mails existem
            return Json::error($response, 'Credenciais inválidas.', 401);
        }

        return $this->withTokens($response, $this->tokenPayload($user, $this->refresh->issue($user->id)));
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();

        try {
            $rotated = $this->refresh->rotate((string) ($body['refresh_token'] ?? ''));
        } catch (InvalidTokenException) {
            return Json::error($response, 'Refresh token inválido ou expirado.', 401);
        }

        $user = $this->users->findById($rotated['user_id']);
        if ($user === null) {
            return Json::error($response, 'Usuário do token não existe mais.', 401);
        }

        return $this->withTokens($response, $this->tokenPayload($user, $rotated['token']));
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) $request->getParsedBody();
        // revoga o refresh token apresentaod; idempotente mesmo se já revogado ou inexistente
        $this->refresh->revoke((string) ($body['refresh_token'] ?? ''));

        return $response->withStatus(204);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function withTokens(ResponseInterface $response, array $payload): ResponseInterface
    {
        // respostas com token não devem ser cacheadas por navegador ou proxy
        return Json::write($response, $payload, 200)->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @return array<string,mixed>
     */
    private function tokenPayload(User $user, string $refreshToken): array
    {
        return [
            'access_token' => $this->jwt->issueAccessToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->jwt->accessTtl(),
            'refresh_token' => $refreshToken,
            'user' => $user->toPublicArray(),
        ];
    }
}
