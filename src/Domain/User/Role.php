<?php

declare(strict_types=1);

namespace App\Domain\User;

// perfis de acesso do rbac: administrador, operador e cliente
enum Role: string
{
    case ADMIN = 'admin';
    case OPERATOR = 'operator';
    case CLIENT = 'client';

    // rótulo em pt-BR para exibição na interface
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrador',
            self::OPERATOR => 'Operador',
            self::CLIENT => 'Cliente',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
