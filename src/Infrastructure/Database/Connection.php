<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use InvalidArgumentException;
use PDO;
use RuntimeException;

// fábrica de conexão PDO; sqlite nos testes e mysql na aplicação (não gosto de Postgres)
final class Connection
{
    /**
     * @param array<string,mixed> $settings
     */
    public static function create(array $settings): PDO
    {
        $driver = (string) ($settings['driver'] ?? 'sqlite');

        return match ($driver) {
            'sqlite' => self::sqlite($settings),
            'mysql' => self::mysql($settings),
            default => throw new InvalidArgumentException('Driver de banco não suportado: ' . $driver),
        };
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function sqlite(array $settings): PDO
    {
        $path = (string) ($settings['path'] ?? ':memory:');

        if ($path !== ':memory:') {
            $dir = dirname($path);
            if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                throw new RuntimeException('Não foi possível criar o diretório do banco: ' . $dir);
            }
        }

        $pdo = new PDO('sqlite:' . $path, null, null, self::options());
        // integridade referencial no sqlite precisa ser habilitada por conexão
        $pdo->exec('PRAGMA foreign_keys = ON');

        return $pdo;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private static function mysql(array $settings): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            (string) ($settings['host'] ?? '127.0.0.1'),
            (int) ($settings['port'] ?? 3306),
            (string) ($settings['name'] ?? ''),
        );

        return new PDO(
            $dsn,
            (string) ($settings['user'] ?? ''),
            (string) ($settings['password'] ?? ''),
            self::options(),
        );
    }

    /**
     * @return array<int,mixed>
     */
    private static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
