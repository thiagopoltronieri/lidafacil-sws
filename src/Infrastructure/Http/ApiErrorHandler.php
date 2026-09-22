<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Throwable;

// handler central de erros da api: devolve json genérico ao cliente e registra o detalhe só no log do servidor
final class ApiErrorHandler
{
    public function __construct(private readonly ResponseFactoryInterface $responseFactory)
    {
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        [$status, $message] = $this->classify($exception);

        // erros de servidor (5xx) vão para o log sem a mensagem da exceção nem o corpo, para não vazar pii (ex.: e-mail em erro de unicidade)
        if ($status >= 500) {
            error_log(sprintf(
                '[api] %s %s -> %s',
                $request->getMethod(),
                $request->getUri()->getPath(),
                $exception::class,
            ));
        }

        $response = $this->responseFactory->createResponse($status);

        return Json::error($response, $message, $status);
    }

    /**
     * @return array{int,string}
     */
    private function classify(Throwable $exception): array
    {
        if ($exception instanceof HttpException) {
            $status = $exception->getCode();
            if ($status < 400 || $status > 599) {
                $status = 500;
            }

            return [$status, $this->messageFor($status)];
        }

        return [500, $this->messageFor(500)];
    }

    private function messageFor(int $status): string
    {
        return match ($status) {
            400 => 'Requisição inválida.',
            401 => 'Autenticação necessária.',
            403 => 'Acesso negado.',
            404 => 'Recurso não encontrado.',
            405 => 'Método não permitido.',
            429 => 'Muitas requisições. Tente novamente em instantes.',
            default => 'Erro interno no servidor.',
        };
    }
}
