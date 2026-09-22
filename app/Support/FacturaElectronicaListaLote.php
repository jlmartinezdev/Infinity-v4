<?php

namespace App\Support;

class FacturaElectronicaListaLote
{
    public const MAX = 30;

    /**
     * Arma el siguiente lote de una lista, omitiendo clientes ya emitidos en el período.
     *
     * @param  list<int|string>  $clienteIds
     * @param  list<int|string>  $yaEmitidos
     * @return array{lote: list<int>, pendientes: int, restantes: int}
     */
    public static function preparar(array $clienteIds, array $yaEmitidos = [], int $max = self::MAX): array
    {
        $vistos = [];
        $orden = [];
        foreach ($clienteIds as $raw) {
            $id = (int) $raw;
            if ($id <= 0 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $orden[] = $id;
        }

        $emitidos = [];
        foreach ($yaEmitidos as $raw) {
            $id = (int) $raw;
            if ($id > 0) {
                $emitidos[$id] = true;
            }
        }

        $pendientes = [];
        foreach ($orden as $id) {
            if (! isset($emitidos[$id])) {
                $pendientes[] = $id;
            }
        }

        $tope = max(1, $max);
        $lote = array_slice($pendientes, 0, $tope);

        return [
            'lote' => $lote,
            'pendientes' => count($pendientes),
            'restantes' => max(0, count($pendientes) - count($lote)),
        ];
    }
}
