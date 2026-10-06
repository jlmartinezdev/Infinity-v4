<?php

namespace App\Support;

use Carbon\Carbon;

final class FacturaElectronicaListaPeriodos
{
    /**
     * Mes facturado del DE: el marcador de período, o la fecha de emisión si no hay marcador.
     */
    public static function extraerYm(?string $observaciones, Carbon|string|null $fechaEmision): ?string
    {
        $obs = (string) $observaciones;
        if (preg_match('/Período facturación:\s*(\d{4}-\d{2})/u', $obs, $m) === 1) {
            return $m[1];
        }

        if ($fechaEmision instanceof Carbon) {
            return $fechaEmision->format('Y-m');
        }

        $fecha = trim((string) $fechaEmision);
        if ($fecha === '') {
            return null;
        }

        try {
            return Carbon::parse($fecha)->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function etiqueta(string $ym): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $ym)->startOfMonth()->locale('es')->isoFormat('MMMM YYYY');
        } catch (\Throwable) {
            return $ym;
        }
    }

    /**
     * @param  iterable<mixed>  $facturas  filas con cliente_id, id, observaciones, fecha_emision (más nuevas primero)
     * @return array<int, list<array{ym: string, label: string, factura_id: int}>>
     */
    public static function porCliente(iterable $facturas): array
    {
        $porCliente = [];
        foreach ($facturas as $f) {
            $cid = (int) self::valor($f, 'cliente_id');
            $id = (int) self::valor($f, 'id');
            $ym = self::extraerYm(
                self::valor($f, 'observaciones'),
                self::valor($f, 'fecha_emision'),
            );
            if ($cid <= 0 || $id <= 0 || $ym === null) {
                continue;
            }
            if (isset($porCliente[$cid][$ym])) {
                continue;
            }
            $porCliente[$cid][$ym] = [
                'ym' => $ym,
                'label' => self::etiqueta($ym),
                'factura_id' => $id,
            ];
        }

        $out = [];
        foreach ($porCliente as $cid => $meses) {
            krsort($meses);
            $out[(int) $cid] = array_values($meses);
        }

        return $out;
    }

    /**
     * @param  list<int>  $clienteIds
     * @param  array<int, list<array{ym: string, label: string, factura_id: int}>>  $porCliente
     * @return list<array{ym: string, label: string, clientes: int}>
     */
    public static function resumenLista(array $clienteIds, array $porCliente): array
    {
        $conteo = [];
        $labels = [];
        foreach ($clienteIds as $raw) {
            $cid = (int) $raw;
            foreach ($porCliente[$cid] ?? [] as $p) {
                $ym = (string) $p['ym'];
                $conteo[$ym] = ($conteo[$ym] ?? 0) + 1;
                $labels[$ym] = (string) $p['label'];
            }
        }
        krsort($conteo);

        $out = [];
        foreach ($conteo as $ym => $n) {
            $out[] = [
                'ym' => $ym,
                'label' => $labels[$ym],
                'clientes' => $n,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{ym: string, label: string, clientes: int}>  $resumen
     */
    public static function textoResumen(array $resumen, int $max = 3): string
    {
        if ($resumen === []) {
            return '';
        }
        $vistos = array_slice($resumen, 0, max(1, $max));
        $partes = array_map(fn ($r) => (string) $r['label'], $vistos);
        $txt = implode(', ', $partes);
        $extra = count($resumen) - count($vistos);
        if ($extra > 0) {
            $txt .= ' y '.$extra.' más';
        }

        return $txt;
    }

    private static function valor(mixed $fila, string $clave): mixed
    {
        if (is_array($fila)) {
            return $fila[$clave] ?? null;
        }
        if (is_object($fila)) {
            return $fila->{$clave} ?? null;
        }

        return null;
    }
}
