<?php

namespace App\Support;

use App\Models\FacturacionParametro;

class BackupScheduleConfig
{
    public const TIPO_ESENCIAL = 'esencial';

    public const TIPO_COMPLETO = 'completo';

    public static function hora(): string
    {
        $hora = trim((string) FacturacionParametro::obtener('backup_drive_hora', '02:30'));
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '02:30';
        }

        return ScheduleOnceAfter::normalizeHora($hora);
    }

    public static function guardarHora(string $hora): void
    {
        FacturacionParametro::establecer(
            'backup_drive_hora',
            self::horaFromInput($hora),
            'Hora diaria backup Google Drive'
        );
    }

    public static function tipo(): string
    {
        return self::normalizarTipo((string) FacturacionParametro::obtener('backup_drive_tipo', self::TIPO_ESENCIAL));
    }

    public static function guardarTipo(string $tipo): void
    {
        FacturacionParametro::establecer(
            'backup_drive_tipo',
            self::normalizarTipo($tipo),
            'Tipo diario backup Google Drive (esencial o completo)'
        );
    }

    public static function normalizarTipo(string $tipo): string
    {
        return strtolower(trim($tipo)) === self::TIPO_COMPLETO
            ? self::TIPO_COMPLETO
            : self::TIPO_ESENCIAL;
    }

    public static function horaFromInput(string $hora): string
    {
        $hora = trim($hora);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '02:30';
        }

        return ScheduleOnceAfter::normalizeHora($hora);
    }
}
