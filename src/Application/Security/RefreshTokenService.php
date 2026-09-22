<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Infrastructure\Repository\RefreshTokenRepository;
use App\Support\Clock;

// emite, valida, rotaciona e revoga refresh tokens; o token cru só existe no momento da emissão
final class RefreshTokenService
{
    public function __construct(
        private readonly RefreshTokenRepository $tokens,
        private readonly Clock $clock,
        private readonly int $refreshTtl,
    ) {
    }

    public function issue(int $userId): string
    {
        // 256 bits de entropia; por ser aleatório e forte, o hash de lookup pode ser sha256, não é senha
        $token = bin2hex(random_bytes(32));
        $expiresAt = $this->clock->now()
            ->modify('+' . $this->refreshTtl . ' seconds')
            ->format('Y-m-d H:i:s');

        $this->tokens->create($userId, $this->hash($token), $expiresAt);

        return $token;
    }

    /**
     * valida o token apresentado, revoga-o e emite um novo com rotação; devolve o dono e o novo token
     *
     * @return array{user_id:int,token:string}
     */
    public function rotate(string $presentedToken): array
    {
        $userId = $this->verify($presentedToken);

        // revoga antes de emitir; a revogação condicional é atômica, então em corrida só uma chamada muda a linha
        if ($this->tokens->revokeByHash($this->hash($presentedToken)) !== 1) {
            throw new InvalidTokenException('refresh token já utilizado');
        }

        return [
            'user_id' => $userId,
            'token' => $this->issue($userId),
        ];
    }

    public function revoke(string $presentedToken): void
    {
        $this->tokens->revokeByHash($this->hash($presentedToken));
    }

    private function verify(string $presentedToken): int
    {
        if (trim($presentedToken) === '') {
            throw new InvalidTokenException('refresh token ausente');
        }

        $row = $this->tokens->findByHash($this->hash($presentedToken));
        if ($row === null) {
            throw new InvalidTokenException('refresh token inválido');
        }

        if ($row['revoked_at'] !== null) {
            throw new InvalidTokenException('refresh token revogado');
        }

        if ($row['expires_at'] <= $this->clock->now()->format('Y-m-d H:i:s')) {
            throw new InvalidTokenException('refresh token expirado');
        }

        return $row['user_id'];
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
