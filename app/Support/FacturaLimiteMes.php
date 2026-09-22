<?php

namespace App\Support;

use App\Models\Factura;
use App\Models\FacturacionParametro;
use Carbon\Carbon;

/**
 * Tope de monto de facturas electrónicas emitidas en un mes (Y-m).
 */
class FacturaLimiteMes
{
    public const CLAVE = 'fe_limites_mes';

    /**
     * @return array<string, float>
     */
    public static function mapa(): array
    {
        $raw = FacturacionParametro::obtener(self::CLAVE, '');
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $mes => $monto) {
            if (! is_string($mes) || ! preg_match('/^\d{4}-\d{2}$/', $mes)) {
                continue;
            }
            if (! is_numeric($monto) || (float) $monto <= 0) {
                continue;
            }
            $out[$mes] = (float) $monto;
        }

        return $out;
    }

    public static function limite(string $ym): ?float
    {
        $ym = self::normalizarMes($ym);
        $mapa = self::mapa();

        return $mapa[$ym] ?? null;
    }

    public static function establecer(string $ym, ?float $monto): void
    {
        $ym = self::normalizarMes($ym);
        $mapa = self::mapa();
        if ($monto === null || $monto <= 0) {
            unset($mapa[$ym]);
        } else {
            $mapa[$ym] = round($monto, 0);
        }
        ksort($mapa);
        FacturacionParametro::establecer(
            self::CLAVE,
            json_encode($mapa, JSON_UNESCAPED_UNICODE),
            'Topes mensuales de facturación electrónica (PYG) por mes Y-m'
        );
    }

    public static function montoEmitido(string $ym): float
    {
        $ym = self::normalizarMes($ym);
        $inicio = Carbon::createFromFormat('Y-m', $ym)->startOfMonth();

        return (float) Factura::query()
            ->where('estado', 'emitida')
            ->whereYear('fecha_emision', $inicio->year)
            ->whereMonth('fecha_emision', $inicio->month)
            ->sum('total');
    }

    /**
     * @return array{
     *     ok: bool,
     *     limite: ?float,
     *     usado: float,
     *     restante: ?float,
     *     porcentaje: ?float,
     *     message: ?string
     * }
     */
    public static function evaluar(string $ym, float $adicional, ?float $usado = null): array
    {
        $ym = self::normalizarMes($ym);
        $limite = self::limite($ym);
        $usado = $usado ?? self::montoEmitido($ym);

        return self::resultado($adicional, $limite, $usado);
    }

    /**
     * @return array{
     *     ok: bool,
     *     limite: ?float,
     *     usado: float,
     *     restante: ?float,
     *     porcentaje: ?float,
     *     message: ?string
     * }
     */
    public static function resultado(float $adicional, ?float $limite, float $usado): array
    {
        $usado = max(0, $usado);
        $adicional = max(0, $adicional);
        if ($limite === null) {
            return [
                'ok' => true,
                'limite' => null,
                'usado' => $usado,
                'restante' => null,
                'porcentaje' => null,
                'message' => null,
            ];
        }

        $restante = max(0, $limite - $usado);
        $porcentaje = $limite > 0 ? min(100, round(($usado / $limite) * 100, 1)) : 0.0;
        $ok = ($usado + $adicional) <= ($limite + 0.5);

        $message = null;
        if (! $ok) {
            $message = sprintf(
                'El tope de facturación de este mes es %s PYG. Ya se emitieron %s PYG; quedan %s PYG. Esta factura es de %s PYG.',
                self::formato($limite),
                self::formato($usado),
                self::formato($restante),
                self::formato($adicional)
            );
        }

        return [
            'ok' => $ok,
            'limite' => $limite,
            'usado' => $usado,
            'restante' => $restante,
            'porcentaje' => $porcentaje,
            'message' => $message,
        ];
    }

    /**
     * @return array{
     *     mes: string,
     *     limite: ?float,
     *     usado: float,
     *     restante: ?float,
     *     porcentaje: ?float,
     *     excedido: bool,
     *     sin_tope: bool
     * }
     */
    public static function resumen(string $ym): array
    {
        $eval = self::evaluar($ym, 0);

        return [
            'mes' => self::normalizarMes($ym),
            'limite' => $eval['limite'],
            'usado' => $eval['usado'],
            'restante' => $eval['restante'],
            'porcentaje' => $eval['porcentaje'],
            'excedido' => $eval['limite'] !== null && $eval['usado'] > $eval['limite'] + 0.5,
            'sin_tope' => $eval['limite'] === null,
        ];
    }

    public static function formato(float $monto): string
    {
        return number_format($monto, 0, ',', '.');
    }

    public static function normalizarMes(string $ym): string
    {
        $ym = trim($ym);
        $fecha = Carbon::createFromFormat('Y-m', $ym);

        return $fecha->format('Y-m');
    }
}
