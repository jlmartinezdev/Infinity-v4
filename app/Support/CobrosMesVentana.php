<?php

namespace App\Support;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class CobrosMesVentana
{
    /** Inicio de ventana de cobro: día del mes anterior (20 = desde el 20 inclusive). */
    public const DIA_INICIO_COBROS = 20;

    /**
     * Ciclo que cierra en el mes $mesReferencia (debe ser primer día del mes M).
     * Facturas: created_at en el mes natural anterior a M.
     * Cobros: fecha_pago desde el día 20 del mes anterior a M hasta el fin de M.
     * Atribución: si el pago y la factura son del día 20 de M o después, no suma en M (va al mes siguiente).
     *
     * @return array{desdeVentana: Carbon, hastaVentana: Carbon, facturaDesde: Carbon, facturaHasta: Carbon}
     */
    public static function rangosParaMesReferencia(Carbon $mesReferencia): array
    {
        $mesM = $mesReferencia->copy()->startOfMonth()->startOfDay();

        $facturaDesde = $mesM->copy()->subMonthNoOverflow()->startOfMonth()->startOfDay();
        $facturaHasta = $mesM->copy()->subMonthNoOverflow()->endOfMonth()->endOfDay();

        $desdeVentana = $mesM->copy()->subMonthNoOverflow()->startOfMonth()->addDays(self::DIA_INICIO_COBROS - 1)->startOfDay();
        $hastaVentana = $mesM->copy()->endOfMonth()->endOfDay();

        return [
            'desdeVentana' => $desdeVentana,
            'hastaVentana' => $hastaVentana,
            'facturaDesde' => $facturaDesde,
            'facturaHasta' => $facturaHasta,
        ];
    }

    /** Ciclo vigente según la fecha actual (mes calendario). */
    public static function rangosMesActual(?Carbon $ahora = null): array
    {
        $ahora ??= now();

        return self::rangosParaMesReferencia($ahora->copy()->startOfMonth());
    }

    /**
     * Recibos del ciclo: fecha de pago en la ventana y al menos una factura creada el mes anterior.
     * Es el mismo criterio del listado de Cobros al abrir «Cobros del mes» y del PDF resumen.
     *
     * @param  Builder<Cobro>|QueryBuilder  $query
     */
    public static function aplicarFiltroRecibosCiclo($query, Carbon $mesReferencia, string $table = 'cobros'): void
    {
        $rangos = self::rangosParaMesReferencia($mesReferencia);
        $fd = $rangos['facturaDesde']->toDateString();
        $fh = $rangos['facturaHasta']->toDateString();

        $query->whereDate("{$table}.fecha_pago", '>=', $rangos['desdeVentana']->toDateString());
        $query->whereDate("{$table}.fecha_pago", '<=', $rangos['hastaVentana']->toDateString());
        $query->whereExists(function ($sub) use ($table, $fd, $fh) {
            $sub->selectRaw('1')
                ->from('cobro_factura_interna as cfi_ciclo')
                ->join('factura_internas as fi_ciclo', 'fi_ciclo.id', '=', 'cfi_ciclo.factura_interna_id')
                ->whereColumn('cfi_ciclo.cobro_id', "{$table}.id")
                ->whereDate('fi_ciclo.created_at', '>=', $fd)
                ->whereDate('fi_ciclo.created_at', '<=', $fh);
        });
    }

    public static function sumMontoRecibosCiclo(Carbon $mesReferencia): float
    {
        $q = DB::table('cobros');
        self::aplicarFiltroRecibosCiclo($q, $mesReferencia);

        return round((float) $q->sum('monto'), 2);
    }

    /**
     * Meses de resumen cuya ventana de cobro incluye esta fecha de pago.
     *
     * @return list<string> Fechas Y-m-01
     */
    public static function mesesVentanaDeFechaPago(Carbon $fechaPago): array
    {
        $dia = $fechaPago->copy()->startOfDay();
        $base = $dia->copy()->startOfMonth();
        $meses = [];

        foreach ([-1, 0, 1] as $offset) {
            $mes = $base->copy()->addMonthsNoOverflow($offset)->startOfMonth();
            $rangos = self::rangosParaMesReferencia($mes);
            $desde = $rangos['desdeVentana']->copy()->startOfDay();
            $hasta = $rangos['hastaVentana']->copy()->startOfDay();
            if ($dia->between($desde, $hasta)) {
                $meses[] = $mes->toDateString();
            }
        }

        return array_values(array_unique($meses));
    }

