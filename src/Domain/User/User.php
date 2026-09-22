<?php

declare(strict_types=1);

namespace App\Domain\User;

use JsonSerializable;

// usuário do sistema autenticado por e-mail e senha, com perfil de acesso do rbac
final readonly class User implements JsonSerializable
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public Role $role,
        public string $passwordHash,
        public string $createdAt,
    ) {
    }

    /**
     * representação pública do usuário, sem o hash da senha, para respostas da api
     *
     * @return array{id:int,name:string,email:string,role:string,created_at:string}
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role->value,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * garante que serializar o objeto (json_encode) nunca vaze o hash da senha
     *
     * @return array{id:int,name:string,email:string,role:string,created_at:string}
     */
    public function jsonSerialize(): array
    {
        return $this->toPublicArray();
    }
}
