<?php

declare(strict_types=1);

use App\Kernel;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);

// em dev lemos o .env; em container/produção as variáveis já vêm do ambiente
if (is_file($root . '/.env')) {
    Dotenv::createImmutable($root)->safeLoad();
}

/** @var array<string,mixed> $settings */
$settings = require $root . '/config/settings.php';

Kernel::create($settings)->run();
