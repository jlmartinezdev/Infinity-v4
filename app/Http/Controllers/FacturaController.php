<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturacionParametro;
use App\Models\Impuesto;
use App\Models\Servicio;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FacturaController extends Controller
{
    public function index(Request $request)
    {
        $query = Factura::with(['cliente', 'usuario'])
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_emision', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->hasta);
        }

        $facturas = $query->paginate(15)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get();

        return view('facturas.index', compact('facturas', 'clientes'));
    }

    public function create()
    {
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo'])->orderBy('nombre')->get();
        $impuestos = Impuesto::activos();

        return view('facturas.create', compact('clientes', 'impuestos'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'tipo_documento' => ['required', 'string', 'in:factura_contado,factura_credito,nota_credito,nota_debito'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'moneda' => ['required', 'string', 'in:PYG,USD'],
            'numero_timbrado' => ['nullable', 'string', 'max:20'],
            'timbrado_vigencia_desde' => ['nullable', 'date'],
            'timbrado_vigencia_hasta' => ['nullable', 'date'],
            'establecimiento' => ['nullable', 'integer', 'min:1', 'max:255'],
            'punto_emision' => ['nullable', 'integer', 'min:1', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
        ]);

        $factura = \DB::transaction(function () use ($validated, $request) {
            $factura = Factura::create([
                'cliente_id' => $validated['cliente_id'],
                'tipo_documento' => $validated['tipo_documento'],
                'estado' => 'borrador',
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'moneda' => $validated['moneda'],
                'numero_timbrado' => $validated['numero_timbrado'] ?? null,
                'timbrado_vigencia_desde' => $validated['timbrado_vigencia_desde'] ?? null,
                'timbrado_vigencia_hasta' => $validated['timbrado_vigencia_hasta'] ?? null,
                'establecimiento' => $validated['establecimiento'] ?? 1,
                'punto_emision' => $validated['punto_emision'] ?? 1,
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario_id' => $request->user()?->usuario_id,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
            ]);

            foreach ($validated['detalles'] as $item) {
                $impuesto = isset($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularDesdePrecio(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);
            }

            $factura->load('detalles');
            $factura->recalcularTotales();
            return $factura;
        });

        return redirect()->route('facturas.show', $factura)->with('success', 'Factura creada correctamente.');
    }

    public function show(Factura $factura)
    {
        $factura->load(['cliente', 'detalles.impuesto', 'usuario', 'cobros']);

        return view('facturas.show', compact('factura'));
    }

    public function edit(Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden editar facturas en estado borrador.');
        }
        $factura->load('detalles');
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo'])->orderBy('nombre')->get();
        $impuestos = Impuesto::activos();

        return view('facturas.edit', compact('factura', 'clientes', 'impuestos'));
    }

    public function update(Request $request, Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)->with('error', 'Solo se pueden editar facturas en borrador.');
        }

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'tipo_documento' => ['required', 'string', 'in:factura_contado,factura_credito,nota_credito,nota_debito'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'moneda' => ['required', 'string', 'in:PYG,USD'],
            'numero_timbrado' => ['nullable', 'string', 'max:20'],
            'timbrado_vigencia_desde' => ['nullable', 'date'],
            'timbrado_vigencia_hasta' => ['nullable', 'date'],
            'establecimiento' => ['nullable', 'integer', 'min:1', 'max:255'],
            'punto_emision' => ['nullable', 'integer', 'min:1', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:255'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
        ]);

        \DB::transaction(function () use ($factura, $validated) {
            $factura->update([
                'cliente_id' => $validated['cliente_id'],
                'tipo_documento' => $validated['tipo_documento'],
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'] ?? null,
                'moneda' => $validated['moneda'],
                'numero_timbrado' => $validated['numero_timbrado'] ?? null,
                'timbrado_vigencia_desde' => $validated['timbrado_vigencia_desde'] ?? null,
                'timbrado_vigencia_hasta' => $validated['timbrado_vigencia_hasta'] ?? null,
                'establecimiento' => $validated['establecimiento'] ?? 1,
                'punto_emision' => $validated['punto_emision'] ?? 1,
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $factura->detalles()->delete();
            foreach ($validated['detalles'] as $item) {
                $impuesto = isset($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularDesdePrecio(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ]);
            }
            $factura->load('detalles');
            $factura->recalcularTotales();
        });

        return redirect()->route('facturas.show', $factura)->with('success', 'Factura actualizada correctamente.');
    }

    public function destroy(Factura $factura)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.index')->with('error', 'Solo se pueden eliminar facturas en borrador.');
        }
        $factura->delete();
        return redirect()->route('facturas.index')->with('success', 'Factura eliminada.');
    }

    /**
     * Formulario para generar factura interna (mensual) desde servicios activos del cliente.
     */
    public function generarInterna(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);
        $mesActual = now()->format('Y-m');
        $periodoDesde = $request->get('periodo_desde', now()->startOfMonth()->toDateString());
        $periodoHasta = $request->get('periodo_hasta', now()->endOfMonth()->toDateString());

        return view('facturas.generar-interna', compact('clientes', 'periodoDesde', 'periodoHasta', 'mesActual'));
    }

    /**
     * Generar y guardar factura interna para el cliente y período indicados.
     */
    public function storeGenerarInterna(Request $request, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $desde = Carbon::parse($validated['periodo_desde']);
        $hasta = Carbon::parse($validated['periodo_hasta']);

        try {
            $factura = $facturacionService->generarFacturaInterna(
                $cliente,
                $desde,
                $hasta,
                $request->user()?->usuario_id
            );
            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna generada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.generar-interna')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Recibe servicio_ids desde el listado de servicios y redirige al formulario de generar factura interna.
     */
    public function prepararInternaDesdeServicios(Request $request)
    {
        $ids = $request->input('servicio_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];

        if (empty($ids)) {
            return redirect()->route('servicios.index')
                ->with('error', 'Seleccione al menos un servicio para generar la factura interna.');
        }

        session(['factura_interna_servicio_ids' => $ids]);
        return redirect()->route('facturas.generar-interna-desde-servicios');
    }

    /**
     * Formulario para generar factura(s) interna(s) con los servicios pre-seleccionados (desde listado de servicios).
     */
    public function generarInternaDesdeServicios()
    {
        $servicioIds = session('factura_interna_servicio_ids', []);

        if (empty($servicioIds)) {
            return redirect()->route('servicios.index')
                ->with('error', 'No hay servicios seleccionados. Marque las filas en el listado de servicios.');
        }

        $servicios = Servicio::whereIn('servicio_id', $servicioIds)
            ->with(['plan', 'cliente'])
            ->orderBy('cliente_id')
            ->get();

        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $periodoDesdeCarbon = Carbon::parse($periodoDesde);
        $periodoHastaCarbon = Carbon::parse($periodoHasta);

        $prorrateosPorServicio = [];
        foreach ($servicios as $s) {
            $precioPlan = $s->plan ? (float) $s->plan->precio : 0;
            $prorrateosPorServicio[$s->servicio_id] = \App\Services\FacturacionService::obtenerDetalleProrrateo($s, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);
        }

        return view('facturas.generar-interna-desde-servicios', [
            'servicios' => $servicios,
            'periodoDesde' => $periodoDesde,
            'periodoHasta' => $periodoHasta,
            'prorrateosPorServicio' => $prorrateosPorServicio,
        ]);
    }

    /**
     * Generar factura(s) interna(s) para los servicios guardados en sesión.
     */
    public function storeGenerarInternaDesdeServicios(Request $request, FacturacionService $facturacionService)
    {
        $servicioIds = session('factura_interna_servicio_ids', []);

        if (empty($servicioIds)) {
            return redirect()->route('servicios.index')
                ->with('error', 'La sesión expiró. Seleccione nuevamente los servicios.');
        }

        $validated = $request->validate([
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
        ]);

        $desde = Carbon::parse($validated['periodo_desde']);
        $hasta = Carbon::parse($validated['periodo_hasta']);

        try {
            $resultado = $facturacionService->generarFacturaInternaDesdeServicios(
                $servicioIds,
                $desde,
                $hasta,
                $request->user()?->usuario_id
            );

            session()->forget('factura_interna_servicio_ids');

            $primera = $resultado['primera'];
            $total = $resultado['facturas']->count();

            $mensaje = $total === 1
                ? 'Factura interna generada correctamente.'
                : "Se generaron {$total} facturas internas (una por cliente).";

            return redirect()->route('factura-internas.show', $primera)
                ->with('success', $mensaje);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.generar-interna-desde-servicios')
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Formulario para crear factura interna desde un solo servicio (con campos editables).
     */
    public function crearInternaDesdeServicio(Servicio $servicio)
    {
        $servicio->load(['plan', 'cliente']);
        $impuestos = Impuesto::activos();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();

        $periodoDesde = now()->startOfMonth()->toDateString();
        $periodoHasta = now()->endOfMonth()->toDateString();
        $fechaEmision = now()->toDateString();

        $diaVenc = FacturacionParametro::diaVencimiento();
        $diaCobro = FacturacionParametro::diaFechaCobro();
        $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaVenc, 28) - 1);
        if ($fechaVencimiento->isPast()) {
            $fechaVencimiento = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaVenc, 28) - 1);
        }
        $fechaVencimiento = $fechaVencimiento->toDateString();

        $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addDays(min($diaCobro, 28) - 1);
        if ($fechaPagoCarbon->isPast()) {
            $fechaPagoCarbon = Carbon::createFromDate(now()->year, now()->month, 1)->addMonth()->addDays(min($diaCobro, 28) - 1);
        }
        $fechaPago = $fechaPagoCarbon->toDateString();
        $precioPlan = $servicio->plan ? (float) $servicio->plan->precio : 0;
        $periodoDesdeCarbon = Carbon::parse($periodoDesde);
        $periodoHastaCarbon = Carbon::parse($periodoHasta);
        $precio = \App\Services\FacturacionService::calcularPrecioProrrateado($servicio, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);
        $descripcion = $servicio->plan
            ? sprintf('%s - %s Gs. - Período %s a %s', $servicio->plan->nombre, number_format($precio, 0, ',', '.'), now()->format('d/m/Y'), now()->endOfMonth()->format('d/m/Y'))
            : 'Servicio';

        $prorrateoInfo = \App\Services\FacturacionService::obtenerDetalleProrrateo($servicio, $periodoDesdeCarbon, $periodoHastaCarbon, $precioPlan);

        return view('facturas.crear-interna-servicio', compact(
            'servicio',
            'impuestos',
            'impuestoExento',
            'periodoDesde',
            'periodoHasta',
            'fechaEmision',
            'fechaVencimiento',
            'fechaPago',
            'precio',
            'descripcion',
            'prorrateoInfo',
        ));
    }

    /**
     * Guarda factura interna creada desde un servicio con datos editables.
     */
    public function storeCrearInternaDesdeServicio(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.impuesto_id' => ['nullable', 'exists:impuestos,id'],
        ]);

        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }

        try {
            $factura = $facturacionService->generarFacturaInternaDesdeUnServicio(
                $servicio,
                Carbon::parse($validated['periodo_desde']),
                Carbon::parse($validated['periodo_hasta']),
                $validated['fecha_emision'],
                $validated['fecha_vencimiento'],
                $validated['fecha_pago'] ?? null,
                (float) ($validated['descuento'] ?? 0),
                $validated['items'],
                $request->user()?->usuario_id
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio', $servicio)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Ejecuta suspensión por falta de pago: servicios de clientes con facturas vencidas y saldo pendiente se marcan como suspendidos.
     */
    public function suspenderFaltaPago(FacturacionService $facturacionService)
    {
        $suspendidos = $facturacionService->suspenderPorFaltaPago();
        $cantidad = count($suspendidos);
        return redirect()->route('facturas.index')
            ->with('success', $cantidad > 0
                ? "Suspensión aplicada: {$cantidad} servicio(s) suspendido(s) por falta de pago."
                : 'No había servicios pendientes de suspender por falta de pago.');
    }
}
