<?php

namespace App\Support;

/**
 * Enlace público (HMAC) al PDF de facturas pendientes de un cliente.
 * Mismo patrón que el recibo: URL de plantilla Meta = {APP_URL}/pendientes-resumen/{{1}}
 * y el parámetro del botón es "{cliente_id}/{token}".
 */
class PendientesResumenPublico
{
    public static function token(int $clienteId): string
    {
        return substr(hash_hmac('sha256', 'pendientes-resumen|'.$clienteId, (string) config('app.key')), 0, 40);
    }

    public static function tokenValido(int $clienteId, string $token): bool
    {
        return $token !== '' && hash_equals(self::token($clienteId), $token);
    }

    public static function sufijo(int $clienteId): string
    {
        return $clienteId.'/'.self::token($clienteId);
    }

    public static function url(int $clienteId): string
    {
        return route('pendientes.resumen.publico', [
            'cliente' => $clienteId,
            'token' => self::token($clienteId),
        ]);
    }
}
