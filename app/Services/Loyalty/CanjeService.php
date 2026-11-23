<?php

namespace App\Services\Loyalty;

use App\Models\Canje;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
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
            if (! $premio->tieneStockDisponible()) {
                throw new \InvalidArgumentException('Sin stock para este premio.');
            }

            $canjesDelPremio = Canje::query()
                ->where('cliente_id', $clienteId)
                ->where('premio_id', $premio->id)
                ->where('estado', '!=', Canje::ESTADO_CANCELADO);
            if ($premio->una_vez_por_cliente && (clone $canjesDelPremio)->exists()) {
                throw new \InvalidArgumentException('Este premio solo se puede canjear una vez.');
            }
            if ($premio->tope_anual_por_cliente !== null) {
                $usados = (clone $canjesDelPremio)->whereYear('created_at', now()->year)->count();
                if ($usados >= (int) $premio->tope_anual_por_cliente) {
                    throw new \InvalidArgumentException('Alcanzaste el tope anual de canjes para este premio.');
                }
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
            if ($requeridos < 1 && ! $premio->esDescuentoFactura()) {
                throw new \InvalidArgumentException('Este premio no se puede canjear sin puntos.');
            }
            if ($requeridos > 0 && $this->puntos->saldo($clienteId) < $requeridos) {
                throw new \InvalidArgumentException('No tenés puntos suficientes para este canje.');
            }

            if (! $premio->tieneStockIlimitado()) {
                $premio->stock = (int) $premio->stock - 1;
                $premio->save();
            }

            $estado = Canje::ESTADO_PENDIENTE;
            $extra = [];
            if ($premio->esAutomatico() || $premio->esSorteo()) {
                $estado = Canje::ESTADO_APLICADO;
                $extra['completed_at'] = now();
            } elseif ($premio->necesitaAprobacion()) {
                $estado = Canje::ESTADO_PENDIENTE;
            }

            $canje = Canje::create(array_merge([
                'cliente_id' => $clienteId,
                'premio_id' => $premio->id,
                'puntos_usados' => $requeridos,
                'modalidad' => $modalidad,
                'estado' => $estado,
            ], $extra));

            if ($requeridos > 0) {
                $this->puntos->debitar($clienteId, $requeridos, 'Canje: '.$premio->nombre, 'canje', [
                    'referencia_tipo' => 'canje',
                    'referencia_id' => $canje->id,
                    'meta' => [
                        'premio_tipo' => $premio->tipo,
                        'descuento_porcentaje' => $premio->descuento_porcentaje,
                        'descuento_monto' => $premio->descuento_monto,
                        'etiqueta' => $premio->etiqueta,
                        'tier' => $premio->tier,
                    ],
                ]);
            }

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
        if (! in_array($canje->modalidad, [Canje::MOD_RETIRO, Canje::MOD_APROBACION], true)) {
            throw new \InvalidArgumentException('Solo aplica a retiro en oficina o aprobación.');
        }

        return $this->transicionar($canje, Canje::ESTADO_ENTREGADO, $staffId, [
            'completed_at' => now(),
        ]);
    }

    /**
     * El staff habilita un descuento que pedía aprobación. El monto entra en la próxima factura.
     */
    public function aprobarDescuento(Canje $canje, ?int $staffId = null): Canje
    {
        $canje->loadMissing('premio');
        if ($canje->modalidad !== Canje::MOD_DESCUENTO) {
            throw new \InvalidArgumentException('Solo se aprueba un descuento en factura.');
        }
        if (! $canje->premio?->necesitaAprobacion()) {
            throw new \InvalidArgumentException('Este descuento no requiere aprobación. Entra solo en la próxima factura.');
        }
        if ($canje->estado !== Canje::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Este canje no está pendiente de aprobación.');
        }

        return $this->transicionar($canje, Canje::ESTADO_APROBADO, $staffId);
    }

    /**
     * Descuenta en la factura que se está emitiendo los canjes de descuento ya habilitados.
     * Un solo canje de cada premio por factura. Los que piden aprobación esperan estado APROBADO.
     */
    public function aplicarDescuentosEnFactura(
        FacturaInterna $factura,
        float &$subtotal,
        float &$totalImpuestos,
        float &$total,
        ?int $impuestoId
    ): void {
        if (($factura->tipo_factura ?? FacturaInterna::TIPO_SERVICIO) !== FacturaInterna::TIPO_SERVICIO) {
            return;
        }
        if ($total <= 0.009) {
            return;
        }

        $canjes = Canje::query()
            ->with('premio')
            ->where('cliente_id', $factura->cliente_id)
            ->where('modalidad', Canje::MOD_DESCUENTO)
            ->whereNull('factura_interna_id')
            ->whereIn('estado', [Canje::ESTADO_PENDIENTE, Canje::ESTADO_APROBADO])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $premiosEnFactura = Canje::query()
            ->where('factura_interna_id', $factura->id)
            ->pluck('premio_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $aplico = false;

        foreach ($canjes as $canje) {
            if ($total <= 0.009) {
                break;
            }
            $premio = $canje->premio;
            if (! $premio || ! $premio->esDescuentoFactura()) {
                continue;
            }
            if (in_array((int) $premio->id, $premiosEnFactura, true)) {
                continue;
            }
            $habilitado = $premio->necesitaAprobacion()
                ? $canje->estado === Canje::ESTADO_APROBADO
                : in_array($canje->estado, [Canje::ESTADO_PENDIENTE, Canje::ESTADO_APROBADO], true);
            if (! $habilitado) {
                continue;
            }

            $monto = $this->montoDescuento($premio, $total);
            if ($monto <= 0.009) {
                continue;
            }

            [$descSubtotal, $descImpuesto] = $this->partirDescuento($monto, $subtotal, $totalImpuestos, $total);
            FacturaInternaDetalle::create([
                'factura_interna_id' => $factura->id,
                'impuesto_id' => $impuestoId,
                'servicio_id' => null,
                'descripcion' => $this->descripcionDescuento($premio),
                'cantidad' => 1,
                'precio_unitario' => -$monto,
                'subtotal' => -$descSubtotal,
                'porcentaje_impuesto' => 0,
                'monto_impuesto' => -$descImpuesto,
                'total' => -$monto,
            ]);

            $subtotal = max(0, round($subtotal - $descSubtotal, 2));
            $totalImpuestos = max(0, round($totalImpuestos - $descImpuesto, 2));
            $total = max(0, round($total - $monto, 2));
            $premiosEnFactura[] = (int) $premio->id;
            $aplico = true;

            $nota = 'Aplicado en factura #'.$factura->id;
            $canje->update([
                'estado' => Canje::ESTADO_APLICADO,
                'factura_interna_id' => $factura->id,
                'completed_at' => now(),
                'notas' => trim(($canje->notas ? $canje->notas."\n" : '').$nota),
            ]);
        }

        if ($aplico) {
            $factura->update([
                'subtotal' => $subtotal,
                'total_impuestos' => $totalImpuestos,
                'total' => $total,
            ]);
        }
    }

    public function marcarAplicado(Canje $canje, ?int $staffId = null): Canje
    {
        if (! in_array($canje->modalidad, [
            Canje::MOD_DESCUENTO,
            Canje::MOD_AUTOMATICO,
            Canje::MOD_SORTEO,
            Canje::MOD_APROBACION,
        ], true)) {
            throw new \InvalidArgumentException('Modalidad no admite marcar como aplicado.');
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
            if ($premio && ! $premio->tieneStockIlimitado()) {
                $premio->stock = (int) $premio->stock + 1;
                $premio->save();
            }

            if ((int) $canje->puntos_usados > 0) {
                $this->puntos->acreditar(
                    (int) $canje->cliente_id,
                    (int) $canje->puntos_usados,
                    'Reversa canje #'.$canje->id,
                    'reversa',
                    [
                        'referencia_tipo' => 'canje',
                        'referencia_id' => $canje->id,
                        'created_by' => $staffId,
                        'origen_lote' => \App\Models\PuntosLote::ORIGEN_REVERSA,
                        'dias_vencimiento' => null,
                    ]
                );
            }

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

    private function montoDescuento(Premio $premio, float $total): float
    {
        $monto = 0.0;
        $porcentaje = (float) ($premio->descuento_porcentaje ?? 0);
        $fijo = (float) ($premio->descuento_monto ?? 0);
        if ($porcentaje > 0) {
            $monto += round($total * $porcentaje / 100, 2);
        }
        if ($fijo > 0) {
            $monto += round($fijo, 2);
        }

        return min(round($monto, 2), round($total, 2));
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function partirDescuento(float $monto, float $subtotal, float $totalImpuestos, float $total): array
    {
        if ($monto >= $total - 0.009) {
            return [round($subtotal, 2), round($totalImpuestos, 2)];
        }

        $ratio = $total > 0 ? $monto / $total : 0;
        $descSubtotal = round($subtotal * $ratio, 2);
        $descImpuesto = round($totalImpuestos * $ratio, 2);
        $ajuste = round($monto - ($descSubtotal + $descImpuesto), 2);
        if (abs($ajuste) >= 0.01) {
            $descImpuesto = round($descImpuesto + $ajuste, 2);
        }

        return [$descSubtotal, $descImpuesto];
    }

    private function descripcionDescuento(Premio $premio): string
    {
        $partes = [];
        $porcentaje = (float) ($premio->descuento_porcentaje ?? 0);
        $fijo = (float) ($premio->descuento_monto ?? 0);
        if ($porcentaje > 0) {
            $partes[] = rtrim(rtrim(number_format($porcentaje, 2, '.', ''), '0'), '.').'%';
        }
        if ($fijo > 0) {
            $partes[] = number_format($fijo, 0, ',', '.').' Gs.';
        }
        $detalle = $partes !== [] ? ' ('.implode(' + ', $partes).')' : '';

        return 'Descuento '.$premio->nombre.$detalle;
    }
}
