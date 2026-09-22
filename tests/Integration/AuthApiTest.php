<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\User\Role;

final class AuthApiTest extends AppTestCase
{
    private const string EMAIL = 'admin@lidafacil.local';

    public function testLoginWithValidCredentialsReturnsTokens(): void
    {
        $this->createUser(self::EMAIL, Role::ADMIN);

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => self::DEFAULT_PASSWORD,
        ]);

        self::assertSame(200, $response->getStatusCode());
        $json = $this->json($response);
        self::assertNotEmpty($json['access_token'] ?? '');
        self::assertNotEmpty($json['refresh_token'] ?? '');
        self::assertSame('Bearer', $json['token_type'] ?? null);
        self::assertSame(900, $json['expires_in'] ?? null);
        self::assertIsArray($json['user'] ?? null);
        self::assertSame('admin', $json['user']['role'] ?? null);
        // o hash da senha nunca pode aparecer na resposta
        self::assertArrayNotHasKey('password_hash', $json['user']);
    }

    public function testLoginWithWrongPasswordIsUnauthorized(): void
    {
        $this->createUser(self::EMAIL, Role::ADMIN);

        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => 'senha-errada',
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testLoginWithUnknownEmailIsUnauthorized(): void
    {
        $response = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'ninguem@lidafacil.local',
            'password' => self::DEFAULT_PASSWORD,
        ]);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testLoginWithMissingFieldsIsUnprocessable(): void
    {
        $response = $this->request('POST', '/api/v1/auth/login', ['email' => self::EMAIL]);

        self::assertSame(422, $response->getStatusCode());
    }

    public function testProtectedRouteRequiresToken(): void
    {
        $response = $this->request('GET', '/api/v1/users/me');

        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('Bearer', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function testProtectedRouteRejectsGarbageToken(): void
    {
        $response = $this->request('GET', '/api/v1/users/me', null, 'isto-nao-e-um-token');

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRefreshRotatesTokenAndOldOneIsRejected(): void
    {
        $this->createUser(self::EMAIL, Role::ADMIN);
        $first = $this->json($this->login());
        $firstRefresh = (string) $first['refresh_token'];

        $rotated = $this->request('POST', '/api/v1/auth/refresh', ['refresh_token' => $firstRefresh]);
        self::assertSame(200, $rotated->getStatusCode());
        $secondRefresh = (string) $this->json($rotated)['refresh_token'];
        self::assertNotSame($firstRefresh, $secondRefresh);

        // reusar o refresh já rotacionado deve falhar; rotação com revogação
        $reused = $this->request('POST', '/api/v1/auth/refresh', ['refresh_token' => $firstRefresh]);
        self::assertSame(401, $reused->getStatusCode());

        // o novo refresh segue válido
        $again = $this->request('POST', '/api/v1/auth/refresh', ['refresh_token' => $secondRefresh]);
        self::assertSame(200, $again->getStatusCode());
    }

    public function testLogoutRevokesRefreshToken(): void
    {
        $this->createUser(self::EMAIL, Role::ADMIN);
        $refresh = (string) $this->json($this->login())['refresh_token'];

        $logout = $this->request('POST', '/api/v1/auth/logout', ['refresh_token' => $refresh]);
        self::assertSame(204, $logout->getStatusCode());

        $afterLogout = $this->request('POST', '/api/v1/auth/refresh', ['refresh_token' => $refresh]);
        self::assertSame(401, $afterLogout->getStatusCode());
    }

    private function login(): \Psr\Http\Message\ResponseInterface
    {
        return $this->request('POST', '/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => self::DEFAULT_PASSWORD,
        ]);
    }
}
