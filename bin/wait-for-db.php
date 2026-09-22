<?php

declare(strict_types=1);

use App\Infrastructure\Database\Connection;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

/** @var array<string,mixed> $settings */
$settings = require $root . '/config/settings.php';
/** @var array<string,mixed> $db */
$db = $settings['db'];

// uma única tentativa de conexão; o entrypoint repete em laço até o banco aceitar
try {
    $pdo = Connection::create($db);
    $pdo->query('SELECT 1');
    exit(0);
} catch (\Throwable $e) {
    // silencioso de proposito: o laço extenro decide quando desistir, sem poluir o log
    exit(1);
}
