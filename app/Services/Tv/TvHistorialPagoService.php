<?php

namespace App\Services\Tv;

use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\TvCuentaAsignacion;
use App\Models\User;

class TvHistorialPagoService
{
    /**
     * @return array<string, mixed>
     */
    public function historialAsignacion(TvCuentaAsignacion $asignacion, ?User $usuario = null): array
    {
        $asignacion->loadMissing(['servicio.cliente', 'tvCuenta']);
        $servicio = $asignacion->servicio;
        $cliente = $servicio?->cliente;
        $servicioId = (int) ($asignacion->servicio_id ?? 0);

        if ($servicioId <= 0) {
            return $this->respuestaVacia($asignacion, $cliente, $servicio);
        }

        $detalles = FacturaInternaDetalle::query()
            ->where('servicio_id', $servicioId)
            ->where(function ($q) {
                $q->where('descripcion', 'like', 'Servicio especial%')
                    ->orWhereHas('facturaInterna', fn ($f) => $f->where(
                        'tipo_factura',
                        FacturaInterna::TIPO_SERVICIO_ESPECIAL
                    ));
            })
            ->whereHas('facturaInterna', fn ($f) => $f->whereNotIn('estado', ['anulada', 'cancelada']))
            ->with([
                'facturaInterna.cobros' => fn ($q) => $q->orderByDesc('fecha_pago')->orderByDesc('id'),
            ])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $porFactura = $detalles->groupBy('factura_interna_id');
        $estadosFactura = FacturaInterna::estados();
        $lineas = [];

        foreach ($porFactura as $facturaId => $items) {
            /** @var FacturaInterna|null $factura */
            $factura = $items->first()?->facturaInterna;
            if (! $factura) {
                continue;
            }

            $montoTv = round((float) $items->sum('total'), 2);
            $periodo = $this->etiquetaPeriodo($factura);
            $cobros = $factura->cobros->map(function ($cobro) use ($usuario) {
                return [
                    'id' => (int) $cobro->id,
                    'fecha' => $cobro->fecha_pago?->format('d/m/Y') ?? '',
                    'monto' => (float) ($cobro->pivot->monto ?? 0),
                    'url' => $usuario?->tienePermiso('cobros.ver')
                        ? route('cobros.show', $cobro)
                        : null,
                ];
            })->values()->all();

            $lineas[] = [
                'factura_id' => (int) $factura->id,
                'fecha_emision' => $factura->fecha_emision?->format('d/m/Y') ?? '',
                'periodo' => $periodo,
                'tipo' => $factura->etiquetaTipoFactura(),
                'descripcion' => $items->pluck('descripcion')->filter()->first() ?? 'App TV',
                'monto_tv' => $montoTv,
                'estado' => (string) $factura->estado,
                'estado_label' => $estadosFactura[$factura->estado] ?? $factura->estado,
                'saldo_pendiente' => (float) $factura->saldo_pendiente,
                'fecha_pago' => $factura->fecha_pago?->format('d/m/Y'),
                'url_factura' => $usuario?->tienePermiso('factura-interna.ver')
                    ? route('factura-internas.show', $factura)
                    : null,
                'cobros' => $cobros,
            ];
        }

        usort($lineas, function (array $a, array $b) {
            $cmp = strcmp($b['fecha_emision'], $a['fecha_emision']);

            return $cmp !== 0 ? $cmp : $b['factura_id'] <=> $a['factura_id'];
        });

        $totalFacturado = round(collect($lineas)->sum('monto_tv'), 2);
        $totalPendiente = round(collect($lineas)->sum('saldo_pendiente'), 2);

        $nombreCliente = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));

        return [
            'cliente' => [
                'id' => $cliente?->cliente_id,
                'nombre' => $nombreCliente !== '' ? $nombreCliente : null,
                'cedula' => $cliente?->cedula,
                'url' => $cliente && $usuario?->tienePermiso('clientes.ver')
                    ? route('clientes.detalle', $cliente)
                    : null,
            ],
            'servicio_id' => $servicioId,
            'asignacion' => [
                'id' => (int) $asignacion->id,
                'perfil_numero' => $asignacion->perfil_numero,
                'fecha_activacion' => $asignacion->fecha_activacion?->format('d/m/Y'),
                'es_promo' => (bool) ($asignacion->es_promo ?? false),
                'precio_aplicado' => $asignacion->precio_aplicado !== null
                    ? (float) $asignacion->precio_aplicado
                    : null,
            ],
            'cuenta_tv' => [
                'usuario_app' => $asignacion->tvCuenta?->usuario_app,
                'app' => $asignacion->tvCuenta?->aplicacion,
            ],
            'resumen' => [
                'total_facturado' => $totalFacturado,
                'total_pendiente' => $totalPendiente,
                'cantidad_facturas' => count($lineas),
            ],
            'lineas' => $lineas,
        ];
    }

    private function etiquetaPeriodo(FacturaInterna $factura): string
    {
        if ($factura->esServicioEspecial()) {
            return 'Servicio especial';
        }

        if ($factura->periodo_desde && $factura->periodo_hasta) {
            return $factura->periodo_desde->format('d/m/Y').' – '.$factura->periodo_hasta->format('d/m/Y');
        }

        return '—';
    }

    /**
     * @return array<string, mixed>
     */
    private function respuestaVacia(
        TvCuentaAsignacion $asignacion,
        mixed $cliente,
        mixed $servicio
    ): array {
        $nombreCliente = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));

        return [
            'cliente' => [
                'id' => $cliente?->cliente_id,
                'nombre' => $nombreCliente !== '' ? $nombreCliente : null,
                'cedula' => $cliente?->cedula,
                'url' => null,
            ],
            'servicio_id' => (int) ($servicio?->servicio_id ?? 0),
            'asignacion' => [
                'id' => (int) $asignacion->id,
                'perfil_numero' => $asignacion->perfil_numero,
                'fecha_activacion' => $asignacion->fecha_activacion?->format('d/m/Y'),
                'es_promo' => (bool) ($asignacion->es_promo ?? false),
                'precio_aplicado' => null,
            ],
            'cuenta_tv' => [
                'usuario_app' => $asignacion->tvCuenta?->usuario_app,
                'app' => $asignacion->tvCuenta?->aplicacion,
            ],
            'resumen' => [
                'total_facturado' => 0,
                'total_pendiente' => 0,
                'cantidad_facturas' => 0,
            ],
            'lineas' => [],
        ];
    }
}
