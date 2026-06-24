<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturacionParametro;
use App\Models\Impuesto;
use App\Models\Servicio;
use App\Models\SifenConfiguracion;
use App\Services\FacturacionService;
use App\Services\Sifen\SifenKudeService;
use App\Services\Sifen\SifenService;
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

        $mesDashboard = now()->startOfMonth();
        if ($request->filled('mes')) {
            try {
                $mesDashboard = Carbon::createFromFormat('Y-m', (string) $request->mes)->startOfMonth();
            } catch (\Throwable) {
                // Ignorar formato inválido; usar mes actual.
            }
        }

        $statsEmitidasMes = Factura::query()
            ->where('estado', 'emitida')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto_total, COALESCE(SUM(total_impuestos), 0) as monto_iva')
            ->first();

        $borradoresMes = Factura::query()
            ->where('estado', 'borrador')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->count();

        $montoBorradoresMes = (float) Factura::query()
            ->where('estado', 'borrador')
            ->whereYear('fecha_emision', $mesDashboard->year)
            ->whereMonth('fecha_emision', $mesDashboard->month)
            ->sum('total');

        return view('facturas.index', [
            'facturas' => $facturas,
            'clientes' => $clientes,
            'mesDashboard' => $mesDashboard,
            'mesDashboardLabel' => $mesDashboard->locale('es')->isoFormat('MMMM YYYY'),
            'statsEmitidasMes' => $statsEmitidasMes,
            'borradoresMes' => $borradoresMes,
            'montoBorradoresMes' => $montoBorradoresMes,
        ]);
    }

    public function create(Request $request)
    {
        $mes = now();
        $mesLabel = $mes->locale('es')->isoFormat('MMMM YYYY');

        $emisionesMes = Factura::query()
            ->where('estado', 'emitida')
            ->whereYear('fecha_emision', $mes->year)
            ->whereMonth('fecha_emision', $mes->month)
            ->selectRaw('cliente_id, COUNT(*) as cantidad, MAX(id) as ultima_factura_id, MAX(fecha_emision) as ultima_fecha')
            ->groupBy('cliente_id')
            ->get()
            ->keyBy('cliente_id');

        $query = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo'])
            ->orderBy('nombre')
            ->orderBy('apellido');

        if ($request->filled('buscar')) {
            $buscar = trim((string) $request->buscar);
            $query->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', '%'.$buscar.'%')
                    ->orWhere('apellido', 'like', '%'.$buscar.'%')
                    ->orWhere('cedula', 'like', '%'.$buscar.'%');
            });
        }

        if ($request->boolean('solo_pendientes')) {
            $query->whereNotIn('cliente_id', $emisionesMes->keys()->all());
        }

        $clientes = $query->paginate(25)->withQueryString();

        $totalActivos = Cliente::whereIn('estado', ['activo', 'inactivo'])->count();
        $emitidosMes = $emisionesMes->count();
        $pendientesMes = max(0, $totalActivos - $emitidosMes);

        return view('facturas.seleccionar-cliente', compact(
            'clientes',
            'emisionesMes',
            'mesLabel',
            'totalActivos',
            'emitidosMes',
            'pendientesMes',
        ));
    }

    public function createParaCliente(Cliente $cliente)
    {
        if (blank($cliente->cedula)) {
            return redirect()->route('facturas.create')
                ->with('error', 'El cliente debe tener cédula o RUC para emitir factura electrónica SIFEN.');
        }

        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo'])->orderBy('nombre')->get();
        $impuestos = Impuesto::activos();
        $clienteSeleccionado = $cliente;

        $sifenConfig = SifenConfiguracion::activa();
        $prefill = $this->construirPrefillSifen($sifenConfig);
        $detallesIniciales = $this->construirDetallesDesdeServiciosCliente($cliente);

        return view('facturas.create', compact(
            'clientes',
            'impuestos',
            'clienteSeleccionado',
            'prefill',
            'detallesIniciales',
            'sifenConfig',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function construirPrefillSifen(?SifenConfiguracion $config): array
    {
        if (! $config) {
            return [
                'numero_timbrado' => null,
                'timbrado_vigencia_desde' => null,
                'timbrado_vigencia_hasta' => null,
                'establecimiento' => 1,
                'punto_emision' => 1,
            ];
        }

        return [
            'numero_timbrado' => $config->numero_timbrado,
            'timbrado_vigencia_desde' => $config->timbrado_vigencia_desde?->format('Y-m-d'),
            'timbrado_vigencia_hasta' => $config->timbrado_vigencia_hasta?->format('Y-m-d'),
            'establecimiento' => $config->establecimiento ?? 1,
            'punto_emision' => $config->punto_expedicion ?? 1,
        ];
    }

    /**
     * @return list<array{descripcion: string, cantidad: float, precio_unitario: float, impuesto_id: int|null, servicio_id: int}>
     */
    private function construirDetallesDesdeServiciosCliente(Cliente $cliente): array
    {
        $impuestoIva = Impuesto::where('codigo', 'IVA10')->first() ?? Impuesto::activos()->firstWhere('porcentaje', '>', 0);
        $periodoDesde = now()->startOfMonth();
        $periodoHasta = now()->endOfMonth();
        $periodoStr = $periodoDesde->format('d/m/Y').' a '.$periodoHasta->format('d/m/Y');

        $servicios = Servicio::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->where('estado', Servicio::ESTADO_ACTIVO)
            ->with('plan')
            ->orderBy('servicio_id')
            ->get();

        $detalles = [];

        foreach ($servicios as $servicio) {
            $plan = $servicio->plan;
            $precioPlan = (float) ($plan?->precio ?? 0);
            if ($precioPlan <= 0) {
                continue;
            }

            $precio = FacturacionService::calcularPrecioProrrateado(
                $servicio,
                $periodoDesde->copy(),
                $periodoHasta->copy(),
                $precioPlan
            );

            $nombrePlan = $plan?->nombre ?? 'Servicio de internet';
            $detalles[] = [
                'descripcion' => sprintf('%s - %s Gs. - Período %s', $nombrePlan, number_format($precio, 0, ',', '.'), $periodoStr),
                'cantidad' => 1,
                'precio_unitario' => $precio,
                'impuesto_id' => $impuestoIva?->id,
                'servicio_id' => $servicio->servicio_id,
            ];

            $precioApp = (float) ($servicio->precio_app ?? 0);
            if ((bool) ($servicio->app_tv ?? false) && $precioApp > 0) {
                $detalles[] = [
                    'descripcion' => sprintf('Servicio especial - %s Gs. - Período %s', number_format($precioApp, 0, ',', '.'), $periodoStr),
                    'cantidad' => 1,
                    'precio_unitario' => $precioApp,
                    'impuesto_id' => $impuestoIva?->id,
                    'servicio_id' => $servicio->servicio_id,
                ];
            }
        }

        return $detalles;
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
            'detalles.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
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
                $calc = FacturaDetalle::calcularLinea(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'servicio_id' => $item['servicio_id'] ?? null,
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
        $factura->load(['cliente', 'detalles.impuesto', 'usuario']);

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
            'detalles.*.servicio_id' => ['nullable', 'integer', 'exists:servicios,servicio_id'],
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
                $calc = FacturaDetalle::calcularLinea(
                    (float) $item['cantidad'],
                    (float) $item['precio_unitario'],
                    $impuesto
                );
                FacturaDetalle::create([
                    'factura_electronica_id' => $factura->id,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'servicio_id' => $item['servicio_id'] ?? null,
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
     * Emite la factura electrónica: genera DE, firma, envía a SIFEN y produce KuDE PDF.
     */
    public function emitir(Factura $factura, SifenService $sifenService)
    {
        if ($factura->estado !== 'borrador') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo se pueden emitir facturas en estado borrador.');
        }

        try {
            $resultado = $sifenService->emitirDocumento($factura, true);
        } catch (\Throwable $e) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Error al emitir factura electrónica: '.$e->getMessage());
        }

        $mensaje = 'Factura electrónica emitida. CDC: '.$resultado['cdc'];
        if ($resultado['sifen'] && ($resultado['sifen']['protocolo'] ?? null)) {
            $mensaje .= ' · Protocolo: '.$resultado['sifen']['protocolo'];
        }
        if (($resultado['correo']['enviado'] ?? false) && ($resultado['correo']['destinatario'] ?? null)) {
            $mensaje .= ' · Correo enviado a '.$resultado['correo']['destinatario'];
        }

        return redirect()->route('facturas.show', $factura)->with('success', $mensaje);
    }

    /**
     * Descarga el KuDE PDF de una factura emitida.
     */
    public function descargarKude(Factura $factura, SifenKudeService $kudeService)
    {
        if ($factura->estado !== 'emitida') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo las facturas emitidas tienen KuDE PDF.');
        }

        $config = SifenConfiguracion::activa();
        if (! $config) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay configuración SIFEN activa.');
        }

        try {
            $pdfPath = $kudeService->generar($factura, $config, $factura->set_qr_url);
            $factura->update(['pdf_path' => $pdfPath]);
        } catch (\Throwable $e) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No se pudo generar el KuDE PDF: '.$e->getMessage());
        }

        $ruta = storage_path($pdfPath);

        return response()->download($ruta, basename($ruta));
    }

    /**
     * Vista KuDE formato ticket POS 80 mm (impresión térmica).
     */
    public function verKudePos(Factura $factura, SifenKudeService $kudeService)
    {
        if ($factura->estado !== 'emitida') {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Solo las facturas emitidas tienen KuDE.');
        }

        if (! $factura->set_cdc) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'La factura no tiene CDC; no se puede generar el KuDE.');
        }

        $config = SifenConfiguracion::activa();
        if (! $config) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay configuración SIFEN activa.');
        }

        return view('facturas.kude-pos-80', $kudeService->datosParaVista($factura, $config, $factura->set_qr_url));
    }

    /**
     * Descarga el XML firmado del DE.
     */
    public function descargarXml(Factura $factura)
    {
        if (! $factura->xml_path) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'No hay XML generado para esta factura.');
        }

        if (str_contains($factura->xml_path, 'DE_borrador_')) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'El XML disponible es solo borrador (sin firma). Emita el documento antes de descargar el XML firmado.');
        }

        $ruta = storage_path($factura->xml_path);
        if (! is_file($ruta)) {
            return redirect()->route('facturas.show', $factura)
                ->with('error', 'Archivo XML no encontrado en el servidor.');
        }

        $nombre = basename($ruta);

        return response()->file($ruta, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
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
        $impuestoPlan = Impuesto::where('codigo', 'IVA10')->first() ?? $impuestoExento;

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
            'impuestoPlan',
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
     * Formulario: factura interna por una fracción del saldo pendiente del cliente (desde el servicio).
     */
    public function crearInternaFraccionDeudaServicio(Servicio $servicio, FacturacionService $facturacionService)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede emitir factura en un servicio cancelado.');
        }

        $saldoPendiente = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($saldoPendiente <= 0) {
            return redirect()->route('servicios.index')->with('error', 'Este cliente no tiene saldo pendiente en facturas internas para fraccionar.');
        }

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

        $descripcionLineaDefault = sprintf('Fracción deuda — Servicio #%s', $servicio->servicio_id);

        return view('facturas.crear-interna-servicio-fraccion-deuda', compact(
            'servicio',
            'saldoPendiente',
            'periodoDesde',
            'periodoHasta',
            'fechaEmision',
            'fechaVencimiento',
            'fechaPago',
            'descripcionLineaDefault',
        ));
    }

    /**
     * Guarda factura interna con un ítem exento por el monto fraccionado (≤ saldo pendiente del cliente).
     */
    public function storeInternaFraccionDeudaServicio(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede emitir factura en un servicio cancelado.');
        }

        $saldoMax = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($saldoMax <= 0) {
            return redirect()->route('servicios.index')->with('error', 'Este cliente no tiene saldo pendiente en facturas internas.');
        }

        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
            'monto_fraccion' => ['required', 'numeric', 'min:1'],
            'descripcion_linea' => ['nullable', 'string', 'max:500'],
        ]);

        $monto = (float) $validated['monto_fraccion'];
        $saldoActual = $facturacionService->saldoPendienteInternasCliente((int) $servicio->cliente_id);
        if ($monto > $saldoActual + 0.0001) {
            return redirect()->route('facturas.crear-interna-servicio-fraccion-deuda', $servicio)
                ->withInput()
                ->with('error', 'El monto no puede superar el saldo pendiente actual ('.number_format($saldoActual, 0, ',', '.').' Gs.).');
        }

        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $lineaDesc = trim((string) ($validated['descripcion_linea'] ?? '')) !== ''
            ? trim($validated['descripcion_linea'])
            : sprintf('Fracción deuda — %s Gs. — Servicio #%s', number_format($monto, 0, ',', '.'), $servicio->servicio_id);

        $items = [[
            'descripcion' => $lineaDesc,
            'cantidad' => 1,
            'precio_unitario' => $monto,
            'impuesto_id' => $impuestoExento->id,
        ]];

        $obs = sprintf(
            'Factura fraccionada por deuda — Servicio #%d — Período %s a %s',
            $servicio->servicio_id,
            Carbon::parse($validated['periodo_desde'])->format('d/m/Y'),
            Carbon::parse($validated['periodo_hasta'])->format('d/m/Y')
        );

        try {
            $factura = $facturacionService->generarFacturaInternaDesdeUnServicio(
                $servicio,
                Carbon::parse($validated['periodo_desde']),
                Carbon::parse($validated['periodo_hasta']),
                $validated['fecha_emision'],
                $validated['fecha_vencimiento'],
                $validated['fecha_pago'] ?? null,
                0.0,
                $items,
                $request->user()?->usuario_id,
                true,
                $obs
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna fraccionada por deuda creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio-fraccion-deuda', $servicio)
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Formulario: factura interna por servicio especial (sin período ni vencimiento).
     */
    public function crearInternaServicioEspecial(Servicio $servicio)
    {
        $servicio->load(['plan', 'cliente']);
        if (! $servicio->cliente) {
            return redirect()->route('servicios.index')->with('error', 'El servicio no tiene cliente asociado.');
        }
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->route('servicios.index')->with('error', 'No se puede facturar un servicio cancelado.');
        }

        $impuestos = Impuesto::activos();
        $impuestoExento = Impuesto::where('codigo', 'EXENTO')->first() ?? Impuesto::first();
        $fechaEmision = now()->toDateString();
        $precioPlan = $servicio->plan ? (float) $servicio->plan->precio : 0;
        $descripcion = $servicio->plan
            ? sprintf('Servicio especial — %s', $servicio->plan->nombre)
            : sprintf('Servicio especial #%d', $servicio->servicio_id);

        return view('facturas.crear-interna-servicio-especial', compact(
            'servicio',
            'impuestos',
            'impuestoExento',
            'fechaEmision',
            'precioPlan',
            'descripcion',
        ));
    }

    /**
     * Guarda factura interna por servicio especial.
     */
    public function storeCrearInternaServicioEspecial(Request $request, Servicio $servicio, FacturacionService $facturacionService)
    {
        $validated = $request->validate([
            'fecha_emision' => ['required', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.impuesto_id' => ['nullable', 'exists:impuestos,id'],
        ]);

        $servicio->load(['plan', 'cliente']);

        try {
            $factura = $facturacionService->generarFacturaInternaServicioEspecial(
                $servicio,
                $validated['fecha_emision'],
                (float) ($validated['descuento'] ?? 0),
                $validated['items'],
                $request->user()?->usuario_id,
                $validated['observaciones'] ?? null
            );

            return redirect()->route('factura-internas.show', $factura)
                ->with('success', 'Factura interna por servicio especial creada correctamente.');
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('facturas.crear-interna-servicio-especial', $servicio)
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
