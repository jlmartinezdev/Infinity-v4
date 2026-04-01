<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\FacturacionParametro;
use App\Models\Impuesto;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FacturacionService
{
    private const SALTO_REDONDEO = 5000;

    /**
     * Calcula el precio prorrateado cuando la instalación es en medio del mes.
     * Si fecha_instalacion está dentro del período y no es el primer día, prorratea por días restantes.
     * Redondea hacia arriba a saltos de 5000.
     */
    public static function calcularPrecioProrrateado(Servicio $servicio, Carbon $periodoDesde, Carbon $periodoHasta, float $precioPlan): float
    {
        $fechaInstalacion = $servicio->fecha_instalacion;
        if (! $fechaInstalacion) {
            return $precioPlan;
        }
        $fechaInstalacion = Carbon::parse($fechaInstalacion)->startOfDay();
        $periodoDesde = $periodoDesde->copy()->startOfDay();
        $periodoHasta = $periodoHasta->copy()->endOfDay();

        if ($fechaInstalacion->lt($periodoDesde) || $fechaInstalacion->gt($periodoHasta)) {
            return $precioPlan;
        }
        if ($fechaInstalacion->lte($periodoDesde)) {
            return $precioPlan;
        }

        $finMes = $periodoHasta->copy();
        $diasEnMes = $finMes->day;
        $diasRestantes = $fechaInstalacion->diffInDays($finMes) + 1;

        if ($diasRestantes <= 0 || $diasEnMes <= 0) {
            return $precioPlan;
        }

        $precioDia = $precioPlan / $diasEnMes;
        $montoProrrateado = $precioDia * $diasRestantes;

        return (float) (ceil($montoProrrateado / self::SALTO_REDONDEO) * self::SALTO_REDONDEO);
    }

    /**
     * Devuelve el detalle del prorrateo para mostrar en formularios (null si no aplica prorrateo).
     *
     * @return array{activo: bool, fecha_instalacion: string, dias_restantes: int, dias_en_mes: int, precio_plan: float, precio_prorrateado: float}|null
     */
    public static function obtenerDetalleProrrateo(Servicio $servicio, Carbon $periodoDesde, Carbon $periodoHasta, float $precioPlan): ?array
    {
        $fechaInstalacion = $servicio->fecha_instalacion;
        if (! $fechaInstalacion) {
            return null;
        }
        $fechaInstalacion = Carbon::parse($fechaInstalacion)->startOfDay();
        $periodoDesde = $periodoDesde->copy()->startOfDay();
        $periodoHasta = $periodoHasta->copy()->endOfDay();

        if ($fechaInstalacion->lt($periodoDesde) || $fechaInstalacion->gt($periodoHasta)) {
            return null;
        }
        if ($fechaInstalacion->lte($periodoDesde)) {
            return null;
        }

        $finMes = $periodoHasta->copy();
        $diasEnMes = $finMes->day;
        $diasRestantes = $fechaInstalacion->diffInDays($finMes) + 1;

        if ($diasRestantes <= 0 || $diasEnMes <= 0) {
            return null;
        }

        $precioProrrateado = self::calcularPrecioProrrateado($servicio, $periodoDesde->copy()->startOfDay(), $periodoHasta->copy()->endOfDay(), $precioPlan);

        return [
            'activo' => true,
            'fecha_instalacion' => Carbon::parse($servicio->fecha_instalacion)->format('d/m/Y'),
            'dias_restantes' => $diasRestantes,
            'dias_en_mes' => $diasEnMes,
            'precio_plan' => $precioPlan,
            'precio_prorrateado' => $precioProrrateado,
        ];
    }

    /**
     * Genera una factura interna (mensual) para un cliente a partir de sus servicios activos.
     * Un detalle por cada servicio con el precio del plan.
     *
     * @param  string|null  $estado  Estado de la factura (por defecto: emitida)
     * @param  string|null  $fechaVencimiento  Fecha de vencimiento Y-m-d (por defecto: emisión + dias_vencimiento_factura)
     */
    public function generarFacturaInterna(Cliente $cliente, Carbon $periodoDesde, Carbon $periodoHasta, ?int $usuarioId = null, ?string $estado = null, ?string $fechaVencimiento = null): FacturaInterna
    {
        $servicios = $cliente->servicios()
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->with('plan')
            ->get();

        if ($servicios->isEmpty()) {
            throw new \InvalidArgumentException('El cliente no tiene servicios activos para facturar.');
        }

        $diasVencimiento = FacturacionParametro::diasVencimientoFactura();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $estadoFinal = $estado ?? 'emitida';
        $fechaVencimientoFinal = $fechaVencimiento ?? now()->addDays($diasVencimiento)->toDateString();

        $periodoDesdeEfectivo = $periodoDesde->copy();
        $instalacionesEnPeriodo = $servicios->filter(function ($s) use ($periodoDesde, $periodoHasta) {
            if (! $s->fecha_instalacion) {
                return false;
            }
            $f = Carbon::parse($s->fecha_instalacion)->startOfDay();
            return $f->gte($periodoDesde->copy()->startOfDay()) && $f->lte($periodoHasta->copy()->endOfDay());
        });
        if ($instalacionesEnPeriodo->isNotEmpty()) {
            $fechas = $instalacionesEnPeriodo->map(fn ($s) => Carbon::parse($s->fecha_instalacion)->startOfDay());
            $periodoDesdeEfectivo = $fechas->sortBy(fn ($c) => $c->format('Y-m-d'))->first()->copy();
        }

        return DB::transaction(function () use ($cliente, $servicios, $periodoDesde, $periodoHasta, $periodoDesdeEfectivo, $impuestoExento, $usuarioId, $estadoFinal, $fechaVencimientoFinal) {
            $fechaEmision = now()->toDateString();

            $factura = FacturaInterna::create([
                'cliente_id' => $cliente->cliente_id,
                'periodo_desde' => $periodoDesdeEfectivo->toDateString(),
                'periodo_hasta' => $periodoHasta->toDateString(),
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimientoFinal,
                'estado' => $estadoFinal,
                'moneda' => 'PYG',
                'usuario_id' => $usuarioId,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
                'observaciones' => sprintf('Factura interna - Período %s a %s', $periodoDesdeEfectivo->format('d/m/Y'), $periodoHasta->format('d/m/Y')),
            ]);

            $subtotal = 0;
            $totalImpuestos = 0;
            $total = 0;

            foreach ($servicios as $servicio) {
                $plan = $servicio->plan;
                $precioPlan = $plan ? (float) $plan->precio : 0;
                $precio = self::calcularPrecioProrrateado($servicio, $periodoDesde, $periodoHasta, $precioPlan);
                $nombrePlan = $plan ? $plan->nombre : 'N/A';
                $precioFormateado = number_format($precio, 0, ',', '.');
                $desdeServicio = $servicio->fecha_instalacion && Carbon::parse($servicio->fecha_instalacion)->between($periodoDesde, $periodoHasta)
                    ? Carbon::parse($servicio->fecha_instalacion)->format('d/m/Y')
                    : $periodoDesde->format('d/m/Y');
                $periodoStr = $desdeServicio . ' hasta ' . $periodoHasta->format('d/m/Y');
                $descripcion = sprintf('%s - %s Gs. - Desde %s', $nombrePlan, $precioFormateado, $periodoStr);

                $calc = FacturaDetalle::calcularDesdePrecio(1, $precio, $impuestoExento);

                FacturaInternaDetalle::create([
                    'factura_interna_id' => $factura->id,
                    'impuesto_id' => $impuestoExento?->id,
                    'servicio_id' => $servicio->servicio_id,
                    'descripcion' => $descripcion,
                    'cantidad' => 1,
                    'precio_unitario' => $precio,
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);

                $subtotal += $calc['subtotal'];
                $totalImpuestos += $calc['monto_impuesto'];
                $total += $calc['total'];
            }

            $this->aplicarSaldoAFavorEnFactura($factura, $servicios, $subtotal, $totalImpuestos, $total, $impuestoExento);

            $factura->update([
                'subtotal' => $subtotal,
                'total_impuestos' => $totalImpuestos,
                'total' => $total,
            ]);

            $this->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, 'pendiente');

            return $factura->fresh(['detalles', 'cliente']);
        });
    }

    /**
     * Genera factura(s) interna(s) a partir de servicios seleccionados.
     * Agrupa por cliente: un cliente puede tener varios servicios seleccionados y se genera una factura por cliente.
     * Solo se consideran servicios activos.
     *
     * @param array<int> $servicioIds
     * @param  string|null  $observacionesExtra  Texto opcional añadido al final de observaciones (ej. referencia a pedido).
     * @return array{facturas: \Illuminate\Support\Collection<int, FacturaInterna>, primera: FacturaInterna|null}
     */
    public function generarFacturaInternaDesdeServicios(array $servicioIds, Carbon $periodoDesde, Carbon $periodoHasta, ?int $usuarioId = null, ?string $observacionesExtra = null): array
    {
        if (empty($servicioIds)) {
            throw new \InvalidArgumentException('Debe seleccionar al menos un servicio.');
        }

        $servicios = Servicio::whereIn('servicio_id', $servicioIds)
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->with(['plan', 'cliente'])
            ->get();

        if ($servicios->isEmpty()) {
            throw new \InvalidArgumentException('Ninguno de los servicios seleccionados está activo. Solo se facturan servicios activos.');
        }

        $porCliente = $servicios->groupBy('cliente_id');
        $diasVencimiento = FacturacionParametro::diasVencimientoFactura();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $facturas = collect();

        foreach ($porCliente as $clienteId => $serviciosCliente) {
            $cliente = $serviciosCliente->first()->cliente;
            if (!$cliente) {
                continue;
            }

            $periodoDesdeEfectivo = $periodoDesde->copy();
            $instalacionesEnPeriodo = $serviciosCliente->filter(function ($s) use ($periodoDesde, $periodoHasta) {
                if (! $s->fecha_instalacion) {
                    return false;
                }
                $f = Carbon::parse($s->fecha_instalacion)->startOfDay();

                return $f->gte($periodoDesde->copy()->startOfDay()) && $f->lte($periodoHasta->copy()->endOfDay());
            });
            if ($instalacionesEnPeriodo->isNotEmpty()) {
                $fechas = $instalacionesEnPeriodo->map(fn ($s) => Carbon::parse($s->fecha_instalacion)->startOfDay());
                $periodoDesdeEfectivo = $fechas->sortBy(fn ($c) => $c->format('Y-m-d'))->first()->copy();
            }

            $factura = DB::transaction(function () use ($serviciosCliente, $cliente, $periodoDesde, $periodoHasta, $periodoDesdeEfectivo, $diasVencimiento, $impuestoExento, $usuarioId, $observacionesExtra) {
                $fechaEmision = now()->toDateString();
                $fechaVencimiento = now()->addDays($diasVencimiento)->toDateString();

                $obsBase = sprintf(
                    'Factura interna - Período %s a %s (servicios seleccionados)',
                    $periodoDesdeEfectivo->format('d/m/Y'),
                    $periodoHasta->format('d/m/Y')
                );
                $observaciones = trim($obsBase.($observacionesExtra !== null && $observacionesExtra !== '' ? ' '.$observacionesExtra : ''));

                $factura = FacturaInterna::create([
                    'cliente_id' => $cliente->cliente_id,
                    'periodo_desde' => $periodoDesdeEfectivo->toDateString(),
                    'periodo_hasta' => $periodoHasta->toDateString(),
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                    'moneda' => 'PYG',
                    'usuario_id' => $usuarioId,
                    'subtotal' => 0,
                    'total_impuestos' => 0,
                    'total' => 0,
                    'observaciones' => $observaciones,
                ]);

                $subtotal = 0;
                $totalImpuestos = 0;
                $total = 0;

                foreach ($serviciosCliente as $servicio) {
                    $plan = $servicio->plan;
                    $precioPlan = $plan ? (float) $plan->precio : 0;
                    $precio = self::calcularPrecioProrrateado($servicio, $periodoDesde, $periodoHasta, $precioPlan);
                    $nombrePlan = $plan ? $plan->nombre : 'N/A';
                    $precioFormateado = number_format($precio, 0, ',', '.');
                    $desdeServicio = $servicio->fecha_instalacion && Carbon::parse($servicio->fecha_instalacion)->between($periodoDesde, $periodoHasta)
                        ? Carbon::parse($servicio->fecha_instalacion)->format('d/m/Y')
                        : $periodoDesde->format('d/m/Y');
                    $periodoStr = $desdeServicio.' hasta '.$periodoHasta->format('d/m/Y');
                    $descripcion = sprintf('%s - %s Gs. - Desde %s', $nombrePlan, $precioFormateado, $periodoStr);

                    $calc = FacturaDetalle::calcularDesdePrecio(1, $precio, $impuestoExento);

                    FacturaInternaDetalle::create([
                        'factura_interna_id' => $factura->id,
                        'impuesto_id' => $impuestoExento?->id,
                        'servicio_id' => $servicio->servicio_id,
                        'descripcion' => $descripcion,
                        'cantidad' => 1,
                        'precio_unitario' => $precio,
                        'subtotal' => $calc['subtotal'],
                        'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                        'monto_impuesto' => $calc['monto_impuesto'],
                        'total' => $calc['total'],
                    ]);

                    $subtotal += $calc['subtotal'];
                    $totalImpuestos += $calc['monto_impuesto'];
                    $total += $calc['total'];
                }

                $this->aplicarSaldoAFavorEnFactura($factura, $serviciosCliente, $subtotal, $totalImpuestos, $total, $impuestoExento);

                $factura->update([
                    'subtotal' => $subtotal,
                    'total_impuestos' => $totalImpuestos,
                    'total' => $total,
                ]);

                $this->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, 'pendiente');

                return $factura->fresh(['detalles', 'cliente']);
            });

            $facturas->push($factura);
        }

        return [
            'facturas' => $facturas,
            'primera' => $facturas->first(),
        ];
    }

    /**
     * Genera una factura interna desde un solo servicio con datos editables (fechas, descuento, items).
     */
    public function generarFacturaInternaDesdeUnServicio(
        Servicio $servicio,
        Carbon $periodoDesde,
        Carbon $periodoHasta,
        string $fechaEmision,
        string $fechaVencimiento,
        ?string $fechaPago,
        float $descuento,
        array $items,
        ?int $usuarioId = null
    ): FacturaInterna {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            throw new \InvalidArgumentException('El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado !== Servicio::ESTADO_ACTIVO) {
            throw new \InvalidArgumentException('Solo se pueden facturar servicios activos.');
        }

        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $cliente = $servicio->cliente;

        return DB::transaction(function () use ($servicio, $cliente, $periodoDesde, $periodoHasta, $fechaEmision, $fechaVencimiento, $fechaPago, $descuento, $items, $impuestoExento, $usuarioId) {
            $subtotal = 0;
            $totalImpuestos = 0;
            $total = 0;

            $factura = FacturaInterna::create([
                'cliente_id' => $cliente->cliente_id,
                'periodo_desde' => $periodoDesde->toDateString(),
                'periodo_hasta' => $periodoHasta->toDateString(),
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'fecha_pago' => $fechaPago,
                'estado' => 'pendiente',
                'moneda' => 'PYG',
                'usuario_id' => $usuarioId,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
                'descuento' => $descuento,
                'observaciones' => sprintf('Factura interna - Período %s a %s', $periodoDesde->format('d/m/Y'), $periodoHasta->format('d/m/Y')),
            ]);

            foreach ($items as $item) {
                $impuesto = Impuesto::find($item['impuesto_id'] ?? null) ?? $impuestoExento;
                $cantidad = (float) ($item['cantidad'] ?? 1);
                $precioUnitario = (float) ($item['precio_unitario'] ?? 0);
                $calc = FacturaDetalle::calcularDesdePrecio($cantidad, $precioUnitario, $impuesto);

                FacturaInternaDetalle::create([
                    'factura_interna_id' => $factura->id,
                    'impuesto_id' => $impuesto->id,
                    'servicio_id' => $servicio->servicio_id,
                    'descripcion' => $item['descripcion'] ?? 'Servicio',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);

                $subtotal += $calc['subtotal'];
                $totalImpuestos += $calc['monto_impuesto'];
                $total += $calc['total'];
            }

            $totalFinal = max(0, $total - $descuento);

            $factura->update([
                'subtotal' => $subtotal,
                'total_impuestos' => $totalImpuestos,
                'total' => $totalFinal,
            ]);

            $this->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, 'pendiente');

            return $factura->fresh(['detalles', 'cliente']);
        });
    }

    /**
     * Aplica el saldo a favor de los servicios a la factura: añade línea de descuento y descuenta de los servicios.
     * Modifica $subtotal y $total por referencia.
     */
    private function aplicarSaldoAFavorEnFactura(FacturaInterna $factura, $servicios, float &$subtotal, float &$totalImpuestos, float &$total, ?Impuesto $impuestoExento): void
    {
        $saldoFavorTotal = $servicios->sum(fn (Servicio $s) => (float) ($s->saldo_a_favor ?? 0));
        if ($saldoFavorTotal <= 0 || $subtotal <= 0) {
            return;
        }

        $montoAplicar = min($saldoFavorTotal, $subtotal);
        $montoAplicar = round($montoAplicar, 2);

        FacturaInternaDetalle::create([
            'factura_interna_id' => $factura->id,
            'impuesto_id' => $impuestoExento?->id,
            'servicio_id' => null,
            'descripcion' => 'Saldo a favor aplicado',
            'cantidad' => 1,
            'precio_unitario' => -$montoAplicar,
            'subtotal' => -$montoAplicar,
            'porcentaje_impuesto' => 0,
            'monto_impuesto' => 0,
            'total' => -$montoAplicar,
        ]);

        $subtotal -= $montoAplicar;
        $total -= $montoAplicar;

        $restante = $montoAplicar;
        foreach ($servicios as $servicio) {
            if ($restante <= 0) {
                break;
            }
            $saldoServicio = (float) ($servicio->saldo_a_favor ?? 0);
            if ($saldoServicio <= 0) {
                continue;
            }
            $aDeducir = min($saldoServicio, $restante);
            $aDeducir = round($aDeducir, 2);
            Servicio::where('servicio_id', $servicio->servicio_id)->decrement('saldo_a_favor', $aDeducir);
            $restante -= $aDeducir;
        }
    }

    /**
     * Registra un cobro (solo aplicable a factura interna). Si cubre facturas vencidas del cliente, puede reactivar servicios suspendidos por falta de pago.
     * Si el cobro es por una factura interna, el concepto se copia de la descripción del detalle de la factura.
     *
     * El número de recibo se asigna aquí de forma atómica (secuencia en BD); no debe enviarse en $data.
     *
     * Acepta:
     * - factura_interna_id + monto: cobro a una sola factura
     * - factura_interna_items: [{id, monto}, ...] para un cobro que aplica a varias facturas
     *
     * Regla fecha_pago: si la factura tiene fecha_pago configurada y la fecha del cobro es ANTERIOR a esa fecha,
     * se usa la fecha_pago de la factura en el cobro.
     */
    public function registrarCobro(array $data, ?int $usuarioId = null): Cobro
    {
        $items = $data['factura_interna_items'] ?? null;
        if (empty($items) && !empty($data['factura_interna_id'])) {
            $items = [['id' => (int) $data['factura_interna_id'], 'monto' => (float) ($data['monto'] ?? 0)]];
        }
        $items = is_array($items) ? $items : [];

        $concepto = $data['concepto'] ?? null;
        if (empty($concepto) && !empty($items)) {
            $ids = array_column($items, 'id');
            $conceptos = array_filter(array_map(fn ($id) => $this->conceptoCobroDesdeFactura($id), $ids));
            $concepto = \Illuminate\Support\Str::limit(implode(' | ', $conceptos), 500, '');
        }

        $fechaPagoFinal = $data['fecha_pago'];
        $primeraFactura = !empty($items) ? FacturaInterna::find($items[0]['id']) : null;
        if ($primeraFactura && $primeraFactura->fecha_pago) {
            $fechaCobro = \Carbon\Carbon::parse($data['fecha_pago']);
            $fechaRefFactura = $primeraFactura->fecha_pago instanceof \Carbon\Carbon
                ? $primeraFactura->fecha_pago->copy()->startOfDay()
                : \Carbon\Carbon::parse($primeraFactura->fecha_pago)->startOfDay();
            if ($fechaCobro->copy()->startOfDay()->lt($fechaRefFactura)) {
                $fechaPagoFinal = \Carbon\Carbon::parse($primeraFactura->fecha_pago)->startOfDay();
            }
        }

        $montoTotal = (float) ($data['monto'] ?? 0);
        if (!empty($items)) {
            $montoTotal = array_sum(array_column($items, 'monto'));
        }

        $cobro = DB::transaction(function () use ($data, $usuarioId, $concepto, $fechaPagoFinal, $items, $montoTotal) {
            $numeros = Cobro::reservarSiguientesNumerosRecibo(1);
            $numeroRecibo = $numeros[0];

            $primeraId = !empty($items) ? (int) $items[0]['id'] : null;
            $cobro = Cobro::create([
                'cliente_id' => $data['cliente_id'],
                'factura_interna_id' => $primeraId,
                'monto' => $montoTotal,
                'fecha_pago' => $fechaPagoFinal,
                'forma_pago' => $data['forma_pago'] ?? 'efectivo',
                'numero_recibo' => $numeroRecibo,
                'referencia' => $data['referencia'] ?? null,
                'concepto' => $concepto,
                'observaciones' => $data['observaciones'] ?? null,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($items as $item) {
                $fid = (int) ($item['id'] ?? $item['factura_interna_id'] ?? 0);
                $monto = (float) ($item['monto'] ?? 0);
                if ($fid > 0 && $monto > 0) {
                    $cobro->facturaInternas()->attach($fid, ['monto' => $monto]);
                }
            }

            $cliente = Cliente::find($data['cliente_id']);
            if ($cliente) {
                $this->revisarActivacionServicios($cliente);
                if (!empty($items)) {
                    $this->recalcularCalificacionPagoCliente($cliente);
                }
            }

            foreach ($items as $item) {
                $fid = (int) ($item['id'] ?? $item['factura_interna_id'] ?? 0);
                if ($fid > 0) {
                    $factura = FacturaInterna::find($fid);
                    if ($factura) {
                        $factura->refresh();
                        $estadoPago = $factura->saldo_pendiente <= 0 ? 'pagado' : 'parcial';
                        $this->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, $estadoPago);
                        if ($factura->saldo_pendiente <= 0) {
                            $factura->update(['estado' => 'pagada']);
                        }
                    }
                }
            }

            return $cobro;
        });

        return $cobro->load(['cliente', 'facturaInternas', 'usuario']);
    }

    /**
     * Construye el concepto del cobro a partir de las descripciones del detalle de la factura interna.
     * Máximo 500 caracteres.
     */
    public function conceptoCobroDesdeFactura(?int $facturaInternaId): ?string
    {
        if (!$facturaInternaId) {
            return null;
        }
        $descripciones = FacturaInternaDetalle::where('factura_interna_id', $facturaInternaId)
            ->orderBy('id')
            ->pluck('descripcion')
            ->filter()
            ->values()
            ->all();
        if (empty($descripciones)) {
            return null;
        }
        $texto = implode(' / ', $descripciones);

        return \Illuminate\Support\Str::limit($texto, 500, '');
    }

    /**
     * Si el cliente tiene servicios suspendidos por falta de pago y ya no tiene facturas emitidas con saldo pendiente vencido, reactiva los servicios.
     */
    public function revisarActivacionServicios(Cliente $cliente): void
    {
        $facturasVencidasConSaldo = FacturaInterna::where('cliente_id', $cliente->cliente_id)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0);

        if ($facturasVencidasConSaldo->isEmpty()) {
            $cliente->servicios()
                ->where('estado', Servicio::ESTADO_SUSPENDIDO)
                ->where('motivo_suspension', 'like', '%Falta de pago%')
                ->each(fn (Servicio $s) => $s->activar());
        }
    }

    /**
     * Suspende servicios de clientes con facturas internas emitidas vencidas y con saldo pendiente.
     * Se consideran vencidas las facturas cuya fecha_vencimiento ya pasó (se ejecuta el día de corte configurado).
     *
     * @param  bool  $dryRun  Si true, solo devuelve la lista sin suspender
     */
    public function suspenderPorFaltaPago(bool $dryRun = false): array
    {
        $fechaLimite = now()->toDateString();

        $facturasVencidas = FacturaInterna::whereIn('estado', ['pendiente', 'emitida'])
            ->where('fecha_vencimiento', '<', $fechaLimite)
            ->with('cliente.servicios')
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0);

        $suspendidos = [];
        foreach ($facturasVencidas as $factura) {
            $cliente = $factura->cliente;
            foreach ($cliente->servicios as $servicio) {
                if ($servicio->estaActivo()) {
                    if (!$dryRun) {
                        $servicio->suspender('Falta de pago - Factura vencida');
                    }
                    $suspendidos[] = ['servicio_id' => $servicio->servicio_id, 'cliente_id' => $cliente->cliente_id];
                }
            }
        }

        return $suspendidos;
    }

    /**
     * Genera una factura interna con un solo ítem: saldo pendiente de otra factura (por pago parcial).
     */
    public function generarFacturaPorSaldoRestante(Cliente $cliente, int $facturaOrigenId, string $tipoOrigen, float $montoRestante, ?int $usuarioId = null): FacturaInterna
    {
        $diasVencimiento = FacturacionParametro::diasVencimientoFactura();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $fechaEmision = now()->toDateString();
        $fechaVencimiento = now()->addDays($diasVencimiento)->toDateString();

        $etiqueta = $tipoOrigen === 'electronica' ? 'Factura electrónica' : 'Factura interna';
        $descripcion = sprintf('Saldo pendiente - %s #%s', $etiqueta, $facturaOrigenId);

        return DB::transaction(function () use ($cliente, $montoRestante, $fechaEmision, $fechaVencimiento, $periodoDesde, $periodoHasta, $impuestoExento, $usuarioId, $descripcion) {
            $factura = FacturaInterna::create([
                'cliente_id' => $cliente->cliente_id,
                'periodo_desde' => $periodoDesde,
                'periodo_hasta' => $periodoHasta,
                'fecha_emision' => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'emitida',
                'moneda' => 'PYG',
                'usuario_id' => $usuarioId,
                'subtotal' => $montoRestante,
                'total_impuestos' => 0,
                'total' => $montoRestante,
                'observaciones' => $descripcion,
            ]);

            FacturaInternaDetalle::create([
                'factura_interna_id' => $factura->id,
                'impuesto_id' => $impuestoExento?->id,
                'servicio_id' => null,
                'descripcion' => $descripcion,
                'cantidad' => 1,
                'precio_unitario' => $montoRestante,
                'subtotal' => $montoRestante,
                'porcentaje_impuesto' => 0,
                'monto_impuesto' => 0,
                'total' => $montoRestante,
            ]);

            return $factura->fresh(['detalles', 'cliente']);
        });
    }

    /**
     * Suma monto al saldo a favor del primer servicio asociado a la factura o del cliente.
     */
    public function sumarSaldoAFavorCliente(int $clienteId, float $monto, ?int $facturaInternaId = null): void
    {
        $servicioId = null;
        if ($facturaInternaId) {
            $servicioId = FacturaInternaDetalle::where('factura_interna_id', $facturaInternaId)
                ->whereNotNull('servicio_id')
                ->value('servicio_id');
        }
        if (!$servicioId) {
            $servicioId = Servicio::where('cliente_id', $clienteId)->value('servicio_id');
        }
        if ($servicioId && $monto > 0) {
            Servicio::where('servicio_id', $servicioId)->increment('saldo_a_favor', $monto);
        }
    }

    /**
     * Tras eliminar una factura interna: si el cliente no tiene otra factura interna con saldo pendiente,
     * actualiza estado_pago a 'pagado' en los servicios que estaban en esa factura.
     *
     * @param int $clienteId
     * @param array<int> $servicioIds Servicio IDs que estaban en la factura eliminada.
     */
    public function revisarEstadoPagoServiciosTrasEliminarFacturaInterna(int $clienteId, array $servicioIds): void
    {
        if (empty($servicioIds)) {
            return;
        }

        $tieneOtraPendiente = FacturaInterna::where('cliente_id', $clienteId)
            ->get()
            ->contains(fn (FacturaInterna $f) => $f->saldo_pendiente > 0);

        if (!$tieneOtraPendiente) {
            Servicio::whereIn('servicio_id', $servicioIds)->update(['estado_pago' => 'pagado']);
        }
    }

    /**
     * Recalcula la calificación de pago del cliente según el porcentaje de cobros pagados a fecha.
     * Excelente: 100% a fecha; Bueno: >= 50% a fecha; Malo: < 50% a fecha.
     * Si no tiene cobros con factura, deja calificacion_pago en null.
     */
    public function recalcularCalificacionPagoCliente(Cliente $cliente): void
    {
        $cobros = Cobro::where('cliente_id', $cliente->cliente_id)
            ->whereHas('facturaInternas')
            ->with('facturaInternas')
            ->get();

        $total = $cobros->count();
        if ($total === 0) {
            $cliente->update(['calificacion_pago' => null]);
            return;
        }

        $aTiempo = $cobros->filter(function (Cobro $cobro) {
            $factura = $cobro->facturaInternas->first();
            if (!$factura || !$factura->fecha_vencimiento) {
                return false;
            }
            $fechaPago = $cobro->fecha_pago instanceof \DateTimeInterface
                ? Carbon::parse($cobro->fecha_pago)->startOfDay()
                : Carbon::parse($cobro->fecha_pago)->startOfDay();
            $venc = $factura->fecha_vencimiento instanceof Carbon
                ? $factura->fecha_vencimiento->copy()->startOfDay()
                : Carbon::parse($factura->fecha_vencimiento)->startOfDay();

            return $fechaPago->lte($venc);
        })->count();

        $porcentaje = $total > 0 ? ($aTiempo / $total) * 100 : 0;

        $calificacion = match (true) {
            $porcentaje >= 100 => Cliente::CALIFICACION_EXCELENTE,
            $porcentaje >= 50 => Cliente::CALIFICACION_BUENO,
            default => Cliente::CALIFICACION_MALO,
        };

        $cliente->update(['calificacion_pago' => $calificacion]);
    }

    /**
     * Elimina un cobro y revierte el estado de las facturas/servicios asociados.
     */
    public function eliminarCobro(Cobro $cobro): void
    {
        $clienteId = $cobro->cliente_id;
        $facturaIds = $cobro->facturaInternas()->pluck('factura_interna_id')->unique()->all();
        if (empty($facturaIds) && $cobro->factura_interna_id) {
            $facturaIds = [$cobro->factura_interna_id];
        }

        DB::transaction(function () use ($cobro, $facturaIds) {
            $cobro->delete();

            foreach ($facturaIds as $fid) {
                $factura = FacturaInterna::find($fid);
                if ($factura) {
                    $factura->refresh();
                    $estadoPago = $factura->saldo_pendiente <= 0 ? 'pagado' : ($factura->saldo_pendiente >= (float) $factura->total ? 'pendiente' : 'parcial');
                    $this->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, $estadoPago);
                    if ($factura->saldo_pendiente > 0 && $factura->estado === 'pagada') {
                        $factura->update(['estado' => 'emitida']);
                    }
                }
            }
        });

        $cliente = Cliente::find($clienteId);
        if ($cliente) {
            $this->recalcularCalificacionPagoCliente($cliente);
        }
    }

    /**
     * Actualiza estado_pago de todos los servicios asociados a una factura interna (vía sus detalles).
     */
    public function actualizarEstadoPagoServiciosDeFacturaInterna(int $facturaInternaId, string $estadoPago): void
    {
        $servicioIds = FacturaInternaDetalle::where('factura_interna_id', $facturaInternaId)
            ->whereNotNull('servicio_id')
            ->pluck('servicio_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($servicioIds)) {
            Servicio::whereIn('servicio_id', $servicioIds)->update(['estado_pago' => $estadoPago]);
        }
    }
}
