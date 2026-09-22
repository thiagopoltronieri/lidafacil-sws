<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Security\JwtService;
use App\Application\Security\PasswordHasher;
use App\Domain\User\Role;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;
use App\Infrastructure\Repository\UserRepository;
use App\Kernel;
use App\Support\SystemClock;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;

// base dos testes de integração: api Slim sobre sqlite em memória, autenticação por jwt
abstract class AppTestCase extends TestCase
{
    protected const string JWT_SECRET = 'segredo-de-teste-hs256-bem-grande-0123456789';
    protected const string JWT_ISSUER = 'lidafacil-sws';
    protected const string DEFAULT_PASSWORD = 'senha-de-teste-forte';
    protected const string ALLOWED_ORIGIN = 'https://front.lidafacil.local';

    protected PDO $pdo;
    protected App $app;
    protected UserRepository $users;

    protected function setUp(): void
    {
        $root = dirname(__DIR__, 2);

        $this->pdo = Connection::create(['driver' => 'sqlite', 'path' => ':memory:']);
        (new Migrator())->migrate($this->pdo, $root . '/database/schema.sql');

        $this->users = new UserRepository($this->pdo, new SystemClock());
        $this->app = Kernel::create($this->settings($root), $this->pdo);
    }

    /**
     * @return array<string,mixed>
     */
    private function settings(string $root): array
    {
        return [
            'debug' => true,
            'schema_file' => $root . '/database/schema.sql',
            'db' => ['driver' => 'sqlite', 'path' => ':memory:'],
            'jwt' => [
                'secret' => self::JWT_SECRET,
                'issuer' => self::JWT_ISSUER,
                'access_ttl' => 900,
                'refresh_ttl' => 604800,
            ],
            'cors' => ['allowed_origins' => [self::ALLOWED_ORIGIN]],
        ];
    }

    protected function createUser(
        string $email,
        Role $role,
        string $password = self::DEFAULT_PASSWORD,
        string $name = 'Usuário Teste',
    ): int {
        return $this->users->create($name, $email, (new PasswordHasher())->hash($password), $role);
    }

    // emite um token de acesso válido para um usuário já existente
    protected function tokenFor(int $userId): string
    {
        $user = $this->users->findById($userId);
        self::assertNotNull($user);

        $jwt = new JwtService(self::JWT_SECRET, self::JWT_ISSUER, 900, new SystemClock());

        return $jwt->issueAccessToken($user);
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     */
    protected function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $token = null,
        array $headers = [],
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        if ($token !== null) {
            $request = $request->withHeader('Authorization', 'Bearer ' . $token);
        }

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request->withParsedBody($body)->withHeader('Content-Type', 'application/json');
        }

        return $this->app->handle($request);
    }

    /**
     * @return array<string,mixed>
     */
    protected function json(ResponseInterface $response): array
    {
        /** @var array<string,mixed> $decoded */
        $decoded = (array) json_decode((string) $response->getBody(), true);

        return $decoded;
    }
}
