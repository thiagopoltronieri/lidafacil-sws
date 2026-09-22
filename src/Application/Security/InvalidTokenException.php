<?php

declare(strict_types=1);

namespace App\Application\Security;

use RuntimeException;

// lançada quando um token jwt é ausente, malformado, expirado, com assinatura inválida ou com claims inesperadas
final class InvalidTokenException extends RuntimeException
{
}
