<?php

namespace App\Services;

use App\Models\CobroResumen;
use App\Models\FacturaInterna;
use App\Support\CobrosMesVentana;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Informe de solo lectura. No escribe facturas, recibos ni cobros_resumen.
 * Los pagos se toman de cobro_factura_interna.monto, no del recibo entero.
 */
class AnalisisCobranzasCicloService
{
    public const DESDE_MINIMO = '2026-07-01';

    /**
     * @return array{
     *     desde: string,
     *     hasta: string,
     *     normales: list<array<string, mixed>>,
     *     especiales: list<array<string, mixed>>,
     *     consolidado: list<array<string, mixed>>,
     *     conciliacion: list<array<string, mixed>>,
     *     totales: array<string, float|int>
     * }
     */
    public function analizar(Carbon $desde, Carbon $hasta): array
    {
        $desde = $this->acotar($desde);
        $hasta = $this->acotar($hasta);
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        $ciclos = $this->ciclos($desde, $hasta);
        $normales = $this->normales($desde, $hasta, $ciclos);
        $especiales = $this->especiales($desde, $hasta, $ciclos);
        $favor = $this->saldoFavorPorMes($desde, $hasta);
        $consolidado = $this->consolidar($ciclos, $normales, $especiales, $favor);
        $conciliacion = $this->conciliar($ciclos, $normales);

        return [
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'normales' => array_values($normales),
            'especiales' => array_values($especiales),
            'consolidado' => array_values($consolidado),
            'conciliacion' => array_values($conciliacion),
            'totales' => $this->totales($consolidado),
        ];
    }

    public function ultimoCicloDisponible(): Carbon
    {
        $creada = DB::table('factura_internas')->max('created_at');
        $porFactura = $creada
            ? Carbon::parse($creada)->addMonthNoOverflow()->startOfMonth()
            : now()->startOfMonth();
        $actual = now()->startOfMonth();

        return $porFactura->greaterThan($actual) ? $porFactura : $actual;
    }

    public function acotar(Carbon $mes): Carbon
    {
        $mes = $mes->copy()->startOfMonth();
        $minimo = Carbon::parse(self::DESDE_MINIMO)->startOfMonth();
        $maximo = $this->ultimoCicloDisponible();
        if ($mes->lt($minimo)) {
            return $minimo;
        }
        if ($mes->gt($maximo)) {
            return $maximo;
        }

        return $mes;
    }

    /**
     * @return list<string> Y-m-01
     */
    private function ciclos(Carbon $desde, Carbon $hasta): array
    {
        $ciclos = [];
        $cursor = $desde->copy()->startOfMonth();
        $fin = $hasta->copy()->startOfMonth();
        while ($cursor->lte($fin)) {
            $ciclos[] = $cursor->toDateString();
            $cursor->addMonthNoOverflow();
        }

        return $ciclos;
    }

    /**
     * Facturas normales del ciclo: creadas el mes calendario anterior.
     * El pago aplicado entra en ese ciclo aunque la fecha del recibo sea otra.
     *
     * @param  list<string>  $ciclos
     * @return array<string, array<string, mixed>>
     */
    private function normales(Carbon $desde, Carbon $hasta, array $ciclos): array
    {
        $inicio = $desde->copy()->subMonthNoOverflow()->startOfMonth();
        $fin = $hasta->copy()->subMonthNoOverflow()->endOfMonth();
        $notas = $this->subNotas();
        $aplicado = $this->subAplicado();

        $filas = DB::query()
            ->fromSub($this->baseFacturas($notas, $aplicado)
                ->where(function ($q) {
                    $q->whereNull('fi.tipo_factura')
                        ->orWhere('fi.tipo_factura', '!=', FacturaInterna::TIPO_SERVICIO_ESPECIAL);
                })
                ->whereBetween('fi.created_at', [$inicio, $fin])
                ->selectRaw("DATE_FORMAT(DATE_ADD(fi.created_at, INTERVAL 1 MONTH), '%Y-%m-01') as ciclo")
                ->selectRaw('fi.total as facturado')
                ->selectRaw('COALESCE(nc.notas, 0) as notas')
                ->selectRaw('COALESCE(ap.aplicado, 0) as aplicado')
                ->selectRaw('GREATEST(fi.total - LEAST(fi.total, COALESCE(ap.aplicado, 0)) - COALESCE(nc.notas, 0), 0) as pendiente'), 'base')
            ->groupBy('ciclo')
            ->selectRaw('ciclo')
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('SUM(facturado) as facturado')
            ->selectRaw('SUM(notas) as notas')
            ->selectRaw('SUM(aplicado) as aplicado')
            ->selectRaw('SUM(pendiente) as pendiente')
            ->get()
            ->keyBy('ciclo');

        $out = [];
        foreach ($ciclos as $ciclo) {
            $row = $filas->get($ciclo);
            $out[$ciclo] = $this->fila(
                $ciclo,
                (int) ($row->cantidad ?? 0),
                (float) ($row->facturado ?? 0),
                (float) ($row->notas ?? 0),
                (float) ($row->aplicado ?? 0),
                (float) ($row->pendiente ?? 0),
            );
        }

        return $out;
    }

