<?php

namespace App\Support;

/**
 * Duraciones de MikroTik (19m17s, 1h2m, 45s) y el tiempo que le queda a un trial.
 */
final class HotspotDuracion
{
    public static function segundos(string $valor): ?int
    {
        $valor = strtolower(trim($valor));
        if ($valor === '' || $valor === '—') {
            return null;
        }
        if (preg_match('/^\d+$/', $valor) === 1) {
            return (int) $valor;
        }
        if (preg_match('/^(?:(\d+)w)?(?:(\d+)d)?(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/', $valor, $m) === 1
            && preg_match('/\d+[wdhms]/', $valor) === 1) {
            return ((int) ($m[1] ?? 0)) * 604800
                + ((int) ($m[2] ?? 0)) * 86400
                + ((int) ($m[3] ?? 0)) * 3600
                + ((int) ($m[4] ?? 0)) * 60
                + ((int) ($m[5] ?? 0));
        }
        if (preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $valor, $m) === 1) {
            return ((int) ($m[1] ?? 0)) * 3600
                + ((int) $m[2]) * 60
                + (int) $m[3];
        }

        return null;
    }

    /**
     * Anillo de cuota: el arco es el tiempo ya usado y el centro es lo que queda.
     *
     * @return array{etiqueta: string, porcentaje: float, centro_num: string, centro_unit: string, tiene_cuota: bool}|null
     */
    public static function vistaRestante(string $transcurrido, string $restante): ?array
    {
        $queda = self::segundos($restante);
        if ($queda === null) {
            return null;
        }
        $usado = self::segundos($transcurrido) ?? 0;
        $total = $usado + $queda;
        $pct = $total > 0 ? min(100, max(0, ($usado / $total) * 100)) : 0;
        [$num, $unit, $etiqueta] = self::partes($queda);

        return [
            'etiqueta' => $etiqueta,
            'porcentaje' => $pct,
            'centro_num' => $num,
            'centro_unit' => $unit,
            'tiene_cuota' => true,
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function partes(int $queda): array
    {
        if ($queda <= 0) {
            return ['0', 's', 'se acabó'];
        }
        if ($queda < 60) {
            return [(string) $queda, 's', 'quedan '.$queda.' s'];
        }
        if ($queda < 3600) {
            $minutos = intdiv($queda, 60);
            $segundos = $queda % 60;
            $texto = $segundos > 0
                ? 'quedan '.$minutos.' min '.$segundos.' s'
                : 'quedan '.$minutos.' min';

            return [(string) $minutos, 'min', $texto];
        }
        $horas = intdiv($queda, 3600);
        $minutos = intdiv($queda % 3600, 60);
        $texto = $minutos > 0
            ? 'quedan '.$horas.' h '.$minutos.' min'
            : 'quedan '.$horas.' h';

        return [(string) $horas, 'h', $texto];
    }
}
