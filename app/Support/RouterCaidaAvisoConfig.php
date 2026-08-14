<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\User;

/**
 * Avisos WhatsApp por caída de router (ping sin respuesta).
 * Persistido en facturacion_parametros.
 */
class RouterCaidaAvisoConfig
{
    public static function enabled(): bool
    {
        return (bool) (int) FacturacionParametro::obtener('router_caida_aviso_enabled', 0);
    }

    /** Fallos consecutivos de ping antes de alertar (default 3). */
    public static function confirmaciones(): int
    {
        return max(1, min(20, (int) FacturacionParametro::obtener('router_caida_aviso_confirmaciones', 3)));
    }

    /** @return list<int> */
    public static function usuarioIds(): array
    {
        $raw = FacturacionParametro::obtener('router_caida_aviso_usuario_ids', '[]');
        if (is_array($raw)) {
            return array_values(array_unique(array_map('intval', $raw)));
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $decoded)));
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function destinatarios()
    {
        $ids = self::usuarioIds();
        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->staff()
            ->activos()
            ->whereIn('usuario_id', $ids)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  list<int|string>  $usuarioIds
     */
    public static function guardar(bool $enabled, int $confirmaciones, array $usuarioIds): void
    {
        $confirmaciones = max(1, min(20, $confirmaciones));
        $ids = array_values(array_unique(array_filter(array_map('intval', $usuarioIds))));

        FacturacionParametro::establecer('router_caida_aviso_enabled', $enabled ? '1' : '0', 'Avisos WhatsApp caída router');
        FacturacionParametro::establecer('router_caida_aviso_confirmaciones', (string) $confirmaciones, 'Pings fallidos seguidos antes de alertar');
        FacturacionParametro::establecer('router_caida_aviso_usuario_ids', json_encode($ids), 'Usuarios staff aviso caída router');
    }
}
