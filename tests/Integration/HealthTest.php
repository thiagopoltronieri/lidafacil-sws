<?php

declare(strict_types=1);

namespace App\Tests\Integration;

final class HealthTest extends AppTestCase
{
    public function testHealthReturnsOk(): void
    {
        $response = $this->request('GET', '/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $this->json($response)['status'] ?? null);
    }

    public function testSecurityHeadersArePresentEvenOnPublicRoutes(): void
    {
        $response = $this->request('GET', '/health');

        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
        self::assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
        self::assertSame('no-referrer', $response->getHeaderLine('Referrer-Policy'));
    }

    public function testSecurityHeadersArePresentOnErrorResponses(): void
    {
        // rota inexistente gera 404 pelo error middleware; os headers ainda devem estar presentes
        $response = $this->request('GET', '/rota-que-nao-existe');

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    }
}
