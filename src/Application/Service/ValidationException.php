<?php

declare(strict_types=1);

namespace App\Application\Service;

use RuntimeException;

// carrega os erros de validação por campo para o controller devolver um 422 em json
final class ValidationException extends RuntimeException
{
    /**
     * @param array<string,string> $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('erro de validação');
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
