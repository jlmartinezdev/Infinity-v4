<?php

namespace App\Services\Loyalty;

use App\Models\Canje;
use App\Models\Premio;
use Illuminate\Support\Facades\DB;

class CanjeService
{
    public function __construct(
        private readonly PuntosService $puntos
    ) {}

    /**
     * Crea canje. La modalidad la define el tipo del premio.
     * $modalidadLegacy se ignora si el premio tiene tipo (compat API vieja).
     */
    public function crear(int $clienteId, int $premioId, ?string $modalidadLegacy = null): Canje
    {
        if (! $this->puntos->puedeCanjear($clienteId)) {
            throw new \InvalidArgumentException('Ya realizaste el canje permitido este mes.');
        }

        return DB::transaction(function () use ($clienteId, $premioId, $modalidadLegacy) {
            $premio = Premio::query()->lockForUpdate()->find($premioId);
            if (! $premio || ! $premio->activo) {
                throw new \InvalidArgumentException('Premio no disponible.');
            }
            if ((int) $premio->stock < 1) {
                throw new \InvalidArgumentException('Sin stock para este premio.');
            }

            $modalidad = $premio->modalidadCanje();
            // Legacy: si el premio no tiene tipo útil y mandaron modalidad válida, respetarla.
            if (
                blank($premio->tipo)
                && $modalidadLegacy
                && in_array($modalidadLegacy, [Canje::MOD_RETIRO, Canje::MOD_DESCUENTO], true)
            ) {
                $modalidad = $modalidadLegacy;
            }

            if ($premio->esDescuentoFactura()) {
                $tienePct = $premio->descuento_porcentaje !== null && (float) $premio->descuento_porcentaje > 0;
                $tieneMonto = $premio->descuento_monto !== null && (float) $premio->descuento_monto > 0;
                if (! $tienePct && ! $tieneMonto) {
                    throw new \InvalidArgumentException(
                        'Este premio de descuento no tiene porcentaje ni monto configurado.'
                    );
                }
            }

            $requeridos = (int) $premio->puntos_requeridos;
            if ($this->puntos->saldo($clienteId) < $requeridos) {
                throw new \InvalidArgumentException('No tenés puntos suficientes para este canje.');
            }

            $premio->stock = (int) $premio->stock - 1;
            $premio->save();

            $canje = Canje::create([
                'cliente_id' => $clienteId,
                'premio_id' => $premio->id,
                'puntos_usados' => $requeridos,
                'modalidad' => $modalidad,
                'estado' => Canje::ESTADO_PENDIENTE,
            ]);

            $this->puntos->debitar($clienteId, $requeridos, 'Canje: '.$premio->nombre, 'canje', [
                'referencia_tipo' => 'canje',
                'referencia_id' => $canje->id,
                'meta' => [
                    'premio_tipo' => $premio->tipo,
                    'descuento_porcentaje' => $premio->descuento_porcentaje,
                    'descuento_monto' => $premio->descuento_monto,
                ],
            ]);

            return $canje->load('premio');
        });
    }

    public function marcarPreparacion(Canje $canje, ?int $staffId = null): Canje
    {
        return $this->transicionar($canje, Canje::ESTADO_EN_PREPARACION, $staffId, [
            'prepared_at' => now(),
        ]);
    }

    public function marcarListo(Canje $canje, ?int $staffId = null): Canje
    {
        return $this->transicionar($canje, Canje::ESTADO_LISTO, $staffId, [
            'ready_at' => now(),
        ]);
    }

    public function marcarEntregado(Canje $canje, ?int $staffId = null): Canje
    {
        if ($canje->modalidad !== Canje::MOD_RETIRO) {
            throw new \InvalidArgumentException('Solo aplica a retiro en oficina.');
        }

        return $this->transicionar($canje, Canje::ESTADO_ENTREGADO, $staffId, [
            'completed_at' => now(),
        ]);
    }

    public function marcarAplicado(Canje $canje, ?int $staffId = null): Canje
    {
        if ($canje->modalidad !== Canje::MOD_DESCUENTO) {
            throw new \InvalidArgumentException('Solo aplica a descuento en factura.');
        }

        return $this->transicionar($canje, Canje::ESTADO_APLICADO, $staffId, [
            'completed_at' => now(),
        ]);
    }

    public function cancelar(Canje $canje, ?int $staffId = null, ?string $motivo = null): Canje
    {
        if ($canje->estado === Canje::ESTADO_CANCELADO) {
            return $canje;
        }
        if (in_array($canje->estado, [Canje::ESTADO_ENTREGADO, Canje::ESTADO_APLICADO], true)) {
            throw new \InvalidArgumentException('No se puede cancelar un canje ya completado.');
        }

        return DB::transaction(function () use ($canje, $staffId, $motivo) {
            $canje = Canje::query()->lockForUpdate()->findOrFail($canje->id);

            $premio = Premio::query()->lockForUpdate()->find($canje->premio_id);
            if ($premio) {
                $premio->stock = (int) $premio->stock + 1;
                $premio->save();
            }

            $this->puntos->acreditar(
                (int) $canje->cliente_id,
                (int) $canje->puntos_usados,
                'Reversa canje #'.$canje->id,
                'reversa',
                [
                    'referencia_tipo' => 'canje',
                    'referencia_id' => $canje->id,
                    'created_by' => $staffId,
                ]
            );

            $canje->update([
                'estado' => Canje::ESTADO_CANCELADO,
                'staff_user_id' => $staffId ?? $canje->staff_user_id,
                'cancelled_at' => now(),
                'notas' => trim(($canje->notas ? $canje->notas."\n" : '').($motivo ?? 'Cancelado')),
            ]);

            return $canje->fresh(['premio', 'cliente']);
        });
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transicionar(Canje $canje, string $nuevoEstado, ?int $staffId, array $extra = []): Canje
    {
        if ($canje->estado === Canje::ESTADO_CANCELADO) {
            throw new \InvalidArgumentException('El canje está cancelado.');
        }

        $canje->update(array_merge($extra, [
            'estado' => $nuevoEstado,
            'staff_user_id' => $staffId ?? $canje->staff_user_id,
        ]));

        return $canje->fresh(['premio', 'cliente']);
    }
}
