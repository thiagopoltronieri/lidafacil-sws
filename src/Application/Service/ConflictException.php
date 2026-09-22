<?php

declare(strict_types=1);

namespace App\Application\Service;

use RuntimeException;

// conflito com o estado atual do recurso, por exemplo e-mail ou brinco já em uso; vira um 409 na api
final class ConflictException extends RuntimeException
{
}