    /**
     * Especiales: el facturado se ancla al mes del primer pago, o al mes de creación si no hay cobro.
     * El cobrado del mes es solo el pivote con fecha de pago en ese mes.
     *
     * @param  list<string>  $ciclos
     * @return array<string, array<string, mixed>>
     */
    private function especiales(Carbon $desde, Carbon $hasta, array $ciclos): array
    {
        $notas = $this->subNotas();
        $aplicado = $this->subAplicado();
        $primerPago = DB::table('cobro_factura_interna as cfi')
            ->join('cobros as cb', 'cb.id', '=', 'cfi.cobro_id')
            ->groupBy('cfi.factura_interna_id')
            ->selectRaw('cfi.factura_interna_id, MIN(cb.fecha_pago) as primer_pago');

        $facturas = $this->baseFacturas($notas, $aplicado)
            ->leftJoinSub($primerPago, 'pp', 'pp.factura_interna_id', '=', 'fi.id')
            ->where('fi.tipo_factura', FacturaInterna::TIPO_SERVICIO_ESPECIAL)
            ->selectRaw('fi.id')
            ->selectRaw('fi.total as facturado')
            ->selectRaw('COALESCE(nc.notas, 0) as notas')
            ->selectRaw('COALESCE(ap.aplicado, 0) as aplicado')
            ->selectRaw('GREATEST(fi.total - LEAST(fi.total, COALESCE(ap.aplicado, 0)) - COALESCE(nc.notas, 0), 0) as pendiente')
            ->selectRaw("DATE_FORMAT(COALESCE(pp.primer_pago, fi.created_at), '%Y-%m-01') as ancla")
            ->selectRaw('pp.primer_pago')
            ->get();

        $cobradoMes = DB::table('cobro_factura_interna as cfi')
            ->join('cobros as cb', 'cb.id', '=', 'cfi.cobro_id')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->where('fi.tipo_factura', FacturaInterna::TIPO_SERVICIO_ESPECIAL)
            ->whereDate('cb.fecha_pago', '>=', $desde->toDateString())
            ->whereDate('cb.fecha_pago', '<=', $hasta->copy()->endOfMonth()->toDateString())
            ->groupByRaw("DATE_FORMAT(cb.fecha_pago, '%Y-%m-01')")
            ->selectRaw("DATE_FORMAT(cb.fecha_pago, '%Y-%m-01') as mes")
            ->selectRaw('SUM(cfi.monto) as cobrado')
            ->selectRaw('COUNT(DISTINCT fi.id) as cobradas')
            ->get()
            ->keyBy('mes');

        $out = [];
        foreach ($ciclos as $ciclo) {
            $out[$ciclo] = $this->fila($ciclo, 0, 0, 0, 0, 0);
            $out[$ciclo]['cobradas'] = (int) ($cobradoMes->get($ciclo)->cobradas ?? 0);
            $out[$ciclo]['aplicado'] = round((float) ($cobradoMes->get($ciclo)->cobrado ?? 0), 2);
        }

        foreach ($facturas as $factura) {
            $ancla = (string) $factura->ancla;
            if (! isset($out[$ancla])) {
                continue;
            }
            $out[$ancla]['cantidad']++;
            $out[$ancla]['facturado'] += (float) $factura->facturado;
            $out[$ancla]['notas'] += (float) $factura->notas;
            $out[$ancla]['pendiente'] += (float) $factura->pendiente;
            if ($factura->primer_pago === null) {
                $out[$ancla]['sin_cobro']++;
            }
        }

        foreach ($out as $ciclo => $fila) {
            $out[$ciclo] = $this->fila(
                $ciclo,
                (int) $fila['cantidad'],
                (float) $fila['facturado'],
                (float) $fila['notas'],
                (float) $fila['aplicado'],
                (float) $fila['pendiente'],
                (int) ($fila['cobradas'] ?? 0),
                (int) ($fila['sin_cobro'] ?? 0),
            );
        }

        return $out;
    }

