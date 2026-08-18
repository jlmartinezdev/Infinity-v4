<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\User;

/**
 * Avisos WhatsApp por caída de AP wireless (ping sin respuesta).
 * Si aún no se guardó config propia, reutiliza destinatarios de caída de router.
 */
class ApWirelessCaidaAvisoConfig
{
    public static function enabled(): bool
    {
        $propio = FacturacionParametro::obtener('ap_wireless_caida_aviso_enabled', null);
        if ($propio === null || $propio === '') {
            return RouterCaidaAvisoConfig::enabled();
        }

        return (bool) (int) $propio;
    }

    public static function confirmaciones(): int
    {
        $propio = FacturacionParametro::obtener('ap_wireless_caida_aviso_confirmaciones', null);
        if ($propio === null || $propio === '') {
            return RouterCaidaAvisoConfig::confirmaciones();
        }

        return max(1, min(20, (int) $propio));
    }

    /** @return list<int> */
    public static function usuarioIds(): array
    {
        $raw = FacturacionParametro::obtener('ap_wireless_caida_aviso_usuario_ids', null);
        if ($raw === null || $raw === '') {
            return RouterCaidaAvisoConfig::usuarioIds();
        }

        if (is_array($raw)) {
            return array_values(array_unique(array_map('intval', $raw)));
        }

        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return RouterCaidaAvisoConfig::usuarioIds();
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

        FacturacionParametro::establecer('ap_wireless_caida_aviso_enabled', $enabled ? '1' : '0', 'Avisos WhatsApp caída AP wireless');
        FacturacionParametro::establecer('ap_wireless_caida_aviso_confirmaciones', (string) $confirmaciones, 'Pings fallidos seguidos antes de alertar AP');
        FacturacionParametro::establecer('ap_wireless_caida_aviso_usuario_ids', json_encode($ids), 'Usuarios staff aviso caída AP wireless');
    }
}
