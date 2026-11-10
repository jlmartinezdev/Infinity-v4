<?php

namespace App\Services\Finanzas;

use App\Models\FinCategoria;
use App\Models\FinControlGastoMes;
use App\Models\FinCuenta;
use App\Models\FinEgreso;
use App\Models\FinMovimiento;
use App\Models\Gasto;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ControlGastoMesService
{
    public function __construct(private FinanzasLibroService $libro) {}

    public function mesDesde(?string $valor): Carbon
    {
        if (is_string($valor) && preg_match('/^\d{4}-\d{2}$/', $valor)) {
            return Carbon::createFromFormat('Y-m-d', $valor.'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    /**
     * @return array{
     *     mes: string,
     *     etiqueta: string,
     *     anterior: string,
     *     siguiente: string,
     *     estado: string,
     *     total: float,
     *     cantidad: int,
     *     pendiente: float,
     *     pendientes: int,
     *     desglose: list<array{nombre: string, total: float, cantidad: int}>,
     *     cuenta: ?string,
     *     cerrado_en: ?string,
     *     egreso_id: ?int
     * }
     */
    public function presentar(Carbon $mes): array
    {
        $mes = $mes->copy()->startOfMonth();
        $periodo = $this->periodo($mes);
        $resumen = $this->resumen($mes);
        $estado = $periodo?->estado;
        if ($estado !== 'cerrada' && $estado !== 'abierta') {
            $estado = $mes->lte(now()->startOfMonth()) ? 'abierta' : 'sin_abrir';
        }

        return [
            'mes' => $mes->format('Y-m'),
            'etiqueta' => $this->etiqueta($mes),
            'anterior' => $mes->copy()->subMonth()->format('Y-m'),
            'siguiente' => $mes->copy()->addMonth()->format('Y-m'),
            'estado' => $estado,
            'total' => $resumen['total'],
            'cantidad' => $resumen['cantidad'],
            'pendiente' => $resumen['pendiente'],
            'pendientes' => $resumen['pendientes'],
            'desglose' => $resumen['desglose'],
            'cuenta' => $periodo?->cuenta?->nombre,
            'cerrado_en' => $periodo?->cerrado_en?->format('d/m/Y H:i'),
            'egreso_id' => $periodo && $periodo->estado === 'cerrada' ? $periodo->fin_egreso_id : null,
        ];
    }

    public function estaCerrado(Carbon $fecha): bool
    {
        $periodo = $this->periodo($fecha->copy()->startOfMonth());

        return $periodo?->estado === 'cerrada';
    }

    public function asegurarAbierto(Carbon $fecha): void
    {
        $mes = $fecha->copy()->startOfMonth();
        if ($this->estaCerrado($mes)) {
            throw new InvalidArgumentException('El mes de '.$this->etiqueta($mes).' está cerrado. Abrilo de nuevo en Gastos diarios para modificarlo.');
        }
        if ($mes->gt(now()->startOfMonth()) && $this->periodo($mes)?->estado !== 'abierta') {
            throw new InvalidArgumentException('El mes de '.$this->etiqueta($mes).' todavía no está abierto.');
        }
    }

    public function abrir(Carbon $mes, ?int $usuarioId): FinControlGastoMes
    {
        $mes = $mes->copy()->startOfMonth();

        return DB::transaction(function () use ($mes, $usuarioId) {
            $periodo = FinControlGastoMes::query()
                ->where('anio', $mes->year)
                ->where('mes', $mes->month)
                ->lockForUpdate()
                ->first();

            if ($periodo && $periodo->estado === 'abierta') {
                throw new InvalidArgumentException('Ese mes ya está abierto.');
            }

            if ($periodo && $periodo->estado === 'cerrada' && $periodo->fin_egreso_id) {
                $movimiento = FinMovimiento::query()
                    ->where('referencia_tipo', 'egreso')
                    ->where('referencia_id', $periodo->fin_egreso_id)
                    ->where('tipo', 'egreso')
                    ->whereNull('revertido_por_id')
                    ->first();
                if ($movimiento) {
                    $this->libro->revertir($movimiento, 'Reapertura de gastos diarios '.$this->etiqueta($mes), $usuarioId);
                }
                FinEgreso::query()->whereKey($periodo->fin_egreso_id)->update(['estado' => 'revertido']);
            }

            $datos = [
                'estado' => 'abierta',
                'cuenta_id' => null,
                'importe' => null,
                'fin_egreso_id' => null,
                'cerrado_por' => null,
                'cerrado_en' => null,
                'abierto_por' => $usuarioId,
                'abierto_en' => now(),
            ];

            if ($periodo) {
                $periodo->update($datos);

                return $periodo;
            }

            return FinControlGastoMes::query()->create($datos + [
                'anio' => $mes->year,
                'mes' => $mes->month,
            ]);
        });
    }

    public function cerrar(Carbon $mes, ?int $cuentaId, ?int $usuarioId): FinControlGastoMes
    {
        $mes = $mes->copy()->startOfMonth();

        return DB::transaction(function () use ($mes, $cuentaId, $usuarioId) {
            $periodo = FinControlGastoMes::query()
                ->where('anio', $mes->year)
                ->where('mes', $mes->month)
                ->lockForUpdate()
                ->first();

            if ($periodo && $periodo->estado === 'cerrada') {
                throw new InvalidArgumentException('Ese mes ya está cerrado.');
            }
            if ($mes->gt(now()->startOfMonth()) && $periodo?->estado !== 'abierta') {
                throw new InvalidArgumentException('Abrí el mes antes de cerrarlo.');
            }

            $resumen = $this->resumen($mes);
            $importe = $resumen['total'];
            $egresoId = null;
            if ($importe > 0) {
                $cuenta = FinCuenta::query()->whereKey($cuentaId)->where('activa', true)->first();
                if (! $cuenta) {
                    throw new InvalidArgumentException('Elegí la cuenta de la que salió el dinero.');
                }
                $categoriaId = (int) FinCategoria::query()->where('nombre', 'Gasto operativo')->value('id');
                $finMes = $mes->copy()->endOfMonth();
                $hoy = now()->startOfDay();
                $fecha = $hoy->gte($mes) && $hoy->lte($finMes->copy()->startOfDay()) ? now() : $finMes;
                $egreso = $this->libro->registrarEgreso(
                    $cuenta->id,
                    $categoriaId,
                    $fecha,
                    $importe,
                    null,
                    'CG-'.$mes->format('Y-m'),
                    $this->textoResumen($resumen),
                    $usuarioId,
                    ['concepto' => 'Gastos diarios '.$this->etiqueta($mes)],
                );
                $egresoId = $egreso->id;
                $cuentaId = $cuenta->id;
            }

            $datos = [
                'estado' => 'cerrada',
                'cuenta_id' => $importe > 0 ? $cuentaId : null,
                'importe' => $importe,
                'fin_egreso_id' => $egresoId,
                'cerrado_por' => $usuarioId,
                'cerrado_en' => now(),
            ];
            if (! $periodo || ! $periodo->abierto_en) {
                $datos['abierto_por'] = $periodo?->abierto_por ?? $usuarioId;
                $datos['abierto_en'] = $periodo?->abierto_en ?? now();
            }

            if ($periodo) {
                $periodo->update($datos);

                return $periodo->fresh('cuenta');
            }

            return FinControlGastoMes::query()->create($datos + [
                'anio' => $mes->year,
                'mes' => $mes->month,
            ]);
        });
    }

    /**
     * @return array{total: float, cantidad: int, pendiente: float, pendientes: int, desglose: list<array{nombre: string, total: float, cantidad: int}>}
     */
    private function resumen(Carbon $mes): array
    {
        $base = Gasto::query()->whereYear('fecha', $mes->year)->whereMonth('fecha', $mes->month);
        $desglose = (clone $base)
            ->selectRaw('categoria_gasto_id, SUM(monto) as total, COUNT(*) as cantidad')
            ->groupBy('categoria_gasto_id')
            ->with('categoria')
            ->orderByDesc('total')
            ->get()
            ->map(fn (Gasto $fila) => [
                'nombre' => $fila->categoria?->nombre ?? 'Sin categoría',
                'total' => (float) $fila->total,
                'cantidad' => (int) $fila->cantidad,
            ])
            ->all();

        return [
            'total' => round((float) (clone $base)->sum('monto'), 2),
            'cantidad' => (int) (clone $base)->count(),
            'pendiente' => round((float) (clone $base)->where('pagado', false)->sum('monto'), 2),
            'pendientes' => (int) (clone $base)->where('pagado', false)->count(),
            'desglose' => $desglose,
        ];
    }

    /**
     * @param  array{total: float, cantidad: int, pendiente: float, pendientes: int, desglose: list<array{nombre: string, total: float, cantidad: int}>}  $resumen
     */
    private function textoResumen(array $resumen): string
    {
        $lineas = collect($resumen['desglose'])->map(function (array $fila) {
            return $fila['nombre'].' '.number_format($fila['total'], 0, ',', '.').' ('.$fila['cantidad'].')';
        })->implode("\n");
        $pie = $resumen['cantidad'].' registro'.($resumen['cantidad'] === 1 ? '' : 's');
        if ($resumen['pendientes'] > 0) {
            $pie .= '. Pendiente de pago: '.number_format($resumen['pendiente'], 0, ',', '.');
        }

        return trim($lineas."\n".$pie);
    }

    private function periodo(Carbon $mes): ?FinControlGastoMes
    {
        return FinControlGastoMes::query()
            ->with('cuenta')
            ->where('anio', $mes->year)
            ->where('mes', $mes->month)
            ->first();
    }

    private function etiqueta(Carbon $mes): string
    {
        $nombres = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return ucfirst($nombres[$mes->month]).' '.$mes->year;
    }
}
