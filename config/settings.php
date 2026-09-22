<?php

declare(strict_types=1);

// configuração da aplicação montada a partir de variáveis de ambiente
$root = dirname(__DIR__);

$env = static function (string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : $default;
};

// fixa o fuso para as regras de data e os timestamps não dependerem do fuso padrão do container
date_default_timezone_set($env('APP_TZ', 'America/Sao_Paulo'));

$dbDriver = $env('DB_DRIVER', 'sqlite');

$dbPath = $env('DB_PATH', 'var/data/lidafacil.sqlite');
// caminho relativo do sqlite vira absoluto a partir da raiz do projeto
if ($dbPath !== ':memory:' && !str_starts_with($dbPath, '/')) {
    $dbPath = $root . '/' . $dbPath;
}

// cada driver tem o seu dialeto de schema; os testes usam o de sqlite direto
$schemaFile = $dbDriver === 'mysql'
    ? $root . '/database/schema.mysql.sql'
    : $root . '/database/schema.sql';

// origens liberadas no cors, separadas por vírgula; vazio significa só mesma origem e sem cabeçalhos cors
$corsRaw = $env('CORS_ALLOWED_ORIGINS', '');
$allowedOrigins = array_values(array_filter(
    array_map('trim', explode(',', $corsRaw)),
    static fn (string $origin): bool => $origin !== '',
));

return [
    'app_env' => $env('APP_ENV', 'prod'),
    'debug' => filter_var($env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'schema_file' => $schemaFile,
    'db' => [
        'driver' => $dbDriver,
        'path' => $dbPath,
        'host' => $env('DB_HOST', '127.0.0.1'),
        'port' => (int) $env('DB_PORT', '3306'),
        'name' => $env('DB_NAME', ''),
        'user' => $env('DB_USER', ''),
        'password' => $env('DB_PASSWORD', ''),
    ],
    'jwt' => [
        // segredo forte só no ambiente; sem default para não assinar token com chave conhecida
        'secret' => $env('JWT_SECRET', ''),
        'issuer' => $env('JWT_ISSUER', 'lidafacil-sws'),
        // access token curto de 15 min e refresh token de 7 dias por padrão
        'access_ttl' => (int) $env('JWT_ACCESS_TTL', '900'),
        'refresh_ttl' => (int) $env('JWT_REFRESH_TTL', '604800'),
    ],
    'cors' => [
        'allowed_origins' => $allowedOrigins,
    ],
    // usuários semeados no primeiro start, um por perfil; só entram os que tiverem senha definida no ambiente
    'seed' => [
        'users' => [
            [
                'name' => $env('SEED_ADMIN_NAME', 'Administrador Demonstração'),
                'email' => $env('SEED_ADMIN_EMAIL', 'admin@lidafacil.local'),
                'password' => $env('SEED_ADMIN_PASSWORD', ''),
                'role' => 'admin',
            ],
            [
                'name' => $env('SEED_OPERATOR_NAME', 'Operador Demonstração'),
                'email' => $env('SEED_OPERATOR_EMAIL', 'operator@lidafacil.local'),
                'password' => $env('SEED_OPERATOR_PASSWORD', ''),
                'role' => 'operator',
            ],
            [
                'name' => $env('SEED_CLIENT_NAME', 'Cliente Demonstração'),
                'email' => $env('SEED_CLIENT_EMAIL', 'client@lidafacil.local'),
                'password' => $env('SEED_CLIENT_PASSWORD', ''),
                'role' => 'client',
            ],
        ],
    ],
];
