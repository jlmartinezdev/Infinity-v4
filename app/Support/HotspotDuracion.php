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
     * Tiempo en línea para leer de un vistazo: "30 min 47 s", "2 h 4 min", "1 d 3 h".
     */
    public static function legible(string $valor): string
    {
        $s = self::segundos($valor);
        if ($s === null) {
            return '—';
        }
        if ($s < 60) {
            return $s.' s';
        }
        if ($s < 3600) {
            $resto = $s % 60;

            return intdiv($s, 60).' min'.($resto > 0 ? ' '.$resto.' s' : '');
        }
        if ($s < 86400) {
            $resto = intdiv($s % 3600, 60);

            return intdiv($s, 3600).' h'.($resto > 0 ? ' '.$resto.' min' : '');
        }
        $resto = intdiv($s % 86400, 3600);

        return intdiv($s, 86400).' d'.($resto > 0 ? ' '.$resto.' h' : '');
    }

    /**
     * Antigüedad en una sola unidad: "recién", "hace 12 min", "hace 3 h", "hace 2 d".
     */
    public static function haceCuanto(\DateTimeInterface $cuando, ?\DateTimeInterface $ahora = null): string
    {
        $s = max(0, ($ahora ?? now())->getTimestamp() - $cuando->getTimestamp());
        if ($s < 60) {
            return 'recién';
        }
        if ($s < 3600) {
            return 'hace '.intdiv($s, 60).' min';
        }
        if ($s < 86400) {
            return 'hace '.intdiv($s, 3600).' h';
        }

        return 'hace '.intdiv($s, 86400).' d';
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
