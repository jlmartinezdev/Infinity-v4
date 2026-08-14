<?php

namespace App\Support;

use App\Models\FacturacionParametro;

class BackupScheduleConfig
{
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

    public static function horaFromInput(string $hora): string
    {
        $hora = trim($hora);
        if (! preg_match('/^\d{1,2}:\d{2}$/', $hora)) {
            return '02:30';
        }

        return ScheduleOnceAfter::normalizeHora($hora);
    }
}
