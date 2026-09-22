<?php

declare(strict_types=1);

use App\Application\Security\PasswordHasher;
use App\Domain\User\Role;
use App\Infrastructure\Database\Connection;
use App\Infrastructure\Database\Migrator;
use App\Infrastructure\Repository\UserRepository;
use App\Support\SystemClock;
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
// garante as tabelas antes de semear
(new Migrator())->migrate($pdo, (string) $settings['schema_file']);

$users = new UserRepository($pdo, new SystemClock());
$hasher = new PasswordHasher();

/** @var array{users:list<array{name:string,email:string,password:string,role:string}>} $seed */
$seed = $settings['seed'];

$created = 0;
foreach ($seed['users'] as $spec) {
    // só semeia quem tem senha definida no ambiente; idempotente por e-mail
    if ($spec['password'] === '' || $users->existsByEmail($spec['email'])) {
        continue;
    }

    $users->create($spec['name'], $spec['email'], $hasher->hash($spec['password']), Role::from($spec['role']));
    fwrite(STDOUT, "usuário semeado: {$spec['email']} ({$spec['role']})\n");
    $created++;
}

if ($created === 0) {
    fwrite(STDOUT, "nenhum usuário novo para semear, sem senha definida ou já existentes\n");
}
