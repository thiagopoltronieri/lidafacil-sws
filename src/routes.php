<?php

declare(strict_types=1);

use App\Infrastructure\Http\Controller\Api\AnimalApiController;
use App\Infrastructure\Http\Controller\Api\AuthApiController;
use App\Infrastructure\Http\Controller\Api\UserApiController;
use App\Infrastructure\Http\Controller\Api\VaccinationApiController;
use App\Infrastructure\Http\Controller\HealthController;
use App\Infrastructure\Http\Middleware\JwtAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return static function (App $app): void {
    // healthcheck público usado pelo container
    $app->get('/health', HealthController::class);

    // autenticação pública: login, renovação e logout de token
    $app->group('/api/v1/auth', function (RouteCollectorProxy $group): void {
        $group->post('/login', [AuthApiController::class, 'login']);
        $group->post('/refresh', [AuthApiController::class, 'refresh']);
        $group->post('/logout', [AuthApiController::class, 'logout']);
    });

    // api protegida: todo recurso abaixo exige um token jwt válido; deny-by-default
    $app->group('/api/v1', function (RouteCollectorProxy $group): void {
        // 'me' resolve o próprio usuário do token; fica antes de {id} por clareza; o id só casa dígitos
        $group->get('/users/me', [UserApiController::class, 'me']);
        $group->get('/users', [UserApiController::class, 'index']);
        $group->post('/users', [UserApiController::class, 'store']);
        $group->get('/users/{id:[0-9]+}', [UserApiController::class, 'show']);
        $group->put('/users/{id:[0-9]+}', [UserApiController::class, 'update']);
        $group->delete('/users/{id:[0-9]+}', [UserApiController::class, 'destroy']);

        $group->get('/animals', [AnimalApiController::class, 'index']);
        $group->post('/animals', [AnimalApiController::class, 'store']);
        $group->get('/animals/{id:[0-9]+}', [AnimalApiController::class, 'show']);
        $group->put('/animals/{id:[0-9]+}', [AnimalApiController::class, 'update']);
        $group->delete('/animals/{id:[0-9]+}', [AnimalApiController::class, 'destroy']);

        $group->get('/animals/{id:[0-9]+}/vaccinations', [VaccinationApiController::class, 'index']);
        $group->post('/animals/{id:[0-9]+}/vaccinations', [VaccinationApiController::class, 'store']);
        $group->delete(
            '/animals/{id:[0-9]+}/vaccinations/{vaccinationId:[0-9]+}',
            [VaccinationApiController::class, 'destroy'],
        );
    })->add(JwtAuthMiddleware::class);
};
