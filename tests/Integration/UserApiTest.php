<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\User\Role;

final class UserApiTest extends AppTestCase
{
    private int $adminId;
    private int $operatorId;
    private int $clientId;
    private string $adminToken;
    private string $operatorToken;
    private string $clientToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = $this->createUser('admin@lidafacil.local', Role::ADMIN, name: 'Admin');
        $this->operatorId = $this->createUser('operator@lidafacil.local', Role::OPERATOR, name: 'Operador');
        $this->clientId = $this->createUser('client@lidafacil.local', Role::CLIENT, name: 'Cliente');

        $this->adminToken = $this->tokenFor($this->adminId);
        $this->operatorToken = $this->tokenFor($this->operatorId);
        $this->clientToken = $this->tokenFor($this->clientId);
    }

    public function testListRequiresAuthentication(): void
    {
        self::assertSame(401, $this->request('GET', '/api/v1/users')->getStatusCode());
    }

    public function testClientCannotListUsers(): void
    {
        self::assertSame(403, $this->request('GET', '/api/v1/users', null, $this->clientToken)->getStatusCode());
    }

    public function testOperatorCanListUsers(): void
    {
        $response = $this->request('GET', '/api/v1/users', null, $this->operatorToken);

        self::assertSame(200, $response->getStatusCode());
        self::assertIsArray($this->json($response)['data'] ?? null);
    }

    public function testAdminCanCreateUser(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Nova Operadora',
            'email' => 'nova@lidafacil.local',
            'password' => 'senha-bem-forte-2026',
            'role' => 'operator',
        ], $this->adminToken);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('operator', $this->json($response)['data']['role'] ?? null);
    }

    public function testOperatorCannotCreateUser(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Tentativa',
            'email' => 'tentativa@lidafacil.local',
            'password' => 'senha-bem-forte-2026',
            'role' => 'client',
        ], $this->operatorToken);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testCreateWithInvalidBodyIsUnprocessable(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => '',
            'email' => 'nao-e-email',
            'password' => 'curta',
            'role' => 'chefe',
        ], $this->adminToken);

        self::assertSame(422, $response->getStatusCode());
        self::assertArrayHasKey('fields', $this->json($response)['error'] ?? []);
    }

    public function testCreateWithDuplicateEmailIsConflict(): void
    {
        $response = $this->request('POST', '/api/v1/users', [
            'name' => 'Repetido',
            'email' => 'client@lidafacil.local',
            'password' => 'senha-bem-forte-2026',
            'role' => 'client',
        ], $this->adminToken);

        self::assertSame(409, $response->getStatusCode());
    }

    public function testClientCanViewOwnRecordButNotOthers(): void
    {
        $own = $this->request('GET', '/api/v1/users/' . $this->clientId, null, $this->clientToken);
        self::assertSame(200, $own->getStatusCode());

        $other = $this->request('GET', '/api/v1/users/' . $this->adminId, null, $this->clientToken);
        self::assertSame(403, $other->getStatusCode());
    }

    public function testViewNonExistentUserIsNotFound(): void
    {
        $response = $this->request('GET', '/api/v1/users/999999', null, $this->adminToken);

        self::assertSame(404, $response->getStatusCode());
    }

    public function testClientCannotEscalateOwnRole(): void
    {
        $response = $this->request('PUT', '/api/v1/users/' . $this->clientId, [
            'name' => 'Cliente',
            'email' => 'client@lidafacil.local',
            'role' => 'admin',
        ], $this->clientToken);

        self::assertSame(200, $response->getStatusCode());
        // o campo role é ignorado para quem não pode atribuir perfil: continua client
        self::assertSame('client', $this->json($response)['data']['role'] ?? null);
    }

    public function testOperatorCannotChangeUserRole(): void
    {
        $response = $this->request('PUT', '/api/v1/users/' . $this->clientId, [
            'name' => 'Cliente Editado',
            'email' => 'client@lidafacil.local',
            'role' => 'admin',
        ], $this->operatorToken);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('client', $this->json($response)['data']['role'] ?? null);
    }

    public function testAdminCanChangeUserRole(): void
    {
        $response = $this->request('PUT', '/api/v1/users/' . $this->clientId, [
            'name' => 'Cliente Promovido',
            'email' => 'client@lidafacil.local',
            'role' => 'operator',
        ], $this->adminToken);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('operator', $this->json($response)['data']['role'] ?? null);
    }

    public function testAdminCanDeleteAnotherUser(): void
    {
        $delete = $this->request('DELETE', '/api/v1/users/' . $this->operatorId, null, $this->adminToken);
        self::assertSame(204, $delete->getStatusCode());

        $get = $this->request('GET', '/api/v1/users/' . $this->operatorId, null, $this->adminToken);
        self::assertSame(404, $get->getStatusCode());
    }

    public function testAdminCannotDeleteOwnAccount(): void
    {
        $response = $this->request('DELETE', '/api/v1/users/' . $this->adminId, null, $this->adminToken);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testOperatorCannotUpdateAdminAccount(): void
    {
        // trava do escalonamento vertical: operador não edita a conta do admin
        $response = $this->request('PUT', '/api/v1/users/' . $this->adminId, [
            'name' => 'Invasor',
            'email' => 'admin@lidafacil.local',
        ], $this->operatorToken);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testOperatorCannotResetAnotherUsersPassword(): void
    {
        // operador pode editar um cliente, mas a redefinição de senha de terceiros é bloqueada
        $edit = $this->request('PUT', '/api/v1/users/' . $this->clientId, [
            'name' => 'Cliente Editado',
            'email' => 'client@lidafacil.local',
            'password' => 'senha-injetada-pelo-operador',
        ], $this->operatorToken);
        self::assertSame(200, $edit->getStatusCode());

        // a senha original continua válida, ou seja, o operador não conseguiu resetá-la
        $comOriginal = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'client@lidafacil.local',
            'password' => self::DEFAULT_PASSWORD,
        ]);
        self::assertSame(200, $comOriginal->getStatusCode());

        // e a senha que o operador tentou injetar não funciona
        $comInjetada = $this->request('POST', '/api/v1/auth/login', [
            'email' => 'client@lidafacil.local',
            'password' => 'senha-injetada-pelo-operador',
        ]);
        self::assertSame(401, $comInjetada->getStatusCode());
    }

    public function testOperatorAndClientCannotDeleteUsers(): void
    {
        self::assertSame(403, $this->request('DELETE', '/api/v1/users/' . $this->clientId, null, $this->operatorToken)->getStatusCode());
        self::assertSame(403, $this->request('DELETE', '/api/v1/users/' . $this->adminId, null, $this->clientToken)->getStatusCode());
    }

    public function testMeReturnsOwnRecord(): void
    {
        $response = $this->request('GET', '/api/v1/users/me', null, $this->clientToken);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('client@lidafacil.local', $this->json($response)['data']['email'] ?? null);
    }
}
