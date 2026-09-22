<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Psr\Http\Message\ResponseInterface;

// utilitário para escrever respostas json de forma consistente na api
final class Json
{
    public static function write(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write((string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }

    /**
     * envelope de erro padrão; details leva os erros por campo numa validação (422)
     *
     * @param array<string,string> $details
     */
    public static function error(ResponseInterface $response, string $message, int $status, array $details = []): ResponseInterface
    {
        $error = ['message' => $message, 'status' => $status];
        if ($details !== []) {
            $error['fields'] = $details;
        }

        return self::write($response, ['error' => $error], $status);
    }
}
