<?php

namespace App\Support;

use Carbon\CarbonInterface;

class MonitoreoDuracion
{
    public static function segundosDesde(?CarbonInterface $desde, ?CarbonInterface $ahora = null): ?int
    {
        if (! $desde) {
            return null;
        }
        $ahora = $ahora ?? now();

        return max(0, $ahora->getTimestamp() - $desde->getTimestamp());
    }

    public static function formatear(?CarbonInterface $desde, ?CarbonInterface $ahora = null): ?string
    {
        $segundos = self::segundosDesde($desde, $ahora);
        if ($segundos === null) {
            return null;
        }

        return self::formatearSegundos($segundos);
    }

    public static function formatearSegundos(int $segundos): string
    {
        if ($segundos < 60) {
            return '< 1 min';
        }

        $dias = intdiv($segundos, 86400);
        $horas = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        $partes = [];
        if ($dias > 0) {
            $partes[] = $dias.'d';
        }
        if ($horas > 0 || $dias > 0) {
            $partes[] = $horas.'h';
        }
        $partes[] = $minutos.'m';

        return implode(' ', $partes);
    }
}
