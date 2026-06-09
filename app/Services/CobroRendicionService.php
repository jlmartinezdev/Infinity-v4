<?php

namespace App\Services;

use App\Models\Cobro;
use App\Models\CobroRendicion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CobroRendicionService
{
    /** Cobros en efectivo aún no rendidos al tesorero. */
    public function queryPendientes(?int $usuarioCobradorId = null): Builder
    {
        $query = Cobro::query()
            ->where('forma_pago', 'efectivo')
            ->whereNull('cobro_rendicion_id');

        if ($usuarioCobradorId !== null) {
            $query->where('usuario_id', $usuarioCobradorId);
        }

        return $query;
    }

    /**
     * Resumen por cobrador con efectivo pendiente de rendir.
     *
     * @return Collection<int, array{usuario_id: int, nombre: string, cantidad: int, monto: float, desde: ?Carbon, hasta: ?Carbon}>
     */
    public function resumenPendientesPorCobrador(): Collection
    {
        $rows = $this->queryPendientes()
            ->whereNotNull('usuario_id')
            ->selectRaw('usuario_id, COUNT(*) as cantidad, SUM(monto) as monto, MIN(fecha_pago) as desde, MAX(fecha_pago) as hasta')
            ->groupBy('usuario_id')
            ->orderByDesc('monto')
            ->get();

        $usuarios = \App\Models\User::query()
            ->whereIn('usuario_id', $rows->pluck('usuario_id'))
            ->get(['usuario_id', 'name'])
            ->keyBy('usuario_id');

        return $rows->map(function ($row) use ($usuarios) {
            return [
                'usuario_id' => (int) $row->usuario_id,
                'nombre' => $usuarios->get($row->usuario_id)?->name ?? 'Usuario #'.$row->usuario_id,
                'cantidad' => (int) $row->cantidad,
                'monto' => (float) $row->monto,
                'desde' => $row->desde ? Carbon::parse($row->desde) : null,
                'hasta' => $row->hasta ? Carbon::parse($row->hasta) : null,
            ];
        });
    }

    /**
     * Registra la rendición: marca todos los cobros en efectivo pendientes del cobrador.
     */
    public function registrar(
        int $usuarioCobradorId,
        Carbon $fechaRendicion,
        ?int $usuarioTesoreroId,
        ?string $observaciones = null
    ): CobroRendicion {
        return DB::transaction(function () use ($usuarioCobradorId, $fechaRendicion, $usuarioTesoreroId, $observaciones) {
            $cobros = $this->queryPendientes($usuarioCobradorId)
                ->orderBy('fecha_pago')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($cobros->isEmpty()) {
                throw new \RuntimeException('No hay cobros en efectivo pendientes de rendir para este usuario.');
            }

            $monto = round((float) $cobros->sum('monto'), 2);

            $rendicion = CobroRendicion::create([
                'usuario_cobrador_id' => $usuarioCobradorId,
                'usuario_tesorero_id' => $usuarioTesoreroId,
                'monto' => $monto,
                'cantidad_cobros' => $cobros->count(),
                'fecha_rendicion' => $fechaRendicion,
                'observaciones' => $observaciones,
            ]);

            Cobro::whereIn('id', $cobros->pluck('id'))->update([
                'cobro_rendicion_id' => $rendicion->id,
            ]);

            return $rendicion->fresh(['cobrador', 'tesorero']);
        });
    }
}