    /**
     * @return array<string, float>
     */
    private function saldoFavorPorMes(Carbon $desde, Carbon $hasta): array
    {
        $aplicado = DB::table('cobro_factura_interna')
            ->groupBy('cobro_id')
            ->selectRaw('cobro_id, SUM(monto) as aplicado');

        return DB::table('cobros as cb')
            ->leftJoinSub($aplicado, 'ap', 'ap.cobro_id', '=', 'cb.id')
            ->whereDate('cb.fecha_pago', '>=', $desde->toDateString())
            ->whereDate('cb.fecha_pago', '<=', $hasta->copy()->endOfMonth()->toDateString())
            ->groupByRaw("DATE_FORMAT(cb.fecha_pago, '%Y-%m-01')")
            ->selectRaw("DATE_FORMAT(cb.fecha_pago, '%Y-%m-01') as mes")
            ->selectRaw('SUM(GREATEST(cb.monto - COALESCE(ap.aplicado, 0), 0)) as favor')
            ->pluck('favor', 'mes')
            ->map(fn ($valor) => round((float) $valor, 2))
            ->all();
    }

    /**
     * @param  list<string>  $ciclos
     * @param  array<string, array<string, mixed>>  $normales
     * @param  array<string, array<string, mixed>>  $especiales
     * @param  array<string, float>  $favor
     * @return array<string, array<string, mixed>>
     */
    private function consolidar(array $ciclos, array $normales, array $especiales, array $favor): array
    {
        $out = [];
        foreach ($ciclos as $ciclo) {
            $normal = $normales[$ciclo];
            $especial = $especiales[$ciclo];
            $facturado = (float) $normal['facturado'] + (float) $especial['facturado'];
            $notas = (float) $normal['notas'] + (float) $especial['notas'];
            $aplicado = (float) $normal['aplicado'] + (float) $especial['aplicado'];
            $pendiente = (float) $normal['pendiente'] + (float) $especial['pendiente'];
            $fila = $this->fila(
                $ciclo,
                (int) $normal['cantidad'] + (int) $especial['cantidad'],
                $facturado,
                $notas,
                $aplicado,
                $pendiente,
            );
            $fila['saldo_favor'] = round((float) ($favor[$ciclo] ?? 0), 2);
            $out[$ciclo] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<string>  $ciclos
     * @param  array<string, array<string, mixed>>  $normales
     * @return array<string, array<string, mixed>>
     */
    private function conciliar(array $ciclos, array $normales): array
    {
        $claves = array_map(fn (string $ciclo) => $ciclo, $ciclos);
        $guardados = CobroResumen::query()
            ->whereIn('mes', $claves)
            ->get()
            ->keyBy(fn (CobroResumen $row) => Carbon::parse($row->mes)->toDateString());

        $out = [];
        foreach ($ciclos as $ciclo) {
            $mes = Carbon::parse($ciclo)->startOfMonth();
            $guardado = $guardados->get($ciclo);
            $vivoFacturado = CobrosMesVentana::calcularTotalFacturadoMesReferencia($mes);
            $vivoPendiente = CobrosMesVentana::calcularTotalPendienteMesReferencia($mes);
            $vivoCobrado = CobrosMesVentana::sumMontoRecibosCiclo($mes);
            $normal = $normales[$ciclo];

            $fila = [
                'ciclo' => $ciclo,
                'etiqueta' => $this->etiqueta($ciclo),
                'guardado_facturado' => round((float) ($guardado->total_facturado ?? 0), 2),
                'guardado_cobrado' => round((float) ($guardado->total_cobrado ?? 0), 2),
                'guardado_pendiente' => round((float) ($guardado->total_pendiente ?? 0), 2),
                'vivo_facturado' => round($vivoFacturado, 2),
                'vivo_cobrado' => round($vivoCobrado, 2),
                'vivo_pendiente' => round($vivoPendiente, 2),
                'analisis_facturado' => (float) $normal['facturado_neto'],
                'analisis_aplicado' => (float) $normal['aplicado'],
                'analisis_pendiente' => (float) $normal['pendiente'],
            ];
            $fila['explicacion'] = $this->explicar($fila);
            $out[$ciclo] = $fila;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $fila
     * @return list<string>
     */
    private function explicar(array $fila): array
    {
        $textos = [];
        if (abs($fila['guardado_facturado'] - $fila['vivo_facturado']) > 1) {
            $textos[] = 'El facturado guardado no coincide con las facturas que existen ahora. Pasa cuando se editó un total, se ajustó el mes a mano, o el resumen no se volvió a calcular.';
        }
        if (abs($fila['guardado_cobrado'] - $fila['vivo_cobrado']) > 1) {
            $textos[] = 'El cobrado guardado no coincide con la suma de recibos del ciclo. Pasa si se borró una factura junto con sus cobros y ese cobrado no se rearmó.';
        }
        if (abs($fila['vivo_facturado'] - $fila['analisis_facturado']) > 1) {
            $textos[] = 'El facturado del ciclo incluye las facturas especiales creadas el mes anterior. Este informe las saca de acá y las muestra según el mes del pago.';
        }
        if (abs($fila['vivo_cobrado'] - $fila['analisis_aplicado']) > 1) {
            $textos[] = 'El dashboard suma el recibo entero de la ventana del ciclo. Este informe suma solo lo aplicado a las facturas normales de ese ciclo, en cualquier fecha. La diferencia es saldo a favor, otras facturas del mismo recibo y especiales.';
        }
        if (abs($fila['guardado_pendiente'] - $fila['vivo_pendiente']) > 1) {
            $textos[] = 'El pendiente guardado no coincide con el saldo vivo de las facturas en estado pendiente o emitida. El resumen quedó atrás de una nota, un borrado o un cobro.';
        }
        if (abs($fila['vivo_pendiente'] - $fila['analisis_pendiente']) > 1) {
            $textos[] = 'El pendiente del ciclo cuenta facturas en estado pendiente o emitida, especiales incluidas. Este informe suma el saldo real de las facturas normales, pagadas o no.';
        }
        if ($textos === []) {
            $textos[] = 'Sin diferencia mayor a 1 PYG.';
        }

        return $textos;
    }

    /**
     * @param  array<string, array<string, mixed>>  $consolidado
     * @return array<string, float|int>
     */
    private function totales(array $consolidado): array
    {
        $facturado = 0.0;
        $notas = 0.0;
        $aplicado = 0.0;
        $pendiente = 0.0;
        $favor = 0.0;
        $cantidad = 0;
        foreach ($consolidado as $fila) {
            $cantidad += (int) $fila['cantidad'];
            $facturado += (float) $fila['facturado'];
            $notas += (float) $fila['notas'];
            $aplicado += (float) $fila['aplicado'];
            $pendiente += (float) $fila['pendiente'];
            $favor += (float) $fila['saldo_favor'];
        }

        return [
            'cantidad' => $cantidad,
            'facturado' => round($facturado, 2),
            'notas' => round($notas, 2),
            'aplicado' => round($aplicado, 2),
            'pendiente' => round($pendiente, 2),
            'saldo_favor' => round($favor, 2),
            'porcentaje' => $this->porcentaje($facturado, $notas, $aplicado),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fila(
        string $ciclo,
        int $cantidad,
        float $facturado,
        float $notas,
        float $aplicado,
        float $pendiente,
        int $cobradas = 0,
        int $sinCobro = 0,
    ): array {
        $facturado = round($facturado, 2);
        $notas = round($notas, 2);
        $aplicado = round($aplicado, 2);
        $pendiente = round($pendiente, 2);

        return [
            'ciclo' => $ciclo,
            'etiqueta' => $this->etiqueta($ciclo),
            'cantidad' => $cantidad,
            'cobradas' => $cobradas,
            'sin_cobro' => $sinCobro,
            'facturado' => $facturado,
            'notas' => $notas,
            'facturado_neto' => round($facturado - $notas, 2),
            'aplicado' => $aplicado,
            'pendiente' => $pendiente,
            'porcentaje' => $this->porcentaje($facturado, $notas, $aplicado),
        ];
    }

    private function porcentaje(float $facturado, float $notas, float $aplicado): float
    {
        $base = $facturado - $notas;
        if ($base <= 0.009) {
            return 0.0;
        }

        return round(($aplicado / $base) * 100, 1);
    }

    private function etiqueta(string $ciclo): string
    {
        return Carbon::parse($ciclo)->translatedFormat('M Y');
    }

    private function subNotas()
    {
        return DB::table('factura_interna_notas_credito')
            ->groupBy('factura_interna_id')
            ->selectRaw('factura_interna_id, SUM(monto) as notas');
    }

    private function subAplicado()
    {
        return DB::table('cobro_factura_interna')
            ->groupBy('factura_interna_id')
            ->selectRaw('factura_interna_id, SUM(monto) as aplicado');
    }

    private function baseFacturas($notas, $aplicado)
    {
        return DB::table('factura_internas as fi')
            ->leftJoinSub($notas, 'nc', 'nc.factura_interna_id', '=', 'fi.id')
            ->leftJoinSub($aplicado, 'ap', 'ap.factura_interna_id', '=', 'fi.id');
    }
}
