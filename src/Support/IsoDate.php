<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

// utilitário para interpretar datas no formato AAAA-MM-DD de forma estrita
final class IsoDate
{
    // devolve a data com horário zerado, ou null quando o valor é inválido
    public static function parse(string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $info = DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        // createFromFormat aceita overflow (ex.: mês 13); os avisos denunciam isso
        if ($info !== false && ($info['warning_count'] > 0 || $info['error_count'] > 0)) {
            return null;
        }

        return $date;
    }
}
