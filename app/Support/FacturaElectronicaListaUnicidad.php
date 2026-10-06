<?php

namespace App\Support;

final class FacturaElectronicaListaUnicidad
{
    /**
     * @param  list<int|string>  $ids
     * @param  array<int, string>  $ocupados  cliente_id => nombre de la otra lista
     * @return array{libres: list<int>, omitidos: array<int, string>}
     */
    public static function separar(array $ids, array $ocupados): array
    {
        $libres = [];
        $omitidos = [];
        $vistos = [];
        foreach ($ids as $raw) {
            $id = (int) $raw;
            if ($id <= 0 || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            if (isset($ocupados[$id])) {
                $omitidos[$id] = $ocupados[$id];

                continue;
            }
            $libres[] = $id;
        }

        return [
            'libres' => $libres,
            'omitidos' => $omitidos,
        ];
    }

    /**
     * @param  array<int, string>  $omitidos
     */
    public static function mensajeOmitidos(array $omitidos): string
    {
        if ($omitidos === []) {
            return '';
        }
        $porLista = [];
        foreach ($omitidos as $nombre) {
            $porLista[$nombre] = ($porLista[$nombre] ?? 0) + 1;
        }
        $partes = [];
        foreach ($porLista as $nombre => $n) {
            $partes[] = $n.' en «'.$nombre.'»';
        }

        return 'Se omitieron '.count($omitidos).' ya listados ('.implode(', ', $partes).').';
    }
}
