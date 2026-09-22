<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Application\Security\InvalidTokenException;
use App\Application\Security\JwtService;
use App\Domain\User\Role;
use App\Domain\User\User;
use App\Tests\Support\FixedClock;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private const SECRET = 'segredo-de-teste-bem-grande-para-hs256-0123456789';

    public function testIssuesAndParsesRoundTrip(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));

        $token = $service->issueAccessToken($this->user());
        $auth = $service->parse($token);

        self::assertSame(7, $auth->id);
        self::assertSame(Role::ADMIN, $auth->role);
    }

    public function testAccessTokenPayloadDoesNotCarryNameOrEmail(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));

        $token = $service->issueAccessToken($this->user());
        // decodifica o payload, parte do meio do jwt, para conferir que não há pii nas claims
        $parts = explode('.', $token);
        $payload = (array) json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        self::assertArrayNotHasKey('name', $payload);
        self::assertArrayNotHasKey('email', $payload);
        self::assertSame('admin', $payload['role'] ?? null);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $issuer = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));
        $token = $issuer->issueAccessToken($this->user());

        // o mesmo token, lido 901s depois (ttl é 900s), já deve estar expirado
        $later = $this->service(new DateTimeImmutable('2026-03-01 12:15:01'));

        $this->expectException(InvalidTokenException::class);
        $later->parse($token);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));
        $token = $service->issueAccessToken($this->user());

        // corrompe a assinatura trocando os últimos caracteres
        $tampered = substr($token, 0, -4) . 'AAAA';

        $this->expectException(InvalidTokenException::class);
        $service->parse($tampered);
    }

    public function testTokenSignedWithAnotherSecretIsRejected(): void
    {
        $foreign = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'), 'outro-segredo-totalmente-diferente-987654321');
        $token = $foreign->issueAccessToken($this->user());

        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));

        $this->expectException(InvalidTokenException::class);
        $service->parse($token);
    }

    public function testMalformedTokenIsRejected(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));

        $this->expectException(InvalidTokenException::class);
        $service->parse('isto-nao-e-um-jwt');
    }

    public function testEmptyTokenIsRejected(): void
    {
        $service = $this->service(new DateTimeImmutable('2026-03-01 12:00:00'));

        $this->expectException(InvalidTokenException::class);
        $service->parse('');
    }

    private function service(DateTimeImmutable $now, string $secret = self::SECRET): JwtService
    {
        return new JwtService($secret, 'lidafacil-sws', 900, new FixedClock($now));
    }

    private function user(): User
    {
        return new User(7, 'Ada Lovelace', 'ada@lidafacil.local', Role::ADMIN, 'hash-qualquer', '2026-01-01 00:00:00');
    }
}
