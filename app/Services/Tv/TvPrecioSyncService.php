<?php

namespace App\Services\Tv;

use App\Models\FacturaDetalle;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\Impuesto;
use App\Models\Servicio;
use App\Models\TvCuenta;
use App\Models\TvCuentaAsignacion;
use App\Services\CobrosResumenService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class TvPrecioSyncService
{
    /**
     * Recalcula app_tv, cantidad_perfil_app y precio_app según asignaciones del servicio.
     */
    public function sincronizarServicio(int $servicioId): void
    {
        $asignaciones = TvCuentaAsignacion::query()
            ->where('servicio_id', $servicioId)
            ->get();

        if ($asignaciones->isEmpty()) {
            Servicio::where('servicio_id', $servicioId)->update([
                'app_tv' => false,
                'cantidad_perfil_app' => null,
                'precio_app' => null,
            ]);

            return;
        }

        $suma = 0.0;
        if (Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
            foreach ($asignaciones as $a) {
                $suma += (float) ($a->precio_aplicado ?? 0);
            }
        }

        Servicio::where('servicio_id', $servicioId)->update([
            'app_tv' => true,
            'cantidad_perfil_app' => $asignaciones->count(),
            'precio_app' => $suma > 0 ? round($suma, 2) : null,
        ]);
    }

    /**
     * Copia precio de catálogo (tv_cuentas) a precio_aplicado de asignaciones no promo
     * y resincroniza servicios.precio_app.
     *
     * @param  list<int>|null  $soloSlots  Si se indica, solo esos perfil_numero
     * @return array{
     *     asignaciones_revisadas: int,
     *     asignaciones_actualizadas: int,
     *     servicios_sincronizados: int,
     *     cambios: list<array<string, mixed>>
     * }
     */
    public function reconciliarAsignaciones(
        ?int $tvCuentaId = null,
        bool $incluirCero = true,
        bool $aplicar = false,
        ?array $soloSlots = null,
    ): array {
        if (! Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
            return [
                'asignaciones_revisadas' => 0,
                'asignaciones_actualizadas' => 0,
                'servicios_sincronizados' => 0,
                'cambios' => [],
            ];
        }

        $query = TvCuentaAsignacion::query()
            ->with('tvCuenta')
            ->whereNotNull('perfil_numero')
            ->where(function ($q) {
                $q->where('es_promo', false)->orWhereNull('es_promo');
            });

        if ($tvCuentaId !== null) {
            $query->where('tv_cuenta_id', $tvCuentaId);
        }
        if ($soloSlots !== null && $soloSlots !== []) {
            $query->whereIn('perfil_numero', $soloSlots);
        }

        $asignaciones = $query->orderBy('id')->get();
        $cambios = [];
        $serviciosAfectados = [];

        foreach ($asignaciones as $asignacion) {
            $cuenta = $asignacion->tvCuenta;
            if (! $cuenta instanceof TvCuenta) {
                continue;
            }

            $catalogo = $cuenta->precioSlot((int) $asignacion->perfil_numero);
            $actual = $this->precioNormalizado($asignacion->precio_aplicado);
            $esperado = $this->precioNormalizado($catalogo) ?? 0.0;

            if ($actual === $esperado) {
                continue;
            }

            // Por defecto no “inventa” cobro en asignaciones en 0 (posible promo sin flag).
            if (! $incluirCero && ($actual === null || $actual <= 0.009)) {
                continue;
            }

            $cambios[] = [
                'asignacion_id' => (int) $asignacion->id,
                'tv_cuenta_id' => (int) $asignacion->tv_cuenta_id,
                'servicio_id' => (int) $asignacion->servicio_id,
                'perfil_numero' => (int) $asignacion->perfil_numero,
                'precio_anterior' => $actual,
                'precio_nuevo' => $esperado,
            ];

            if ($aplicar) {
                $asignacion->update(['precio_aplicado' => $esperado]);
                $serviciosAfectados[(int) $asignacion->servicio_id] = true;
            }
        }

        $serviciosSync = 0;
        if ($aplicar) {
            // También resincroniza servicios tocados por desfase cantidad/precio aunque no haya cambio de asignación.
            if ($tvCuentaId !== null) {
                $ids = TvCuentaAsignacion::query()
                    ->where('tv_cuenta_id', $tvCuentaId)
                    ->pluck('servicio_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->all();
                foreach ($ids as $servicioId) {
                    $serviciosAfectados[$servicioId] = true;
                }
            }

            foreach (array_keys($serviciosAfectados) as $servicioId) {
                if ($servicioId <= 0) {
                    continue;
                }
                $this->sincronizarServicio($servicioId);
                $serviciosSync++;
            }
        }

        return [
            'asignaciones_revisadas' => $asignaciones->count(),
            'asignaciones_actualizadas' => count($cambios),
            'servicios_sincronizados' => $serviciosSync,
            'cambios' => $cambios,
        ];
    }

    /**
     * Fuerza precio_app = suma(precio_aplicado) en todos los servicios con asignación TV.
     *
     * @return array{revisados: int, actualizados: int, cambios: list<array<string, mixed>>}
     */
    public function reconciliarPrecioAppServicios(bool $aplicar = false): array
    {
        $servicioIds = TvCuentaAsignacion::query()
            ->whereNotNull('servicio_id')
            ->distinct()
            ->pluck('servicio_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $cambios = [];
        $actualizados = 0;

        foreach ($servicioIds as $servicioId) {
            $asignaciones = TvCuentaAsignacion::query()
                ->where('servicio_id', $servicioId)
                ->get(['precio_aplicado']);

            $suma = round((float) $asignaciones->sum(fn ($a) => (float) ($a->precio_aplicado ?? 0)), 2);
            $esperadoPrecio = $suma > 0 ? $suma : null;
            $esperadaCantidad = $asignaciones->count();

            $servicio = Servicio::query()->where('servicio_id', $servicioId)->first([
                'servicio_id', 'app_tv', 'precio_app', 'cantidad_perfil_app',
            ]);
            if (! $servicio) {
                continue;
            }

            $precioActual = $this->precioNormalizado($servicio->precio_app);
            $cantActual = $servicio->cantidad_perfil_app !== null ? (int) $servicio->cantidad_perfil_app : null;
            $appTvActual = (bool) ($servicio->app_tv ?? false);

            $mismoPrecio = $precioActual === $this->precioNormalizado($esperadoPrecio);
            $mismaCant = $cantActual === $esperadaCantidad;
            $mismoFlag = $appTvActual === true;

            if ($mismoPrecio && $mismaCant && $mismoFlag) {
                continue;
            }

            $cambios[] = [
                'servicio_id' => $servicioId,
                'precio_app_anterior' => $precioActual,
                'precio_app_nuevo' => $esperadoPrecio,
                'cantidad_anterior' => $cantActual,
                'cantidad_nueva' => $esperadaCantidad,
            ];

            if ($aplicar) {
                $this->sincronizarServicio($servicioId);
                $actualizados++;
            }
        }

        return [
            'revisados' => count($servicioIds),
            'actualizados' => $aplicar ? $actualizados : count($cambios),
            'cambios' => $cambios,
        ];
    }

    /**
     * Precio TV que debería facturarse hoy: suma de precios de catálogo
     * de asignaciones no promo (promos aportan 0).
     */
    public function precioAppEsperadoParaServicio(int $servicioId): ?float
    {
        $asignaciones = TvCuentaAsignacion::query()
            ->with('tvCuenta')
            ->where('servicio_id', $servicioId)
            ->get();

        if ($asignaciones->isEmpty()) {
            return null;
        }

        $suma = 0.0;
        foreach ($asignaciones as $asignacion) {
            if ($asignacion->es_promo) {
                continue;
            }
            $cuenta = $asignacion->tvCuenta;
            if (! $cuenta instanceof TvCuenta || ! $asignacion->perfil_numero) {
                $suma += (float) ($asignacion->precio_aplicado ?? 0);

                continue;
            }
            $suma += (float) ($cuenta->precioSlot((int) $asignacion->perfil_numero) ?? 0);
        }

        $suma = round($suma, 2);

        return $suma > 0 ? $suma : null;
    }

    /**
     * Corrige líneas «Servicio especial» de facturas internas pendientes cuyo monto
     * no coincide con el precio TV esperado (catálogo de asignaciones actuales).
     *
     * @return array{revisadas: int, corregidas: int, cambios: list<array<string, mixed>>}
     */
    public function corregirFacturasInternasTvPendientes(bool $aplicar = false, ?float $soloSiPrecioAnterior = null): array
    {
        $detalles = FacturaInternaDetalle::query()
            ->where('descripcion', 'like', 'Servicio especial%')
            ->whereNotNull('servicio_id')
            ->whereHas('facturaInterna', function ($q) {
                $q->where('estado', 'pendiente')
                    ->where(function ($q2) {
                        $q2->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
                            ->orWhereNull('tipo_factura');
                    });
            })
            ->with(['facturaInterna', 'impuesto', 'servicio:servicio_id,precio_app,app_tv'])
            ->orderBy('id')
            ->get();

        $cambios = [];
        $corregidas = 0;
        $facturasTocadas = [];

        foreach ($detalles as $detalle) {
            $servicioId = (int) $detalle->servicio_id;
            $esperado = $this->precioAppEsperadoParaServicio($servicioId);
            if ($esperado === null || $esperado <= 0.009) {
                continue;
            }

            $actual = $this->precioNormalizado($detalle->precio_unitario) ?? 0.0;
            if ($actual === $esperado) {
                continue;
            }

            if ($soloSiPrecioAnterior !== null && abs($actual - $soloSiPrecioAnterior) > 0.009) {
                continue;
            }

            $periodoStr = $this->extraerPeriodoDescripcion((string) $detalle->descripcion);
            $descripcionNueva = $periodoStr !== null
                ? sprintf('Servicio especial - %s Gs. - Período %s', number_format($esperado, 0, ',', '.'), $periodoStr)
                : sprintf('Servicio especial - %s Gs.', number_format($esperado, 0, ',', '.'));

            $cambios[] = [
                'detalle_id' => (int) $detalle->id,
                'factura_id' => (int) $detalle->factura_interna_id,
                'servicio_id' => $servicioId,
                'precio_anterior' => $actual,
                'precio_nuevo' => $esperado,
                'total_factura_anterior' => (float) ($detalle->facturaInterna?->total ?? 0),
            ];

            if (! $aplicar) {
                continue;
            }

            $impuesto = $detalle->impuesto
                ?? ($detalle->impuesto_id ? Impuesto::find($detalle->impuesto_id) : null);
            $calc = FacturaDetalle::calcularDesdePrecioIvaIncluido(1, $esperado, $impuesto);

            $detalle->update([
                'descripcion' => $descripcionNueva,
                'precio_unitario' => $esperado,
                'subtotal' => $calc['subtotal'],
                'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                'monto_impuesto' => $calc['monto_impuesto'],
                'total' => $calc['total'],
            ]);

            $facturasTocadas[(int) $detalle->factura_interna_id] = true;
            $corregidas++;
        }

        if ($aplicar) {
            foreach (array_keys($facturasTocadas) as $facturaId) {
                $factura = FacturaInterna::query()->find($facturaId);
                if (! $factura) {
                    continue;
                }
                $this->recalcularTotalesFacturaInterna($factura);
                app(CobrosResumenService::class)->aplicarImpactoFactura($factura->fresh());
            }
        }

        return [
            'revisadas' => $detalles->count(),
            'corregidas' => $aplicar ? $corregidas : count($cambios),
            'cambios' => $cambios,
        ];
    }

    /**
     * Agrega línea TV faltante en facturas del período para servicios con antigüedad
     * de instalación y perfil TV activo en el período (no promo).
     *
     * @return array{revisados: int, a_agregar: int, agregadas: int, cambios: list<array<string, mixed>>}
     */
    public function agregarLineasTvFaltantesEnPeriodo(
        Carbon $periodoDesde,
        Carbon $periodoHasta,
        int $minAntiguedadMeses = 1,
        bool $aplicar = false,
        bool $incluirPagadas = true,
    ): array {
        $corteInstalacion = $periodoDesde->copy()->subMonthsNoOverflow(max(0, $minAntiguedadMeses))->startOfDay();
        $periodoHastaStr = $periodoHasta->toDateString();

        $servicios = Servicio::query()
            ->where('app_tv', true)
            ->whereIn('estado', [
                Servicio::ESTADO_ACTIVO,
                Servicio::ESTADO_SUSPENDIDO,
                Servicio::ESTADO_CORTADO,
            ])
            ->whereNotNull('fecha_instalacion')
            ->whereDate('fecha_instalacion', '<', $corteInstalacion->toDateString())
            ->whereHas('cliente', fn ($q) => $q->where('estado', 'activo'))
            ->whereHas('tvCuentaAsignaciones', function ($q) use ($periodoDesde) {
                // Debe estar activo desde antes del mes facturado (evita cobros
                // a perfiles asignados después de emitir la factura del mes).
                $q->where(function ($q2) {
                    $q2->where('es_promo', false)->orWhereNull('es_promo');
                })->where(function ($q2) use ($periodoDesde) {
                    $q2->whereNull('fecha_activacion')
                        ->orWhereDate('fecha_activacion', '<', $periodoDesde->toDateString());
                });
            })
            ->with(['cliente:cliente_id,nombre,apellido,cedula', 'plan'])
            ->orderBy('cliente_id')
            ->get();

        $impuestoPlan = Impuesto::where('codigo', 'IVA10')->first()
            ?? Impuesto::where('porcentaje', 10)->first()
            ?? Impuesto::first();

        $cambios = [];
        $agregadas = 0;

        foreach ($servicios as $servicio) {
            $esperado = $this->precioAppEsperadoParaServicio((int) $servicio->servicio_id);
            if ($esperado === null || $esperado <= 0.009) {
                continue;
            }

            $factura = FacturaInterna::query()
                ->where('cliente_id', $servicio->cliente_id)
                ->whereDate('periodo_hasta', $periodoHastaStr)
                ->where(function ($q) {
                    $q->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
                        ->orWhereNull('tipo_factura');
                })
                ->whereNotIn('estado', ['anulada', 'cancelada'])
                ->orderByDesc('id')
                ->first();

            if (! $factura) {
                continue;
            }
            if (! $incluirPagadas && $factura->estado === 'pagada') {
                continue;
            }

            $yaTieneTv = FacturaInternaDetalle::query()
                ->where('factura_interna_id', $factura->id)
                ->where(function ($q) {
                    $q->where('descripcion', 'like', '%Especial%')
                        ->orWhere('descripcion', 'like', '%especial%');
                })
                ->where('total', '>', 0)
                ->exists();

            if ($yaTieneTv) {
                continue;
            }

            $nombre = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));
            $cambios[] = [
                'cliente_id' => (int) $servicio->cliente_id,
                'nombre' => $nombre,
                'cedula' => (string) ($servicio->cliente?->cedula ?? ''),
                'servicio_id' => (int) $servicio->servicio_id,
                'fecha_instalacion' => optional($servicio->fecha_instalacion)?->toDateString(),
                'factura_id' => (int) $factura->id,
                'estado_factura' => (string) $factura->estado,
                'total_anterior' => (float) $factura->total,
                'precio_tv' => $esperado,
                'total_nuevo' => round((float) $factura->total + $esperado, 2),
            ];

            if (! $aplicar) {
                continue;
            }

            $impuestoLineaPlan = FacturaInternaDetalle::query()
                ->where('factura_interna_id', $factura->id)
                ->where('servicio_id', $servicio->servicio_id)
                ->where('total', '>', 0)
                ->orderBy('id')
                ->first();
            $impuesto = $impuestoLineaPlan?->impuesto_id
                ? Impuesto::find($impuestoLineaPlan->impuesto_id)
                : $impuestoPlan;
            $periodoStr = $periodoDesde->format('d/m/Y').' hasta '.$periodoHasta->format('d/m/Y');
            $calc = FacturaDetalle::calcularDesdePrecioIvaIncluido(1, $esperado, $impuesto);

            FacturaInternaDetalle::create([
                'factura_interna_id' => $factura->id,
                'impuesto_id' => $impuesto?->id,
                'servicio_id' => $servicio->servicio_id,
                'descripcion' => sprintf(
                    'Servicio especial - %s Gs. - Período %s',
                    number_format($esperado, 0, ',', '.'),
                    $periodoStr
                ),
                'cantidad' => 1,
                'precio_unitario' => $esperado,
                'subtotal' => $calc['subtotal'],
                'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                'monto_impuesto' => $calc['monto_impuesto'],
                'total' => $calc['total'],
            ]);

            $this->recalcularTotalesFacturaInterna($factura->fresh());
            $factura = $factura->fresh();
            if ($factura && (float) $factura->saldo_pendiente > 0.009 && $factura->estado === 'pagada') {
                $factura->update(['estado' => 'pendiente']);
            }
            app(CobrosResumenService::class)->aplicarImpactoFactura($factura->fresh());
            $agregadas++;
        }

        return [
            'revisados' => $servicios->count(),
            'a_agregar' => count($cambios),
            'agregadas' => $aplicar ? $agregadas : 0,
            'cambios' => $cambios,
        ];
    }

    public function recalcularTotalesFacturaInterna(FacturaInterna $factura): void
    {
        $detalles = FacturaInternaDetalle::where('factura_interna_id', $factura->id)->get();
        $subtotal = 0.0;
        $totalImpuestos = 0.0;
        $sumaTotalesLineas = 0.0;
        foreach ($detalles as $d) {
            $subtotal += (float) $d->subtotal;
            $totalImpuestos += (float) $d->monto_impuesto;
            $sumaTotalesLineas += (float) $d->total;
        }
        $descuento = (float) ($factura->descuento ?? 0);
        $factura->update([
            'subtotal' => $subtotal,
            'total_impuestos' => $totalImpuestos,
            'total' => max(0, $sumaTotalesLineas - $descuento),
        ]);
    }

    public function precioNormalizado(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return round((float) $valor, 2);
    }

    private function extraerPeriodoDescripcion(string $descripcion): ?string
    {
        if (preg_match('/Período\s+(.+)$/u', $descripcion, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listarDesfasesAsignaciones(bool $incluirCero = true): Collection
    {
        $resultado = $this->reconciliarAsignaciones(null, $incluirCero, false);

        return collect($resultado['cambios']);
    }
}
