<?php

namespace App\Support;

final class TicketNotaLista
{
    public static function paraLista(?string $texto): string
    {
        return self::plano($texto);
    }

    public static function paraDetalle(?string $texto): ?string
    {
        if ($texto === null || trim($texto) === '') {
            return $texto;
        }

        return $texto;
    }

    private static function plano(?string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $texto)));
    }
}
