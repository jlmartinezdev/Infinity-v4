<?php

namespace App\Services\Finanzas;

use App\Models\FinCategoria;
use App\Models\FinCierre;
use App\Models\FinConciliacion;
use App\Models\FinCuenta;
use App\Models\FinEgreso;
use App\Models\FinLiquidacion;
use App\Models\FinLiquidacionCobro;
use App\Models\FinMovimiento;
use App\Models\FinRetiro;
use App\Models\FinTransferencia;
use App\Support\FinanzasParametros;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinanzasLibroService
{
    public function saldoHasta(int $cuentaId, Carbon $fecha): float
    {
        return round((float) FinMovimiento::query()
            ->where('cuenta_id', $cuentaId)
            ->whereDate('fecha_efectiva', '<=', $fecha->toDateString())
            ->selectRaw('COALESCE(SUM(importe * signo), 0) as saldo')
            ->value('saldo'), 2);
    }

    public function saldoActual(int $cuentaId): float
    {
        return round((float) FinMovimiento::query()
            ->where('cuenta_id', $cuentaId)
            ->selectRaw('COALESCE(SUM(importe * signo), 0) as saldo')
            ->value('saldo'), 2);
    }

    /**
     * @return array{ingresos: float, egresos: float, transferencias_entrada: float, transferencias_salida: float, retiros: float, ajustes: float}
     */
    public function desglose(int $cuentaId, Carbon $desde, Carbon $hasta): array
    {
        $filas = FinMovimiento::query()
            ->where('cuenta_id', $cuentaId)
            ->whereDate('fecha_efectiva', '>=', $desde->toDateString())
            ->whereDate('fecha_efectiva', '<=', $hasta->toDateString())
            ->get(['tipo', 'signo', 'importe', 'referencia_tipo']);

        $out = [
            'ingresos' => 0.0,
            'egresos' => 0.0,
            'transferencias_entrada' => 0.0,
            'transferencias_salida' => 0.0,
            'retiros' => 0.0,
            'ajustes' => 0.0,
        ];
        foreach ($filas as $fila) {
            $importe = (float) $fila->importe;
            if ($fila->tipo === 'ingreso') {
                $out['ingresos'] += $importe;
            } elseif ($fila->tipo === 'transferencia_entrada') {
                $out['transferencias_entrada'] += $importe;
            } elseif ($fila->tipo === 'transferencia_salida') {
                $out['transferencias_salida'] += $importe;
            } elseif ($fila->tipo === 'egreso' && $fila->referencia_tipo === 'retiro') {
                $out['retiros'] += $importe;
            } elseif ($fila->tipo === 'egreso') {
                $out['egresos'] += $importe;
            } elseif ($fila->tipo === 'ajuste') {
                $out['ajustes'] += $importe * (int) $fila->signo;
            }
        }

        return array_map(fn ($valor) => round($valor, 2), $out);
    }

    public function periodoCerrado(int $cuentaId, Carbon $fecha): bool
    {
        return FinCierre::query()
            ->where('cuenta_id', $cuentaId)
            ->whereDate('periodo_desde', '<=', $fecha->toDateString())
            ->whereDate('periodo_hasta', '>=', $fecha->toDateString())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, bool $forzar = false): FinMovimiento
    {
        $importe = round((float) $datos['importe'], 2);
        $signo = (int) $datos['signo'];
        if ($importe <= 0 || ! in_array($signo, [1, -1], true)) {
            throw new InvalidArgumentException('El importe del movimiento debe ser mayor a cero.');
        }
        $fecha = Carbon::parse($datos['fecha'])->startOfDay();
        $cuentaId = (int) $datos['cuenta_id'];
        if (! $forzar && $this->periodoCerrado($cuentaId, $fecha)) {
            throw new PeriodoCerradoException('Ese período ya está cerrado. La corrección se hace con un ajuste autorizado.');
        }

        return FinMovimiento::query()->create([
            'cuenta_id' => $cuentaId,
            'tipo' => $datos['tipo'],
            'signo' => $signo,
            'fecha_efectiva' => $fecha->toDateString(),
            'importe' => $importe,
            'concepto' => $datos['concepto'],
            'categoria_id' => $datos['categoria_id'] ?? null,
            'referencia_tipo' => $datos['referencia_tipo'] ?? null,
            'referencia_id' => $datos['referencia_id'] ?? null,
            'referencia_texto' => $datos['referencia_texto'] ?? null,
            'usuario_id' => $datos['usuario_id'] ?? null,
            'observaciones' => $datos['observaciones'] ?? null,
            'transferencia_id' => $datos['transferencia_id'] ?? null,
            'liquidacion_id' => $datos['liquidacion_id'] ?? null,
        ]);
    }

    public function revertir(FinMovimiento $movimiento, string $motivo, ?int $usuarioId, bool $forzar = true): FinMovimiento
    {
        if ($movimiento->revertido_por_id) {
            return FinMovimiento::query()->findOrFail($movimiento->revertido_por_id);
        }

        return DB::transaction(function () use ($movimiento, $motivo, $usuarioId, $forzar) {
            $reverso = $this->registrar([
                'cuenta_id' => $movimiento->cuenta_id,
                'tipo' => 'ajuste',
                'signo' => -1 * (int) $movimiento->signo,
                'fecha' => $movimiento->fecha_efectiva,
                'importe' => $movimiento->importe,
                'concepto' => 'Reverso: '.$movimiento->concepto,
                'categoria_id' => $movimiento->categoria_id,
                'referencia_tipo' => 'reverso',
                'referencia_id' => $movimiento->id,
                'referencia_texto' => $movimiento->referencia_texto,
                'usuario_id' => $usuarioId,
                'observaciones' => $motivo,
            ], $forzar);
            $movimiento->update(['revertido_por_id' => $reverso->id]);

            return $reverso;
        });
    }

    public function transferir(
        int $origenId,
        int $destinoId,
        float $importe,
        Carbon $fecha,
        ?string $referencia,
        ?string $observaciones,
        ?int $usuarioId,
        ?int $liquidacionId = null,
        bool $forzar = false,
    ): FinTransferencia {
        if ($origenId === $destinoId) {
            throw new InvalidArgumentException('La cuenta de origen y la de destino tienen que ser distintas.');
        }
        if ($importe <= 0) {
            throw new InvalidArgumentException('El importe de la transferencia debe ser mayor a cero.');
        }

        return DB::transaction(function () use ($origenId, $destinoId, $importe, $fecha, $referencia, $observaciones, $usuarioId, $liquidacionId, $forzar) {
            $transferencia = FinTransferencia::query()->create([
                'cuenta_origen_id' => $origenId,
                'cuenta_destino_id' => $destinoId,
                'importe' => round($importe, 2),
                'fecha' => $fecha->toDateString(),
                'numero_referencia' => $referencia,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
                'estado' => 'contabilizada',
                'liquidacion_id' => $liquidacionId,
            ]);
            $concepto = 'Transferencia '.$transferencia->id.($referencia ? ' · '.$referencia : '');
            $this->registrar([
                'cuenta_id' => $origenId,
                'tipo' => 'transferencia_salida',
                'signo' => -1,
                'fecha' => $fecha,
                'importe' => $importe,
                'concepto' => $concepto,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
                'referencia_texto' => $referencia,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
                'transferencia_id' => $transferencia->id,
                'liquidacion_id' => $liquidacionId,
            ], $forzar);
            $this->registrar([
                'cuenta_id' => $destinoId,
                'tipo' => 'transferencia_entrada',
                'signo' => 1,
                'fecha' => $fecha,
                'importe' => $importe,
                'concepto' => $concepto,
                'referencia_tipo' => 'transferencia',
                'referencia_id' => $transferencia->id,
                'referencia_texto' => $referencia,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
                'transferencia_id' => $transferencia->id,
                'liquidacion_id' => $liquidacionId,
            ], $forzar);

            return $transferencia;
        });
    }

    public function registrarEgreso(
        int $cuentaId,
        int $categoriaId,
        Carbon $fecha,
        float $importe,
        ?int $proveedorId,
        ?string $comprobante,
        ?string $observaciones,
        ?int $usuarioId,
        array $extra = [],
    ): FinEgreso {
        $categoria = FinCategoria::query()->findOrFail($categoriaId);
        if (! in_array($categoria->clase, ['opex', 'capex'], true)) {
            throw new InvalidArgumentException('El egreso tiene que ser un gasto operativo o una inversión.');
        }

        return DB::transaction(function () use ($cuentaId, $categoria, $fecha, $importe, $proveedorId, $comprobante, $observaciones, $usuarioId, $extra) {
            $concepto = trim((string) ($extra['concepto'] ?? ''));
            if ($concepto === '') {
                $concepto = $categoria->nombre.($comprobante ? ' · '.$comprobante : '');
            }
            $egreso = FinEgreso::query()->create([
                'cuenta_id' => $cuentaId,
                'categoria_id' => $categoria->id,
                'plantilla_id' => $extra['plantilla_id'] ?? null,
                'fecha' => $fecha->toDateString(),
                'importe' => round($importe, 2),
                'proveedor_id' => $proveedorId,
                'comprobante' => $comprobante,
                'usuario_id' => $usuarioId,
                'usuario_staff_id' => $extra['usuario_staff_id'] ?? null,
                'documento_path' => $extra['documento_path'] ?? null,
                'factura_path' => $extra['factura_path'] ?? null,
                'observaciones' => $observaciones,
                'estado' => 'contabilizado',
            ]);
            $this->registrar([
                'cuenta_id' => $cuentaId,
                'tipo' => 'egreso',
                'signo' => -1,
                'fecha' => $fecha,
                'importe' => $importe,
                'concepto' => $concepto,
                'categoria_id' => $categoria->id,
                'referencia_tipo' => 'egreso',
                'referencia_id' => $egreso->id,
                'referencia_texto' => $comprobante,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
            ]);

            return $egreso;
        });
    }

    public function registrarRetiro(
        string $persona,
        Carbon $fecha,
        float $importe,
        int $cuentaId,
        string $motivo,
        ?string $autorizacion,
        ?string $comprobante,
        ?int $usuarioId,
    ): FinRetiro {
        $categoria = $this->categoriaNombre('Retiro');

        return DB::transaction(function () use ($persona, $fecha, $importe, $cuentaId, $motivo, $autorizacion, $comprobante, $usuarioId, $categoria) {
            $retiro = FinRetiro::query()->create([
                'persona' => $persona,
                'fecha' => $fecha->toDateString(),
                'importe' => round($importe, 2),
                'cuenta_id' => $cuentaId,
                'motivo' => $motivo,
                'usuario_id' => $usuarioId,
                'autorizacion' => $autorizacion,
                'comprobante' => $comprobante,
                'estado' => 'contabilizado',
            ]);
            $this->registrar([
                'cuenta_id' => $cuentaId,
                'tipo' => 'egreso',
                'signo' => -1,
                'fecha' => $fecha,
                'importe' => $importe,
                'concepto' => 'Retiro '.$persona,
                'categoria_id' => $categoria->id,
                'referencia_tipo' => 'retiro',
                'referencia_id' => $retiro->id,
                'referencia_texto' => $comprobante,
                'usuario_id' => $usuarioId,
                'observaciones' => $motivo,
            ]);

            return $retiro;
        });
    }

    /**
     * @param  list<int>  $cobroIds
     */
    public function liquidar(array $cobroIds, float $comision, Carbon $fecha, string $referencia, ?string $observaciones, ?int $usuarioId): FinLiquidacion
    {
        $bancard = FinCuenta::query()->where('codigo', FinanzasParametros::CUENTA_BANCARD)->firstOrFail();
        $banco = FinCuenta::query()->where('codigo', FinanzasParametros::CUENTA_BANCO)->firstOrFail();
        $cobroIds = array_values(array_unique(array_map('intval', $cobroIds)));
        if ($cobroIds === []) {
            throw new InvalidArgumentException('Elegí al menos un cobro para liquidar.');
        }

        $movimientos = FinMovimiento::query()
            ->where('cuenta_id', $bancard->id)
            ->where('tipo', 'ingreso')
            ->where('referencia_tipo', 'cobro')
            ->whereIn('referencia_id', $cobroIds)
            ->whereNull('revertido_por_id')
            ->get();
        if ($movimientos->count() !== count($cobroIds)) {
            throw new InvalidArgumentException('Hay cobros que no están pendientes en Bancard.');
        }
        $ya = FinLiquidacionCobro::query()->whereIn('cobro_id', $cobroIds)->exists();
        if ($ya) {
            throw new InvalidArgumentException('Alguno de esos cobros ya fue liquidado.');
        }

        $bruto = round((float) $movimientos->sum('importe'), 2);
        $comision = round($comision, 2);
        if ($comision < 0 || $comision > $bruto) {
            throw new InvalidArgumentException('La comisión no puede superar el bruto ni ser negativa.');
        }
        $neto = round($bruto - $comision, 2);

        return DB::transaction(function () use ($movimientos, $cobroIds, $bruto, $comision, $neto, $fecha, $referencia, $observaciones, $usuarioId, $bancard, $banco) {
            $liquidacion = FinLiquidacion::query()->create([
                'fecha_liquidacion' => $fecha->toDateString(),
                'referencia' => $referencia,
                'bruto' => $bruto,
                'comision' => $comision,
                'neto' => $neto,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
                'estado' => 'contabilizada',
            ]);
            foreach ($movimientos as $movimiento) {
                FinLiquidacionCobro::query()->create([
                    'liquidacion_id' => $liquidacion->id,
                    'cobro_id' => $movimiento->referencia_id,
                    'importe' => $movimiento->importe,
                ]);
            }
            if ($comision > 0) {
                $this->registrar([
                    'cuenta_id' => $bancard->id,
                    'tipo' => 'egreso',
                    'signo' => -1,
                    'fecha' => $fecha,
                    'importe' => $comision,
                    'concepto' => 'Comisión Bancard '.$referencia,
                    'categoria_id' => $this->categoriaNombre('Comisión Bancard')->id,
                    'referencia_tipo' => 'liquidacion',
                    'referencia_id' => $liquidacion->id,
                    'referencia_texto' => $referencia,
                    'usuario_id' => $usuarioId,
                    'observaciones' => $observaciones,
                    'liquidacion_id' => $liquidacion->id,
                ]);
            }
            if ($neto > 0) {
                $this->transferir(
                    $bancard->id,
                    $banco->id,
                    $neto,
                    $fecha,
                    $referencia,
                    'Liquidación Bancard. El neto no es un ingreso nuevo.',
                    $usuarioId,
                    $liquidacion->id,
                );
            }

            return $liquidacion;
        });
    }

    public function conciliar(
        int $cuentaId,
        Carbon $fecha,
        float $saldoReal,
        ?string $observaciones,
        bool $ajustar,
        ?int $usuarioId,
    ): FinConciliacion {
        $sistema = $this->saldoHasta($cuentaId, $fecha);
        $diferencia = round($saldoReal - $sistema, 2);

        return DB::transaction(function () use ($cuentaId, $fecha, $saldoReal, $sistema, $diferencia, $observaciones, $ajustar, $usuarioId) {
            $conciliacion = FinConciliacion::query()->create([
                'cuenta_id' => $cuentaId,
                'fecha' => $fecha->toDateString(),
                'saldo_sistema' => $sistema,
                'saldo_real' => round($saldoReal, 2),
                'diferencia' => $diferencia,
                'usuario_id' => $usuarioId,
                'observaciones' => $observaciones,
            ]);
            if ($ajustar && abs($diferencia) > 0.009) {
                $ajuste = $this->registrar([
                    'cuenta_id' => $cuentaId,
                    'tipo' => 'ajuste',
                    'signo' => $diferencia > 0 ? 1 : -1,
                    'fecha' => $fecha,
                    'importe' => abs($diferencia),
                    'concepto' => 'Ajuste de conciliación',
                    'categoria_id' => $this->categoriaNombre('Ajuste de conciliación')->id,
                    'referencia_tipo' => 'conciliacion',
                    'referencia_id' => $conciliacion->id,
                    'usuario_id' => $usuarioId,
                    'observaciones' => $observaciones,
                ], true);
                $conciliacion->update(['ajuste_movimiento_id' => $ajuste->id]);
            }

            return $conciliacion;
        });
    }

    /**
     * @param  array<int, float>  $saldosReales
     */
    public function cerrar(string $tipo, Carbon $fecha, array $saldosReales, ?int $usuarioId): void
    {
        if ($tipo === 'mensual') {
            $desde = $fecha->copy()->startOfMonth();
            $hasta = $fecha->copy()->endOfMonth();
        } else {
            $desde = $fecha->copy()->startOfDay();
            $hasta = $fecha->copy()->startOfDay();
        }

        DB::transaction(function () use ($tipo, $desde, $hasta, $saldosReales, $usuarioId) {
            $cuentas = FinCuenta::query()->where('activa', true)->orderBy('orden')->get();
            foreach ($cuentas as $cuenta) {
                $existe = FinCierre::query()
                    ->where('cuenta_id', $cuenta->id)
                    ->where('tipo', $tipo)
                    ->whereDate('periodo_desde', $desde->toDateString())
                    ->exists();
                if ($existe) {
                    throw new InvalidArgumentException('La cuenta '.$cuenta->nombre.' ya tiene ese cierre.');
                }
                $desglose = $this->desglose($cuenta->id, $desde, $hasta);
                $calculado = $this->saldoHasta($cuenta->id, $hasta);
                $inicial = $this->saldoHasta($cuenta->id, $desde->copy()->subDay());
                if (! array_key_exists($cuenta->id, $saldosReales)) {
                    throw new InvalidArgumentException('Falta el saldo real de '.$cuenta->nombre.'.');
                }
                $real = round((float) $saldosReales[$cuenta->id], 2);
                FinCierre::query()->create([
                    'cuenta_id' => $cuenta->id,
                    'tipo' => $tipo,
                    'periodo_desde' => $desde->toDateString(),
                    'periodo_hasta' => $hasta->toDateString(),
                    'saldo_inicial' => $inicial,
                    'ingresos' => $desglose['ingresos'],
                    'egresos' => $desglose['egresos'],
                    'transferencias_entrada' => $desglose['transferencias_entrada'],
                    'transferencias_salida' => $desglose['transferencias_salida'],
                    'retiros' => $desglose['retiros'],
                    'ajustes' => $desglose['ajustes'],
                    'saldo_calculado' => $calculado,
                    'saldo_real' => $real,
                    'diferencia' => round($real - $calculado, 2),
                    'usuario_id' => $usuarioId,
                ]);
            }
        });
    }

    public function sincronizarSaldosIniciales(?int $usuarioId): void
    {
        $declarados = FinanzasParametros::saldosIniciales();
        $fecha = $declarados['fecha'] ?? null;
        DB::transaction(function () use ($declarados, $fecha, $usuarioId) {
            $cuentas = FinCuenta::query()->whereIn('codigo', [
                FinanzasParametros::CUENTA_EFECTIVO,
                FinanzasParametros::CUENTA_BANCO,
                FinanzasParametros::CUENTA_BANCARD,
            ])->get();
            foreach ($cuentas as $cuenta) {
                $importe = $declarados[$cuenta->codigo] ?? null;
                $abierto = FinMovimiento::query()
                    ->where('referencia_tipo', 'saldo_inicial')
                    ->where('referencia_texto', 'cuenta:'.$cuenta->id)
                    ->whereNull('revertido_por_id')
                    ->first();
                $hayImporte = $importe !== null && $importe > 0;
                if (! $hayImporte) {
                    if ($abierto) {
                        $this->revertir($abierto, 'Saldo inicial quitado en configuración.', $usuarioId, true);
                    }
                    continue;
                }
                if (! $fecha instanceof Carbon) {
                    throw new InvalidArgumentException('El saldo inicial necesita una fecha.');
                }
                if ($abierto && abs((float) $abierto->importe - $importe) < 0.01 && $abierto->fecha_efectiva->isSameDay($fecha)) {
                    continue;
                }
                if ($abierto) {
                    $this->revertir($abierto, 'Saldo inicial reemplazado en configuración.', $usuarioId, true);
                }
                $this->registrar([
                    'cuenta_id' => $cuenta->id,
                    'tipo' => 'ajuste',
                    'signo' => 1,
                    'fecha' => $fecha,
                    'importe' => $importe,
                    'concepto' => 'Saldo inicial '.$cuenta->nombre,
                    'categoria_id' => $this->categoriaNombre('Saldo inicial')->id,
                    'referencia_tipo' => 'saldo_inicial',
                    'referencia_texto' => 'cuenta:'.$cuenta->id,
                    'usuario_id' => $usuarioId,
                    'observaciones' => 'Declarado en configuración.',
                ], true);
            }
        });
    }

    public function sumaClase(string $clase, Carbon $desde, Carbon $hasta): float
    {
        return round((float) FinMovimiento::query()
            ->join('fin_categorias', 'fin_categorias.id', '=', 'fin_movimientos.categoria_id')
            ->where('fin_categorias.clase', $clase)
            ->where('fin_movimientos.tipo', 'egreso')
            ->whereNull('fin_movimientos.revertido_por_id')
            ->whereDate('fin_movimientos.fecha_efectiva', '>=', $desde->toDateString())
            ->whereDate('fin_movimientos.fecha_efectiva', '<=', $hasta->toDateString())
            ->sum('fin_movimientos.importe'), 2);
    }

    private function categoriaNombre(string $nombre): FinCategoria
    {
        return FinCategoria::query()->where('nombre', $nombre)->firstOrFail();
    }
}
