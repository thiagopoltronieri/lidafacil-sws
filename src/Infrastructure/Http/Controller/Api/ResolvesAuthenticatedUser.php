<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller\Api;

use App\Application\Security\AuthenticatedUser;
use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

// recupera o usuário autenticado anexado pelo JwtAuthMiddleware; compartilhado pelos controllers da api
trait ResolvesAuthenticatedUser
{
    private function actor(ServerRequestInterface $request): AuthenticatedUser
    {
        $actor = $request->getAttribute(JwtAuthMiddleware::ATTRIBUTE);
        if (!$actor instanceof AuthenticatedUser) {
            // não deve ocorrer: o middleware jwt sempre anexa o usuário antes de chegar ao controller
            throw new RuntimeException('requisição sem usuário autenticado');
        }

        return $actor;
    }
}
