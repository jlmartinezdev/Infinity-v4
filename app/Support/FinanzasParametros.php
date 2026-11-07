<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\FinCuenta;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros del libro financiero. Viven en facturacion_parametros
 * y se editan en Configuración. No mueven recibos ni saldos por sí solos.
 */
class FinanzasParametros
{
    public const CUENTA_EFECTIVO = 'efectivo';

    public const CUENTA_BANCO = 'banco';

    public const CUENTA_BANCARD = 'bancard';

    public const SIN_ASIGNAR = 'sin_asignar';

    /**
     * @return array<string, string>
     */
    public static function cuentas(): array
    {
        $cuentas = [];
        if (Schema::hasTable('fin_cuentas')) {
            foreach (FinCuenta::query()->where('activa', true)->orderBy('orden')->orderBy('nombre')->get() as $cuenta) {
                $cuentas[$cuenta->codigo] = $cuenta->nombre;
            }
        }
        if ($cuentas === []) {
            $cuentas = [
                self::CUENTA_EFECTIVO => 'Efectivo',
                self::CUENTA_BANCO => 'Transferencia bancaria',
                self::CUENTA_BANCARD => 'Bancard / TPago',
            ];
        }
        $cuentas[self::SIN_ASIGNAR] = 'Sin asignar';

        return $cuentas;
    }

    /**
     * @return array<string, string>
     */
    public static function formas(): array
    {
        return [
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'tpago' => 'Bancard / TPago',
            'tarjeta' => 'Tarjeta que no es TPago',
            'cheque' => 'Cheque',
            'otro' => 'Otro',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function cuentasPorDefecto(): array
    {
        return [
            'efectivo' => self::CUENTA_EFECTIVO,
            'transferencia' => self::CUENTA_BANCO,
            'tpago' => self::CUENTA_BANCARD,
            'tarjeta' => self::SIN_ASIGNAR,
            'cheque' => self::SIN_ASIGNAR,
            'otro' => self::SIN_ASIGNAR,
        ];
    }

    public static function fechaCorte(): ?Carbon
    {
        $valor = trim((string) FacturacionParametro::obtener('fin_fecha_corte', ''));
        if ($valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function cuentaDeForma(string $forma): string
    {
        $defecto = self::cuentasPorDefecto()[$forma] ?? self::SIN_ASIGNAR;
        $valor = trim((string) FacturacionParametro::obtener('fin_cuenta_'.$forma, $defecto));

        return array_key_exists($valor, self::cuentas()) ? $valor : self::SIN_ASIGNAR;
    }

    /**
     * TPago se reconoce por la referencia o la observación, sin reescribir el recibo.
     */
    public static function cuentaDeRecibo(string $formaPago, ?string $referencia, ?string $observaciones): string
    {
        $referencia = (string) $referencia;
        $observaciones = (string) $observaciones;
        if (str_starts_with($referencia, 'TPAGO-') || str_starts_with($observaciones, 'Pago TPago')) {
            return self::cuentaDeForma('tpago');
        }

        return self::cuentaDeForma($formaPago);
    }

    public static function comisionBancard(): ?float
    {
        $valor = FacturacionParametro::obtener('fin_comision_bancard', '');
        if ($valor === '' || $valor === null) {
            return null;
        }

        return round((float) $valor, 2);
    }

    /**
     * @return array{efectivo: ?float, banco: ?float, bancard: ?float, fecha: ?Carbon}
     */
    public static function saldosIniciales(): array
    {
        $fecha = trim((string) FacturacionParametro::obtener('fin_saldo_inicial_fecha', ''));

        return [
            self::CUENTA_EFECTIVO => self::saldoGuardado('fin_saldo_inicial_efectivo'),
            self::CUENTA_BANCO => self::saldoGuardado('fin_saldo_inicial_banco'),
            self::CUENTA_BANCARD => self::saldoGuardado('fin_saldo_inicial_bancard'),
            'fecha' => $fecha === '' ? null : Carbon::parse($fecha)->startOfDay(),
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public static function guardar(array $datos): void
    {
        $fecha = trim((string) ($datos['fin_fecha_corte'] ?? ''));
        FacturacionParametro::establecer(
            'fin_fecha_corte',
            $fecha,
            'Desde esta fecha los recibos nuevos entran al libro financiero'
        );

        foreach (array_keys(self::formas()) as $forma) {
            FacturacionParametro::establecer(
                'fin_cuenta_'.$forma,
                (string) ($datos['fin_cuenta_'.$forma] ?? self::SIN_ASIGNAR),
                'Cuenta financiera para '.$forma
            );
        }

        $comision = trim((string) ($datos['fin_comision_bancard'] ?? ''));
        FacturacionParametro::establecer(
            'fin_comision_bancard',
            $comision,
            'Porcentaje sugerido de comisión Bancard. No se descuenta solo.'
        );

        foreach ([self::CUENTA_EFECTIVO, self::CUENTA_BANCO, self::CUENTA_BANCARD] as $cuenta) {
            FacturacionParametro::establecer(
                'fin_saldo_inicial_'.$cuenta,
                trim((string) ($datos['fin_saldo_inicial_'.$cuenta] ?? '')),
                'Saldo inicial declarado de '.$cuenta
            );
        }

        FacturacionParametro::establecer(
            'fin_saldo_inicial_fecha',
            trim((string) ($datos['fin_saldo_inicial_fecha'] ?? '')),
            'Fecha del saldo inicial declarado'
        );
    }

    public static function normalizarMonto(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $texto = str_replace(' ', '', $texto);
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $texto) === 1) {
            $texto = str_replace('.', '', $texto);
        }

        return $texto;
    }

    private static function saldoGuardado(string $clave): ?float
    {
        $valor = FacturacionParametro::obtener($clave, '');
        if ($valor === '' || $valor === null) {
            return null;
        }

        return round((float) $valor, 2);
    }
}
