<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ApWirelessCaidaVentana
{
    /**
     * Recorta los episodios de caída a las últimas 24 horas.
     *
     * @param  iterable<int, array{inicio: CarbonInterface, fin: ?CarbonInterface}>  $filas
     * @return list<array{inicio: string, fin: string, abierta: bool}>
     */
    public static function recortar(iterable $filas, CarbonInterface $ahora, ?CarbonInterface $caidoDesde, bool $sigueCaido): array
    {
        $desde = $ahora->copy()->subDay();
        $salida = [];

        foreach ($filas as $fila) {
            $abierta = ($fila['fin'] ?? null) === null;
            $inicio = $fila['inicio'];
            $fin = $fila['fin'] ?? $ahora;
            if ($fin->lessThanOrEqualTo($desde) || $inicio->greaterThanOrEqualTo($ahora)) {
                continue;
            }
            if ($inicio->lessThan($desde)) {
                $inicio = $desde->copy();
            }
            if ($fin->greaterThan($ahora)) {
                $fin = $ahora->copy();
            }
            if ($fin->lessThanOrEqualTo($inicio)) {
                continue;
            }
            $salida[] = [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $fin->toIso8601String(),
                'abierta' => $abierta,
            ];
        }

        if ($salida === [] && $sigueCaido && $caidoDesde !== null && $caidoDesde->lessThan($ahora)) {
            $inicio = $caidoDesde->lessThan($desde) ? $desde->copy() : $caidoDesde;
            $salida[] = [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $ahora->toIso8601String(),
                'abierta' => true,
            ];
        }

        return $salida;
    }
}
