<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Middleware;

use App\Application\Security\InvalidTokenException;
use App\Application\Security\JwtService;
use App\Infrastructure\Http\Json;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

// exige um token jwt válido no header Authorization e anexa o usuário autenticado à requisição - deny-by-default
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE = 'auth';

    public function __construct(
        private readonly JwtService $jwt,
        private readonly ResponseFactoryInterface $responseFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $matches) !== 1) {
            return $this->unauthorized('Token de acesso ausente ou malformado.');
        }

        try {
            $auth = $this->jwt->parse($matches[1]);
        } catch (InvalidTokenException) {
            // mensagem genérica: não revela se o token está expirado, adulterado ou com claim inválida
            return $this->unauthorized('Token de acesso inválido ou expirado.');
        }

        return $handler->handle($request->withAttribute(self::ATTRIBUTE, $auth));
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(401);

        return Json::error($response, $message, 401)->withHeader('WWW-Authenticate', 'Bearer');
    }
}
