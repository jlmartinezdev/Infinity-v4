<?php

namespace App\Support;

use DateTimeInterface;

/**
 * Distancia, filtro de puntos y resumen de un recorrido GPS.
 * Sin acceso a base: el cierre nocturno y los tests usan estas mismas cuentas.
 */
class StaffRecorridoGeometria
{
    public const MIN_METROS = 30.0;

    public const MAX_ACCURACY_M = 100.0;

    /** Un salto mayor se trata como error de GPS y no suma kilómetros. */
    public const MAX_SEGMENTO_M = 80000.0;

    /**
     * @param  array{lat: float, lng: float}|null  $ultimo
     */
    public static function debeGuardar(?array $ultimo, float $lat, float $lng, ?float $accuracy): bool
    {
        if ($accuracy !== null && $accuracy > self::MAX_ACCURACY_M) {
            return false;
        }
        if (abs($lat) < 0.01 && abs($lng) < 0.01) {
            return false;
        }
        if ($ultimo === null) {
            return true;
        }

        return self::distanciaMetros((float) $ultimo['lat'], (float) $ultimo['lng'], $lat, $lng) >= self::MIN_METROS;
    }

    public static function distanciaMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radio = 6371000.0;
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dp = deg2rad($lat2 - $lat1);
        $dl = deg2rad($lng2 - $lng1);
        $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;

        return 2 * $radio * asin(min(1.0, sqrt($a)));
    }

    /**
     * @param  list<array{lat: float, lng: float, at?: mixed, visita_id?: int|null}>  $puntos
     * @return array{
     *     distancia_m: float,
     *     puntos_n: int,
     *     visitas: int,
     *     inicio: string|null,
     *     fin: string|null,
     *     lat_inicio: float,
     *     lng_inicio: float,
     *     lat_fin: float,
     *     lng_fin: float,
     *     traza: list<array{lat: float, lng: float}>
     * }|null
     */
    public static function armarResumen(array $puntos, bool $simplificar = true): ?array
    {
        if ($puntos === []) {
            return null;
        }

        $ordenados = array_values($puntos);
        usort($ordenados, function (array $a, array $b): int {
            return strcmp(self::aIso($a['at'] ?? ''), self::aIso($b['at'] ?? ''));
        });

        $distancia = 0.0;
        $visitas = [];
        $prev = null;
        $traza = [];
        foreach ($ordenados as $punto) {
            $lat = round((float) $punto['lat'], 6);
            $lng = round((float) $punto['lng'], 6);
            $traza[] = ['lat' => $lat, 'lng' => $lng];
            $visita = $punto['visita_id'] ?? null;
            if ($visita !== null && (int) $visita > 0) {
                $visitas[(int) $visita] = true;
            }
            if ($prev !== null) {
                $salto = self::distanciaMetros($prev['lat'], $prev['lng'], $lat, $lng);
                if ($salto <= self::MAX_SEGMENTO_M) {
                    $distancia += $salto;
                }
            }
            $prev = ['lat' => $lat, 'lng' => $lng];
        }

        $primero = $ordenados[0];
        $ultimo = $ordenados[count($ordenados) - 1];
        $trazaFinal = $simplificar ? self::simplificar($traza) : $traza;

        return [
            'distancia_m' => round($distancia, 1),
            'puntos_n' => count($ordenados),
            'visitas' => count($visitas),
            'inicio' => self::aIso($primero['at'] ?? null),
            'fin' => self::aIso($ultimo['at'] ?? null),
            'lat_inicio' => $traza[0]['lat'],
            'lng_inicio' => $traza[0]['lng'],
            'lat_fin' => $traza[count($traza) - 1]['lat'],
            'lng_fin' => $traza[count($traza) - 1]['lng'],
            'traza' => $trazaFinal,
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $puntos
     * @return list<array{lat: float, lng: float}>
     */
    public static function simplificar(array $puntos, float $epsilonMetros = 40.0, int $maxPuntos = 80): array
    {
        $n = count($puntos);
        if ($n <= 2) {
            return array_values($puntos);
        }

        $epsilon = max(1.0, $epsilonMetros);
        $out = self::douglasPeucker($puntos, $epsilon);
        while (count($out) > $maxPuntos && $epsilon < 5000) {
            $epsilon *= 1.7;
            $out = self::douglasPeucker($puntos, $epsilon);
        }

        return $out;
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $puntos
     * @return list<array{lat: float, lng: float}>
     */
    private static function douglasPeucker(array $puntos, float $epsilon): array
    {
        $n = count($puntos);
        $keep = array_fill(0, $n, false);
        $keep[0] = true;
        $keep[$n - 1] = true;
        $stack = [[0, $n - 1]];

        while ($stack !== []) {
            [$desde, $hasta] = array_pop($stack);
            if ($hasta <= $desde + 1) {
                continue;
            }
            $maxDist = -1.0;
            $idx = -1;
            for ($i = $desde + 1; $i < $hasta; $i++) {
                $d = self::distanciaASegmento($puntos[$i], $puntos[$desde], $puntos[$hasta]);
                if ($d > $maxDist) {
                    $maxDist = $d;
                    $idx = $i;
                }
            }
            if ($idx !== -1 && $maxDist > $epsilon) {
                $keep[$idx] = true;
                $stack[] = [$desde, $idx];
                $stack[] = [$idx, $hasta];
            }
        }

        $out = [];
        foreach ($keep as $i => $on) {
            if ($on) {
                $out[] = $puntos[$i];
            }
        }

        return $out;
    }

    /**
     * @param  array{lat: float, lng: float}  $punto
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private static function distanciaASegmento(array $punto, array $a, array $b): float
    {
        $lat0 = deg2rad((float) $a['lat']);
        $aMetros = static function (array $q) use ($a, $lat0): array {
            $x = deg2rad((float) $q['lng'] - (float) $a['lng']) * cos($lat0) * 6371000.0;
            $y = deg2rad((float) $q['lat'] - (float) $a['lat']) * 6371000.0;

            return [$x, $y];
        };
        [$px, $py] = $aMetros($punto);
        [$bx, $by] = $aMetros($b);
        $len2 = ($bx * $bx) + ($by * $by);
        if ($len2 < 1) {
            return hypot($px, $py);
        }
        $t = max(0.0, min(1.0, (($px * $bx) + ($py * $by)) / $len2));

        return hypot($px - ($t * $bx), $py - ($t * $by));
    }

    private static function aIso(mixed $at): ?string
    {
        if ($at instanceof DateTimeInterface) {
            return $at->format(DateTimeInterface::ATOM);
        }
        $texto = trim((string) $at);

        return $texto !== '' ? $texto : null;
    }
}
