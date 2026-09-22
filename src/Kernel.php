<?php

declare(strict_types=1);

namespace App;

use App\Application\Security\JwtService;
use App\Application\Security\RefreshTokenService;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Http\ApiErrorHandler;
use App\Infrastructure\Http\Middleware\CorsMiddleware;
use App\Infrastructure\Http\Middleware\SecurityHeadersMiddleware;
use App\Infrastructure\Repository\RefreshTokenRepository;
use App\Support\Clock;
use App\Support\SystemClock;
use DI\Container;
use PDO;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ResponseFactory;

// monta o container, registra serviços e devolve a aplicação Slim pronta, api rest stateless
final class Kernel
{
    /**
     * @param array<string,mixed> $settings
     */
    public static function create(array $settings, ?PDO $pdo = null): App
    {
        $container = self::buildContainer($settings, $pdo);

        AppFactory::setContainer($container);
        $app = AppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        $errorMiddleware = $app->addErrorMiddleware((bool) ($settings['debug'] ?? false), true, true);
        // erros saem sempre em json genérico, sem vazar detalhe ao cliente
        $errorMiddleware->setDefaultErrorHandler($container->get(ApiErrorHandler::class));

        // cors trata o preflight antes do roteamento; os cabeçalhos de segurança ficam por fora, valendo até nos erros
        $app->add($container->get(CorsMiddleware::class));
        $app->add($container->get(SecurityHeadersMiddleware::class));

        $routes = require __DIR__ . '/routes.php';
        if (is_callable($routes)) {
            $routes($app);
        }

        return $app;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function buildContainer(array $settings, ?PDO $pdo): Container
    {
        // autowiring do php-di resolve os repositórios, serviços e controllers; aqui ficam só os que exigem escalares
        $container = new Container();

        $container->set('settings', $settings);
        $container->set(Clock::class, static fn (): Clock => new SystemClock());
        $container->set(ResponseFactoryInterface::class, static fn (): ResponseFactoryInterface => new ResponseFactory());

        $container->set(PDO::class, static function () use ($pdo, $settings): PDO {
            if ($pdo instanceof PDO) {
                return $pdo;
            }
            /** @var array<string,mixed> $db */
            $db = $settings['db'] ?? [];

            return Connection::create($db);
        });

        /** @var array<string,mixed> $jwt */
        $jwt = $settings['jwt'] ?? [];

        $container->set(JwtService::class, static fn (Container $c): JwtService => new JwtService(
            (string) ($jwt['secret'] ?? ''),
            (string) ($jwt['issuer'] ?? 'lidafacil-sws'),
            (int) ($jwt['access_ttl'] ?? 900),
            $c->get(Clock::class),
        ));

        $container->set(RefreshTokenService::class, static fn (Container $c): RefreshTokenService => new RefreshTokenService(
            $c->get(RefreshTokenRepository::class),
            $c->get(Clock::class),
            (int) ($jwt['refresh_ttl'] ?? 604800),
        ));

        /** @var array<string,mixed> $cors */
        $cors = $settings['cors'] ?? [];
        /** @var list<string> $allowedOrigins */
        $allowedOrigins = $cors['allowed_origins'] ?? [];

        $container->set(CorsMiddleware::class, static fn (Container $c): CorsMiddleware => new CorsMiddleware(
            $allowedOrigins,
            $c->get(ResponseFactoryInterface::class),
        ));

        return $container;
    }
}