    /**
     * @param  array{desdeVentana: Carbon, hastaVentana: Carbon, facturaDesde: Carbon, facturaHasta: Carbon}  $rangos
     * @return array{desde: string, hasta: string, factura_desde: string, factura_hasta: string}
     */
    public static function queryParamsDesdeRangos(array $rangos): array
    {
        return [
            'desde' => $rangos['desdeVentana']->toDateString(),
            'hasta' => $rangos['hastaVentana']->copy()->toDateString(),
            'factura_desde' => $rangos['facturaDesde']->toDateString(),
            'factura_hasta' => $rangos['facturaHasta']->toDateString(),
        ];
    }

    /** Día 20 del mes de referencia M (corte pago/factura tardíos). */
    public static function diaCorteMesReferencia(Carbon $mesReferencia): Carbon
    {
        return $mesReferencia->copy()->startOfMonth()->addDays(self::DIA_INICIO_COBROS - 1)->startOfDay();
    }

    /**
     * Indica si un cobro cuenta en el total del mes M.
     * Excluye cuando fecha_pago y created_at de la factura son ambos el día 20 de M o después.
     */
    public static function cobroCuentaEnMesReferencia(Carbon $fechaPago, ?Carbon $facturaCreatedAt, Carbon $mesReferencia): bool
    {
        if ($facturaCreatedAt === null) {
            return true;
        }

        $diaCorte = self::diaCorteMesReferencia($mesReferencia);

        return ! (
            $fechaPago->copy()->startOfDay()->gte($diaCorte)
            && $facturaCreatedAt->copy()->startOfDay()->gte($diaCorte)
        );
    }

