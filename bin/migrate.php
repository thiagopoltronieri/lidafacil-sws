<?php

declare(strict_types=1);

use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;
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

$pdo = Connection::create($db);
(new Migrator())->migrate($pdo, (string) $settings['schema_file']);

fwrite(STDOUT, "schema aplicado com sucesso\n");
