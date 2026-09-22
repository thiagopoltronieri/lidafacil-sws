<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

// abstração de relógio: permite fixar o "agora" nos testes e evitar dependência do horário real do sistema nas regras de validação
interface Clock
{
    public function now(): DateTimeImmutable;
}