    /**
     * SQL: fecha de la factura asociada al cobro (MIN del pivote o legacy factura_interna_id).
     */
    private static function sqlFacturaCreatedAtCobro(string $table): string
    {
        return "COALESCE(
            (SELECT MIN(fi_attr.created_at) FROM cobro_factura_interna cfi_attr
             INNER JOIN factura_internas fi_attr ON fi_attr.id = cfi_attr.factura_interna_id
             WHERE cfi_attr.cobro_id = {$table}.id),
            (SELECT fi_legacy.created_at FROM factura_internas fi_legacy WHERE fi_legacy.id = {$table}.factura_interna_id)
        )";
    }

    /**
     * Excluye cobros con pago y factura del día 20 del mes de referencia o después.
     *
     * @param  Builder<Cobro>|QueryBuilder  $query
     */
    public static function aplicarFiltroAtribucionMesReferencia(Builder|QueryBuilder $query, Carbon $mesReferencia, string $table = 'cobros'): void
    {
        $diaCorte = self::diaCorteMesReferencia($mesReferencia)->toDateString();
        $sqlFactura = self::sqlFacturaCreatedAtCobro($table);

        $query->where(function ($q) use ($diaCorte, $table, $sqlFactura) {
            $q->where(function ($q2) use ($table) {
                $q2->whereNotExists(function ($sub) use ($table) {
                    $sub->selectRaw('1')
                        ->from('cobro_factura_interna as cfi_sin_f')
                        ->whereColumn('cfi_sin_f.cobro_id', "{$table}.id");
                })->whereNull("{$table}.factura_interna_id");
            })->orWhereRaw(
                "NOT (DATE({$table}.fecha_pago) >= ? AND DATE({$sqlFactura}) >= ?)",
                [$diaCorte, $diaCorte]
            );
        });
    }

    public static function sumPivotMontos(
        Carbon $desdeVentana,
        Carbon $hastaVentana,
        Carbon $facturaDesde,
        Carbon $facturaHasta,
        ?int $usuarioId = null,
        ?string $formaPago = null,
    ): float {
        $q = DB::table('cobro_factura_interna as cfi')
            ->join('cobros', 'cobros.id', '=', 'cfi.cobro_id')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->whereBetween('cobros.fecha_pago', [$desdeVentana, $hastaVentana])
            ->whereBetween('fi.created_at', [$facturaDesde, $facturaHasta]);

        if ($usuarioId !== null) {
            $q->where('cobros.usuario_id', $usuarioId);
        }

        if ($formaPago !== null) {
            $formas = array_keys(Cobro::formasPago());
            if (in_array($formaPago, $formas, true)) {
                $q->where('cobros.forma_pago', $formaPago);
            }
        }

        return (float) ($q->sum('cfi.monto') ?? 0);
    }

    /**
     * Suma de ingreso real: usa cobros.monto (sin depender de distribucion en pivote).
     */
    public static function sumCobrosMontos(
        Carbon $desdeVentana,
        Carbon $hastaVentana,
        Carbon $mesReferencia,
        ?int $usuarioId = null,
        ?string $formaPago = null,
    ): float {
        $q = DB::table('cobros')
            ->whereBetween('fecha_pago', [$desdeVentana, $hastaVentana]);

        self::aplicarFiltroAtribucionMesReferencia($q, $mesReferencia);

        if ($usuarioId !== null) {
            $q->where('usuario_id', $usuarioId);
        }

        if ($formaPago !== null) {
            $formas = array_keys(Cobro::formasPago());
            if (in_array($formaPago, $formas, true)) {
                $q->where('forma_pago', $formaPago);
            }
        }

        return (float) ($q->sum('monto') ?? 0);
    }

    public static function sumPivotMesActualSinUsuario(?Carbon $ahora = null): float
    {
        $r = self::rangosMesActual($ahora);

        return self::sumPivotMontos(
            $r['desdeVentana'],
            $r['hastaVentana'],
            $r['facturaDesde'],
            $r['facturaHasta'],
            null,
            null,
        );
    }

    public static function sumCobrosMesActualSinUsuario(?Carbon $ahora = null): float
    {
        $ahora ??= now();
        $r = self::rangosMesActual($ahora);

        return self::sumCobrosMontos(
            $r['desdeVentana'],
            $r['hastaVentana'],
            $ahora->copy()->startOfMonth(),
            null,
            null,
        );
    }

    /**
     * Clasifica un monto del pivote (cobro → factura) para el resumen mensual.
     * Facturas especiales: el cobro suma siempre al mes de la fecha de pago (no siguen el ciclo mensual).
     *
     * @return array{mes: string, total_cobrado: float, reduccion_pendiente: float, pago_adelantado: float, pago_atrasado: float}|null
     */
    public static function clasificarLineaPivot(Carbon $fechaPago, Carbon $facturaCreatedAt, float $monto, bool $facturaEspecial = false): ?array
    {
        if ($monto <= 0) {
            return null;
        }

        if ($facturaEspecial) {
            return [
                'mes' => $fechaPago->copy()->startOfMonth()->startOfDay()->toDateString(),
                'total_cobrado' => $monto,
                'reduccion_pendiente' => 0.0,
                'pago_adelantado' => 0.0,
                'pago_atrasado' => 0.0,
            ];
        }

        $mesReferencia = $fechaPago->copy()->startOfMonth()->startOfDay();
        $diaCorte = self::diaCorteMesReferencia($mesReferencia);

        // Factura emitida el día 20 o después pertenece al ciclo del mes siguiente (no al mes actual).
        if ($facturaCreatedAt->copy()->startOfDay()->gte($diaCorte)) {
            $mesReferencia = $mesReferencia->copy()->addMonthNoOverflow()->startOfMonth();
        }

        $rangos = self::rangosParaMesReferencia($mesReferencia);
        $diaCorte = self::diaCorteMesReferencia($mesReferencia);
        $finMesM = $mesReferencia->copy()->endOfMonth()->endOfDay();

        $fechaPagoDia = $fechaPago->copy()->startOfDay();
        $facturaDia = $facturaCreatedAt->copy()->startOfDay();

        $pagoAdelantado = 0.0;
        $pagoAtrasado = 0.0;
        $reduccionPendiente = 0.0;

        if ($facturaDia->between($rangos['facturaDesde'], $rangos['facturaHasta'])) {
            $reduccionPendiente = $monto;
        } elseif ($facturaDia->lt($rangos['facturaDesde'])) {
            if ($fechaPagoDia->lt($diaCorte)) {
                return null;
            }
            $pagoAtrasado = $monto;
        } elseif ($facturaDia->between($diaCorte, $finMesM)) {
            $pagoAdelantado = $monto;
            $reduccionPendiente = $monto;
        } else {
            return null;
        }

        return [
            'mes' => $mesReferencia->toDateString(),
            'total_cobrado' => $monto,
            'reduccion_pendiente' => $reduccionPendiente,
            'pago_adelantado' => $pagoAdelantado,
            'pago_atrasado' => $pagoAtrasado,
        ];
    }

    /**
     * Clasifica cobro sin factura (saldo a favor): total en el mes de referencia si cae en ventana.
     *
     * @return array{mes: string, total_cobrado: float, reduccion_pendiente: float, pago_adelantado: float, pago_atrasado: float}|null
     */
    public static function clasificarCobroSinFactura(Carbon $fechaPago, float $monto): ?array
    {
        if ($monto <= 0) {
            return null;
        }

        $mesReferencia = $fechaPago->copy()->startOfMonth()->startOfDay();
        $rangos = self::rangosParaMesReferencia($mesReferencia);

        if ($fechaPago->lt($rangos['desdeVentana']) || $fechaPago->gt($rangos['hastaVentana'])) {
            return null;
        }

        return [
            'mes' => $mesReferencia->toDateString(),
            'total_cobrado' => $monto,
            'reduccion_pendiente' => 0.0,
            'pago_adelantado' => 0.0,
            'pago_atrasado' => 0.0,
        ];
    }

    /**
     * Desglose por mes de un cobro (cada línea del pivote puede ir a un mes distinto).
     *
     * @return array<string, array{total_cobrado: float, reduccion_pendiente: float, pago_adelantado: float, pago_atrasado: float}>
     */
    public static function desgloseCobroParaResumen(Cobro $cobro): array
    {
        $cobro->loadMissing(['facturaInternas', 'facturaInterna']);

        $fechaPago = Carbon::parse($cobro->fecha_pago);
        $porMes = [];

        $agregar = static function (array $linea) use (&$porMes): void {
            $mes = $linea['mes'];
            if (! isset($porMes[$mes])) {
                $porMes[$mes] = [
                    'total_cobrado' => 0.0,
                    'reduccion_pendiente' => 0.0,
                    'pago_adelantado' => 0.0,
                    'pago_atrasado' => 0.0,
                ];
            }
            $porMes[$mes]['total_cobrado'] += $linea['total_cobrado'];
            $porMes[$mes]['reduccion_pendiente'] += $linea['reduccion_pendiente'];
            $porMes[$mes]['pago_adelantado'] += $linea['pago_adelantado'];
            $porMes[$mes]['pago_atrasado'] += $linea['pago_atrasado'];
        };

        if ($cobro->facturaInternas->isNotEmpty()) {
            foreach ($cobro->facturaInternas as $factura) {
                $monto = (float) ($factura->pivot->monto ?? 0);
                $esEspecial = $factura->esServicioEspecial();
                $linea = self::clasificarLineaPivot(
                    $fechaPago,
                    Carbon::parse($factura->created_at),
                    $monto,
                    $esEspecial
                );
                if ($linea !== null) {
                    $agregar($linea);
                }
                if ($esEspecial && $monto > 0) {
                    foreach (self::lineasReduccionPendienteEspecial($factura, $monto) as $lineaPendiente) {
                        $agregar($lineaPendiente);
                    }
                }
            }

            $sumaPivot = (float) $cobro->facturaInternas->sum(fn ($f) => (float) ($f->pivot->monto ?? 0));
            $exceso = (float) $cobro->monto - $sumaPivot;
            if ($exceso > 0.009) {
                $linea = self::clasificarCobroSinFactura($fechaPago, $exceso);
                if ($linea !== null) {
                    $agregar($linea);
                }
            }

            return $porMes;
        }

        if ($cobro->facturaInterna) {
            $esEspecial = $cobro->facturaInterna->esServicioEspecial();
            $linea = self::clasificarLineaPivot(
                $fechaPago,
                Carbon::parse($cobro->facturaInterna->created_at),
                (float) $cobro->monto,
                $esEspecial
            );
            if ($linea !== null) {
                $agregar($linea);
            }
            if ($esEspecial && (float) $cobro->monto > 0) {
                foreach (self::lineasReduccionPendienteEspecial($cobro->facturaInterna, (float) $cobro->monto) as $lineaPendiente) {
                    $agregar($lineaPendiente);
                }
            }

            return $porMes;
        }

        $linea = self::clasificarCobroSinFactura($fechaPago, (float) $cobro->monto);
        if ($linea !== null) {
            $agregar($linea);
        }

        return $porMes;
    }

    /**
     * Líneas de reducción de pendiente para una factura especial cobrada:
     * el cobro suma al mes de pago, pero el pendiente se descuenta en los meses
     * de ciclo donde la factura cuenta como facturado/pendiente.
     *
     * @return list<array{mes: string, total_cobrado: float, reduccion_pendiente: float, pago_adelantado: float, pago_atrasado: float}>
     */
    private static function lineasReduccionPendienteEspecial(FacturaInterna $factura, float $monto): array
    {
        if (! $factura->created_at) {
            return [];
        }

        $lineas = [];
        foreach (self::mesesReferenciaAfectadosPorFactura(Carbon::parse($factura->created_at)) as $mes) {
            $lineas[] = [
                'mes' => $mes,
                'total_cobrado' => 0.0,
                'reduccion_pendiente' => $monto,
                'pago_adelantado' => 0.0,
                'pago_atrasado' => 0.0,
            ];
        }

        return $lineas;
    }

    /** Meses que pueden verse afectados al modificar o borrar un cobro. */
    public static function mesesPosiblesAfectadosPorCobro(Cobro $cobro): array
    {
        $meses = array_keys(self::desgloseCobroParaResumen($cobro));
        $base = Carbon::parse($cobro->fecha_pago)->startOfMonth();
        $meses[] = $base->toDateString();
        $meses[] = $base->copy()->addMonthNoOverflow()->toDateString();
        $meses[] = $base->copy()->subMonthNoOverflow()->toDateString();

        return array_values(array_unique($meses));
    }

    /**
     * Meses de cobros_resumen a recalcular tras registrar o eliminar un cobro.
     *
     * @return list<string> Fechas Y-m-01
     */
    public static function mesesResumenCompletosPorCobro(Cobro $cobro): array
    {
        $cobro->loadMissing(['facturaInternas', 'facturaInterna']);

        $meses = self::mesesPosiblesAfectadosPorCobro($cobro);

        foreach ($cobro->facturaInternas as $factura) {
            if ($factura->created_at) {
                $meses = array_merge(
                    $meses,
                    self::mesesReferenciaAfectadosPorFactura(Carbon::parse($factura->created_at))
                );
            }
        }

        if ($cobro->facturaInterna?->created_at) {
            $meses = array_merge(
                $meses,
                self::mesesReferenciaAfectadosPorFactura(Carbon::parse($cobro->facturaInterna->created_at))
            );
        }

        return array_values(array_unique($meses));
    }

    /**
     * Facturas del ciclo: solo el mes natural anterior a M.
     * Las emitidas el día 20 o después de M van al ciclo de M+1, no se duplican en M.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public static function aplicarFiltroFacturasCicloMesReferencia($query, Carbon $mesReferencia, string $table = 'factura_internas'): void
    {
        $rangos = self::rangosParaMesReferencia($mesReferencia);

        $query->whereBetween("{$table}.created_at", [$rangos['facturaDesde'], $rangos['facturaHasta']]);
    }

    public static function calcularTotalFacturadoMesReferencia(Carbon $mesReferencia): float
    {
        $q = DB::table('factura_internas');
        self::aplicarFiltroFacturasCicloMesReferencia($q, $mesReferencia);
        $total = (float) ($q->sum('factura_internas.total') ?? 0);

        $notas = DB::table('factura_interna_notas_credito')
            ->join('factura_internas', 'factura_internas.id', '=', 'factura_interna_notas_credito.factura_interna_id');
        self::aplicarFiltroFacturasCicloMesReferencia($notas, $mesReferencia);
        $totalNotas = (float) ($notas->sum('factura_interna_notas_credito.monto') ?? 0);

        return round($total - $totalNotas, 2);
    }

    public static function calcularTotalPendienteMesReferencia(Carbon $mesReferencia): float
    {
        $saldo = FacturaInterna::sqlSaldoPendienteExpr();
        $q = DB::table('factura_internas')
            ->selectRaw("SUM({$saldo}) as total_pendiente")
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->whereRaw("{$saldo} > 0");

        self::aplicarFiltroFacturasCicloMesReferencia($q, $mesReferencia);

        return (float) ($q->value('total_pendiente') ?? 0);
    }

    /**
     * Saldo impago de facturas pendiente/emitida, separado por vencimiento.
     * Vencidas: fecha_vencimiento anterior a hoy. Por vencer: hoy o posterior (o sin fecha).
     *
     * @return array{vencidas: float, por_vencer: float}
     */
    public static function totalesPendientePorVencimiento(?Carbon $mesReferencia = null, ?Carbon $hoy = null): array
    {
        $hoyStr = ($hoy ?? now())->toDateString();
        $saldo = FacturaInterna::sqlSaldoPendienteExpr();

        $q = DB::table('factura_internas')
            ->selectRaw("
                COALESCE(SUM(CASE
                    WHEN factura_internas.fecha_vencimiento IS NOT NULL AND factura_internas.fecha_vencimiento < ?
                    THEN {$saldo} ELSE 0 END), 0) as vencidas,
                COALESCE(SUM(CASE
                    WHEN factura_internas.fecha_vencimiento IS NULL OR factura_internas.fecha_vencimiento >= ?
                    THEN {$saldo} ELSE 0 END), 0) as por_vencer
            ", [$hoyStr, $hoyStr])
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->whereRaw($saldo.' > 0');

        if ($mesReferencia !== null) {
            self::aplicarFiltroFacturasCicloMesReferencia($q, $mesReferencia);
        }

        $row = $q->first();

        return [
            'vencidas' => (float) ($row->vencidas ?? 0),
            'por_vencer' => (float) ($row->por_vencer ?? 0),
        ];
    }

    /**
     * Meses de resumen a recalcular cuando se crea o modifica una factura interna.
     *
     * @return list<string> Fechas Y-m-01
     */
    public static function mesesReferenciaAfectadosPorFactura(Carbon $fechaFactura): array
    {
        $f = $fechaFactura->copy()->startOfDay();

        return [
            $f->copy()->addMonthNoOverflow()->startOfMonth()->toDateString(),
        ];
    }

    /** SQL (MySQL) para el monto del cobro registrado como saldo a favor. */
    public static function sqlMontoSaldoFavorRegistrado(): string
    {
        return '(
            CASE
                WHEN EXISTS (SELECT 1 FROM cobro_factura_interna cfi WHERE cfi.cobro_id = cobros.id)
                THEN GREATEST(cobros.monto - COALESCE((SELECT SUM(cfi2.monto) FROM cobro_factura_interna cfi2 WHERE cfi2.cobro_id = cobros.id), 0), 0)
                WHEN cobros.factura_interna_id IS NULL
                THEN cobros.monto
                ELSE 0
            END
        )';
    }

    public static function montoSaldoFavorRegistrado(Cobro $cobro): float
    {
        $cobro->loadMissing('facturaInternas');
        $monto = (float) $cobro->monto;

        if ($cobro->facturaInternas->isNotEmpty()) {
            $aplicado = (float) $cobro->facturaInternas->sum(fn ($f) => (float) ($f->pivot->monto ?? 0));

            return max(0, round($monto - $aplicado, 2));
        }

        if ($cobro->factura_interna_id) {
            return 0.0;
        }

        return round($monto, 2);
    }

    public static function tipoSaldoFavorRegistrado(Cobro $cobro): string
    {
        $cobro->loadMissing('facturaInternas');
        if ($cobro->facturaInternas->isEmpty() && ! $cobro->factura_interna_id) {
            return 'sin_factura';
        }

        return 'exceso';
    }

    /** @param  Builder<Cobro>  $query */
    public static function scopeConSaldoFavorRegistrado(Builder $query): Builder
    {
        return $query->whereRaw(self::sqlMontoSaldoFavorRegistrado().' > 0.009');
    }
}
