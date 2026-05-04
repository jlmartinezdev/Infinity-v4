<?php

namespace App\Support;

use App\Models\Cobro;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CobrosMesVentana
{
    /** Inicio de ventana de cobro: día del mes anterior (20 = desde el 20 inclusive). */
    public const DIA_INICIO_COBROS = 20;

    /**
     * Ciclo que cierra en el mes $mesReferencia (debe ser primer día del mes M).
     * Facturas: created_at en el mes natural anterior a M.
     * Cobros: fecha_pago desde el día 20 del mes anterior a M hasta el fin de M.
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
}
