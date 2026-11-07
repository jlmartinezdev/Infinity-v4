<?php

namespace App\Services\Finanzas;

use App\Models\Cobro;
use App\Models\FinCuenta;
use App\Models\FinMovimiento;
use App\Support\FinanzasParametros;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class FinanzasCobroPuente
{
    public function __construct(private readonly FinanzasLibroService $libro)
    {
    }

    public function registrar(Cobro $cobro): ?FinMovimiento
    {
        $corte = FinanzasParametros::fechaCorte();
        if (! $corte) {
            return null;
        }
        $fecha = Carbon::parse($cobro->fecha_pago)->startOfDay();
        if ($fecha->lt($corte)) {
            return null;
        }

        $codigo = FinanzasParametros::cuentaDeRecibo($cobro->forma_pago, $cobro->referencia, $cobro->observaciones);
        if ($codigo === FinanzasParametros::SIN_ASIGNAR) {
            return null;
        }
        $cuenta = FinCuenta::query()->where('codigo', $codigo)->where('activa', true)->first();
        if (! $cuenta) {
            return null;
        }

        $existente = FinMovimiento::query()
            ->where('referencia_tipo', 'cobro')
            ->where('referencia_id', $cobro->id)
            ->where('tipo', 'ingreso')
            ->whereNull('revertido_por_id')
            ->first();
        if ($existente) {
            return $existente;
        }

        try {
            return $this->libro->registrar([
                'cuenta_id' => $cuenta->id,
                'tipo' => 'ingreso',
                'signo' => 1,
                'fecha' => $fecha,
                'importe' => $cobro->monto,
                'concepto' => 'Recibo '.($cobro->numero_recibo ?: $cobro->id),
                'categoria_id' => \App\Models\FinCategoria::query()->where('nombre', 'Cobro de clientes')->value('id'),
                'referencia_tipo' => 'cobro',
                'referencia_id' => $cobro->id,
                'referencia_texto' => $cobro->numero_recibo,
                'usuario_id' => $cobro->usuario_id,
                'observaciones' => $cobro->forma_pago,
            ]);
        } catch (QueryException $e) {
            $repetido = FinMovimiento::query()
                ->where('referencia_tipo', 'cobro')
                ->where('referencia_id', $cobro->id)
                ->where('tipo', 'ingreso')
                ->first();
            if ($repetido) {
                return $repetido;
            }
            throw $e;
        }
    }

    /**
     * @return array{total:int,ya_estaban:int,pendientes:int,antes_de_corte:int,sin_cuenta:int,monto_pendiente:float,por_cuenta:array<string,array{cantidad:int,monto:float}>}
     */
    public function previaMes(Carbon $mes): array
    {
        $corte = FinanzasParametros::fechaCorte();
        $cobros = $this->cobrosDelMes($mes);
        $enLibro = $this->idsEnLibro($cobros->pluck('id')->all());
        $previa = [
            'total' => $cobros->count(),
            'ya_estaban' => 0,
            'pendientes' => 0,
            'antes_de_corte' => 0,
            'sin_cuenta' => 0,
            'monto_pendiente' => 0.0,
            'por_cuenta' => [],
        ];
        foreach ($cobros as $cobro) {
            if (isset($enLibro[$cobro->id])) {
                $previa['ya_estaban']++;
                continue;
            }
            $motivo = $this->motivoDeOmision($cobro, $corte);
            if ($motivo !== null) {
                $previa[$motivo]++;
                continue;
            }
            $codigo = FinanzasParametros::cuentaDeRecibo($cobro->forma_pago, $cobro->referencia, $cobro->observaciones);
            $previa['pendientes']++;
            $previa['monto_pendiente'] += (float) $cobro->monto;
            $fila = $previa['por_cuenta'][$codigo] ?? ['cantidad' => 0, 'monto' => 0.0];
            $fila['cantidad']++;
            $fila['monto'] += (float) $cobro->monto;
            $previa['por_cuenta'][$codigo] = $fila;
        }
        $previa['monto_pendiente'] = round($previa['monto_pendiente'], 2);
        foreach ($previa['por_cuenta'] as $codigo => $fila) {
            $previa['por_cuenta'][$codigo]['monto'] = round($fila['monto'], 2);
        }

        return $previa;
    }

    /**
     * @return array{cargados:int,ya_estaban:int,omitidos:int,monto_cargado:float,errores:list<string>}
     */
    public function cargarMes(Carbon $mes): array
    {
        $resultado = [
            'cargados' => 0,
            'ya_estaban' => 0,
            'omitidos' => 0,
            'monto_cargado' => 0.0,
            'errores' => [],
        ];
        $cobros = $this->cobrosDelMes($mes);
        $enLibro = $this->idsEnLibro($cobros->pluck('id')->all());
        foreach ($cobros as $cobro) {
            if (isset($enLibro[$cobro->id])) {
                $resultado['ya_estaban']++;
                continue;
            }
            try {
                $movimiento = $this->registrar($cobro);
            } catch (\Throwable $e) {
                $resultado['omitidos']++;
                if (count($resultado['errores']) < 8) {
                    $resultado['errores'][] = ($cobro->numero_recibo ?: $cobro->id).': '.$e->getMessage();
                }
                continue;
            }
            if (! $movimiento) {
                $resultado['omitidos']++;
                continue;
            }
            $resultado['cargados']++;
            $resultado['monto_cargado'] += (float) $cobro->monto;
        }
        $resultado['monto_cargado'] = round($resultado['monto_cargado'], 2);

        return $resultado;
    }

    private function cobrosDelMes(Carbon $mes)
    {
        return Cobro::query()
            ->whereDate('fecha_pago', '>=', $mes->copy()->startOfMonth()->toDateString())
            ->whereDate('fecha_pago', '<=', $mes->copy()->endOfMonth()->toDateString())
            ->orderBy('id')
            ->get(['id', 'monto', 'fecha_pago', 'forma_pago', 'numero_recibo', 'referencia', 'observaciones', 'usuario_id']);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function idsEnLibro(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $enLibro = [];
        foreach (array_chunk($ids, 500) as $lote) {
            $encontrados = FinMovimiento::query()
                ->where('referencia_tipo', 'cobro')
                ->where('tipo', 'ingreso')
                ->whereNull('revertido_por_id')
                ->whereIn('referencia_id', $lote)
                ->pluck('referencia_id');
            foreach ($encontrados as $id) {
                $enLibro[(int) $id] = true;
            }
        }

        return $enLibro;
    }

    private function motivoDeOmision(Cobro $cobro, ?Carbon $corte): ?string
    {
        if (! $corte || Carbon::parse($cobro->fecha_pago)->startOfDay()->lt($corte)) {
            return 'antes_de_corte';
        }
        $codigo = FinanzasParametros::cuentaDeRecibo($cobro->forma_pago, $cobro->referencia, $cobro->observaciones);
        if ($codigo === FinanzasParametros::SIN_ASIGNAR) {
            return 'sin_cuenta';
        }
        $activa = $this->cuentaActiva($codigo);

        return $activa ? null : 'sin_cuenta';
    }

    private function cuentaActiva(string $codigo): bool
    {
        static $activas = null;
        if ($activas === null) {
            $activas = FinCuenta::query()->where('activa', true)->pluck('codigo')->all();
        }

        return in_array($codigo, $activas, true);
    }

    public function revertirPorAnulacion(Cobro $cobro): void
    {
        $movimientos = FinMovimiento::query()
            ->where('referencia_tipo', 'cobro')
            ->where('referencia_id', $cobro->id)
            ->where('tipo', 'ingreso')
            ->whereNull('revertido_por_id')
            ->get();
        foreach ($movimientos as $movimiento) {
            $this->libro->revertir(
                $movimiento,
                'Reverso por anulación del recibo '.($cobro->numero_recibo ?: $cobro->id).'.',
                auth()->id() ? (int) auth()->user()->usuario_id : $cobro->usuario_id,
                true,
            );
        }
    }
}
