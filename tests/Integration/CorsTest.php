<?php

declare(strict_types=1);

namespace App\Tests\Integration;

final class CorsTest extends AppTestCase
{
    public function testPreflightFromAllowedOriginReturnsHeaders(): void
    {
        $response = $this->request('OPTIONS', '/api/v1/users', null, null, ['Origin' => self::ALLOWED_ORIGIN]);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame(self::ALLOWED_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertStringContainsString('POST', $response->getHeaderLine('Access-Control-Allow-Methods'));
        self::assertStringContainsString('Authorization', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testPreflightFromDisallowedOriginHasNoCorsHeader(): void
    {
        $response = $this->request('OPTIONS', '/api/v1/users', null, null, ['Origin' => 'https://site-malicioso.example']);

        // sem eco de origem: a lista de permissão não libera origens desconhecidas
        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testActualRequestFromAllowedOriginGetsCorsHeader(): void
    {
        $response = $this->request('GET', '/health', null, null, ['Origin' => self::ALLOWED_ORIGIN]);

        self::assertSame(self::ALLOWED_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        self::assertSame('Origin', $response->getHeaderLine('Vary'));
    }
}
