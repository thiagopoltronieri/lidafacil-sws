<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use PDO;
use RuntimeException;

// aplica o schema lendo o arquivo .sql e executando comando a comando
final class Migrator
{
    public function migrate(PDO $pdo, string $schemaFile): void
    {
        $sql = @file_get_contents($schemaFile);
        if ($sql === false) {
            throw new RuntimeException('Não foi possível ler o schema em ' . $schemaFile);
        }

        foreach ($this->statements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /**
     * @return list<string>
     */
    private function statements(string $sql): array
    {
        // remove as linhas de comentário para a divisão por ';' não se confundir
        $lines = preg_split('/\r?\n/', $sql) ?: [];
        $withoutComments = array_filter(
            $lines,
            static fn (string $line): bool => !str_starts_with(trim($line), '--'),
        );

        $parts = array_map('trim', explode(';', implode("\n", $withoutComments)));

        return array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
