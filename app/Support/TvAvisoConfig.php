<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\User;

/**
 * Configuración de avisos WhatsApp por vencimiento de cuentas TV.
 * Persistida en facturacion_parametros (misma mecánica que facturación).
 */
class TvAvisoConfig
{
    public static function enabled(): bool
    {
        return (bool) (int) FacturacionParametro::obtener('tv_aviso_enabled', 0);
    }

    public static function diasAntes(): int
    {
        return max(0, min(60, (int) FacturacionParametro::obtener('tv_aviso_dias_antes', 7)));
    }

    /** Hora HH:MM (24h). */
    public static function hora(): string
    {
        $hora = trim((string) FacturacionParametro::obtener('tv_aviso_hora', '09:00'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '09:00';
        }
        [$h, $m] = array_map('intval', explode(':', $hora));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return '09:00';
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    /** @return list<int> */
    public static function usuarioIds(): array
    {
        $raw = FacturacionParametro::obtener('tv_aviso_usuario_ids', '[]');
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
    public static function guardar(bool $enabled, int $diasAntes, string $hora, array $usuarioIds): void
    {
        $diasAntes = max(0, min(60, $diasAntes));
        $hora = self::normalizarHora($hora);
        $ids = array_values(array_unique(array_filter(array_map('intval', $usuarioIds))));

        FacturacionParametro::establecer('tv_aviso_enabled', $enabled ? '1' : '0', 'Avisos WhatsApp vencimiento TV');
        FacturacionParametro::establecer('tv_aviso_dias_antes', (string) $diasAntes, 'Días anticipación aviso TV');
        FacturacionParametro::establecer('tv_aviso_hora', $hora, 'Hora diaria aviso TV');
        FacturacionParametro::establecer('tv_aviso_usuario_ids', json_encode($ids), 'Usuarios staff aviso TV');
    }

    private static function normalizarHora(string $hora): string
    {
        $hora = trim($hora);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '09:00';
        }
        [$h, $m] = array_map('intval', explode(':', $hora));
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return '09:00';
        }

        return sprintf('%02d:%02d', $h, $m);
    }
}
