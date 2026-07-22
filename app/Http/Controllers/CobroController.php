<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\CobroResumen;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\User;
use App\Services\FacturacionService;
use App\Support\CobrosMesVentana;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CobroController extends Controller
{
    public function __construct(
        protected FacturacionService $facturacionService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';

        $query = Cobro::with(['cliente', 'facturaInternas', 'usuario'])
            ->orderBy('id', 'desc');
        $this->applyCobrosListFilters($request, $query, $user, $esAdmin);

        $totalFiltrado = (float) (clone $query)->sum('monto');

        $cobros = $query->paginate(20)->withQueryString();
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);

        $usuariosConCobros = $esAdmin
            ? User::whereIn('usuario_id', Cobro::select('usuario_id')->distinct()->pluck('usuario_id'))
                ->orderBy('name')
                ->get(['usuario_id', 'name'])
            : collect();

        $hoy = now()->toDateString();

        $mesReferenciaCobros = now()->startOfMonth();
        $rangosVentanaMes = CobrosMesVentana::rangosMesActual();
        $cobrosMesVentanaQuery = CobrosMesVentana::queryParamsDesdeRangos($rangosVentanaMes);

        $statsQuery = Cobro::query();
        $this->applyCobrosListFilters($request, $statsQuery, $user, $esAdmin);
        $cobrosHoy = (float) (clone $statsQuery)->whereDate('fecha_pago', $hoy)->sum('monto');

        // Admin con "Todos" los usuarios: total del ciclo desde cobros_resumen (mismo criterio que el dashboard).
        // Con usuario concreto o usuario no admin: suma en vivo desde cobros.
        $usarResumenCobrosMes = $esAdmin && ! $request->filled('usuario_id');

        if ($usarResumenCobrosMes) {
            $cobrosMes = CobroResumen::totalCobradoParaMes($mesReferenciaCobros);
        } else {
            $mesQuery = Cobro::query();
            $this->applyCobrosDimensionFilters($request, $mesQuery, $user, $esAdmin);
            $mesQuery->whereBetween('fecha_pago', [$rangosVentanaMes['desdeVentana'], $rangosVentanaMes['hastaVentana']]);
            CobrosMesVentana::aplicarFiltroAtribucionMesReferencia($mesQuery, $mesReferenciaCobros);
            if ($request->filled('factura_desde') && $request->filled('factura_hasta')) {
                $fd = $request->factura_desde;
                $fh = $request->factura_hasta;
                $mesQuery->whereHas('facturaInternas', function ($q) use ($fd, $fh) {
                    $q->whereDate('factura_internas.created_at', '>=', $fd)
                        ->whereDate('factura_internas.created_at', '<=', $fh);
                });
            }
            $cobrosMes = (float) $mesQuery->sum('cobros.monto');
        }

        $totalPendienteMes = CobroResumen::totalPendienteParaMes($mesReferenciaCobros);
        $totalPendiente = (float) (DB::table('factura_internas')
            ->selectRaw('SUM(total - COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)) as total')
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->whereRaw('total > COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)')
            ->value('total') ?? 0);

        $formasPago = Cobro::formasPago();

        return view('cobros.index', compact(
            'cobros',
            'clientes',
            'totalFiltrado',
            'cobrosHoy',
            'cobrosMes',
            'cobrosMesVentanaQuery',
            'totalPendienteMes',
            'totalPendiente',
            'esAdmin',
            'usuariosConCobros',
            'formasPago',
            'usarResumenCobrosMes'
        ));
    }

    /**
     * Lista de servicios para cobros: búsqueda instantánea por CI/nombre (Vue).
     */
    public function servicios(Request $request)
    {
        $serviciosCollection = Servicio::with(['cliente', 'plan'])
            ->whereHas('cliente')
            ->orderBy('servicio_id', 'desc')
            ->get();

        $clienteIds = $serviciosCollection->pluck('cliente_id')->unique()->filter()->values()->all();
        $pendientesPorCliente = collect();
        if (!empty($clienteIds)) {
            $facturas = FacturaInterna::with('cobros')
                ->whereIn('cliente_id', $clienteIds)
                ->get();
            $pendientesPorCliente = $facturas->groupBy('cliente_id')->map(function ($items) {
                $conSaldo = $items->filter(fn ($f) => $f->saldo_pendiente > 0);
                return ['cantidad' => $conSaldo->count(), 'monto' => $conSaldo->sum('saldo_pendiente')];
            });
        }

        $servicios = $serviciosCollection->map(function ($s) use ($pendientesPorCliente) {
            $pend = $pendientesPorCliente->get($s->cliente_id, ['cantidad' => 0, 'monto' => 0]);
            return [
                'servicio_id' => $s->servicio_id,
                'cliente' => $s->cliente ? [
                    'cliente_id' => $s->cliente->cliente_id,
                    'cedula' => $s->cliente->cedula,
                    'nombre' => $s->cliente->nombre,
                    'apellido' => $s->cliente->apellido,
                    'direccion' => $s->cliente->direccion,
                ] : null,
                'plan' => $s->plan ? [
                    'nombre' => $s->plan->nombre,
                    'precio' => $s->plan->precio,
                ] : null,
                'facturas_pendientes' => $pend,
            ];
        })->values()->all();

        return view('cobros.servicios', [
            'servicios' => $servicios,
            'urlCobrosIndex' => route('cobros.index'),
            'urlEditServicioBase' => url('servicios') . '/__servicio_id__/edit',
            'urlCrearCobroBase' => route('cobros.create') . '?cliente_id=__cliente_id__',
            'canCrearCobro' => auth()->user()?->tienePermiso('cobros.crear') ?? false,
        ]);
    }

    public function create(Request $request)
    {
        $clienteId = $request->get('cliente_id');
        $facturaInternaId = $request->get('factura_interna_id');

        if (!$clienteId && $facturaInternaId) {
            $fi = FacturaInterna::find($facturaInternaId);
            if ($fi) {
                $clienteId = $fi->cliente_id;
            }
        }

        $facturasInternasPendientes = collect();
        $cliente = null;
        if ($clienteId) {
            $cliente = Cliente::find($clienteId);
            if ($cliente) {
                $facturasInternasPendientes = FacturaInterna::where('cliente_id', $cliente->cliente_id)
                    ->where('estado', '!=', 'anulada')
                    ->with(['cobros', 'detalles'])
                    ->get()
                    ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
                    ->map(fn (FacturaInterna $f) => [
                        'id' => $f->id,
                        'periodo_desde' => $f->periodo_desde,
                        'periodo_hasta' => $f->periodo_hasta,
                        'saldo_pendiente' => (float) $f->saldo_pendiente,
                        'concepto' => $this->facturacionService->conceptoCobroDesdeFactura($f->id) ?? '',
                    ])
                    ->values();
            }
        }

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        $montoSugerido = null;
        $facturaInternaIdsPreseleccionados = [];
        if ($facturaInternaId) {
            $fi = FacturaInterna::with('cobros')->find($facturaInternaId);
            if ($fi && $fi->saldo_pendiente > 0) {
                $montoSugerido = $fi->saldo_pendiente;
                $facturaInternaIdsPreseleccionados = [$fi->id];
            }
        } elseif ($facturasInternasPendientes->isNotEmpty()) {
            $facturaInternaIdsPreseleccionados = $facturasInternasPendientes->pluck('id')->values()->all();
            $montoSugerido = $facturasInternasPendientes->sum('saldo_pendiente');
        }

        $conceptoSugerido = $this->facturacionService->conceptoCobroDesdeFactura($facturaInternaId);

        return view('cobros.create', [
            'clientes' => $clientes,
            'cliente' => $cliente,
            'facturasInternasPendientes' => $facturasInternasPendientes,
            'facturaInternaId' => $facturaInternaId,
            'facturaInternaIdsPreseleccionados' => $facturaInternaIdsPreseleccionados,
            'formasPago' => Cobro::formasPago(),
            'montoSugerido' => $montoSugerido,
            'conceptoSugerido' => $conceptoSugerido,
        ]);
    }

    public function store(Request $request)
    {
        $ids = $request->input('factura_interna_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];
        $ids = array_values(array_unique($ids));

        if (count($ids) > 1) {
            return $this->storeMulticobroDesdeCreate($request, $ids);
        }

        $facturaInternaId = $ids[0] ?? $request->input('factura_interna_id');
        $request->merge(['factura_interna_id' => $facturaInternaId ?: null]);

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'factura_interna_id' => ['nullable', 'integer', 'exists:factura_internas,id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'concepto' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $monto = (float) $validated['monto'];
        $saldoAntes = null;
        $facturaOrigenId = null;

        if (!empty($validated['factura_interna_id'])) {
            $factura = FacturaInterna::find($validated['factura_interna_id']);
            if ($factura) {
                $saldoAntes = $factura->saldo_pendiente;
                $facturaOrigenId = $factura->id;
            }
        }

        $cobro = $this->facturacionService->registrarCobro($validated, $request->user()?->usuario_id);

        $registroSaldoAFavor = false;
        if ($saldoAntes !== null && $facturaOrigenId !== null) {
            if ($monto > $saldoAntes) {
                $exceso = $monto - $saldoAntes;
                $this->facturacionService->sumarSaldoAFavorCliente(
                    $cliente->cliente_id,
                    $exceso,
                    $facturaOrigenId
                );
                $registroSaldoAFavor = true;
            }
        } else {
            $this->facturacionService->sumarSaldoAFavorCliente(
                $cliente->cliente_id,
                $monto
            );
            $registroSaldoAFavor = true;
        }

        $mensaje = 'Cobro registrado correctamente. Recibo: ' . $cobro->numero_recibo;
        if ($registroSaldoAFavor) {
            $mensaje .= ' El monto se registró como saldo a favor del servicio.';
        }

        return $this->redirectTrasCobro($cobro, $mensaje);
    }

    /**
     * Multicobro desde el formulario create: un solo cobro que aplica a varias facturas.
     */
    private function storeMulticobroDesdeCreate(Request $request, array $ids): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'concepto' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $facturas = FacturaInterna::with('cliente')
            ->whereIn('id', $ids)
            ->where('cliente_id', $validated['cliente_id'])
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
            ->values();

        if ($facturas->isEmpty()) {
            return redirect()->back()->withInput()->with('error', 'Ninguna de las facturas seleccionadas tiene saldo pendiente.');
        }

        $montoTotal = (float) $validated['monto'];
        $totalSaldo = $facturas->sum(fn (FacturaInterna $f) => $f->saldo_pendiente);
        $usuarioId = $request->user()?->usuario_id;

        $montos = [];
        $acum = 0;
        $n = $facturas->count();
        foreach ($facturas as $i => $f) {
            $saldo = (float) $f->saldo_pendiente;
            if ($i === $n - 1) {
                $montos[] = round($montoTotal - $acum, 2);
            } else {
                $m = round($montoTotal * ($saldo / $totalSaldo), 2);
                $montos[] = $m;
                $acum += $m;
            }
        }

        $items = [];
        foreach ($facturas as $i => $factura) {
            $monto = $montos[$i];
            if ($monto > 0) {
                $items[] = ['id' => $factura->id, 'monto' => $monto];
            }
        }

        if (empty($items)) {
            return redirect()->back()->withInput()->with('error', 'No se pudo distribuir el monto entre las facturas.');
        }

        $cobro = $this->facturacionService->registrarCobro([
            'cliente_id' => $validated['cliente_id'],
            'monto' => $montoTotal,
            'fecha_pago' => $validated['fecha_pago'],
            'forma_pago' => $validated['forma_pago'],
            'referencia' => $validated['referencia'] ?? null,
            'concepto' => $validated['concepto'] ?? null,
            'observaciones' => $validated['observaciones'] ?? null,
            'factura_interna_items' => $items,
        ], $usuarioId);

        $mensaje = 'Cobro registrado. Recibo: ' . $cobro->numero_recibo;
        return $this->redirectTrasCobro($cobro, $mensaje);
    }

    /**
     * Formulario multicobro: facturas internas seleccionadas, monto total se reparte proporcional al saldo.
     */
    public function multicobro(Request $request)
    {
        $ids = $request->input('factura_interna_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : array_values(array_unique($ids));

        if (empty($ids)) {
            return redirect()->route('factura-internas.pendientes')
                ->with('error', 'Seleccione al menos una factura interna para multicobro.');
        }

        $facturas = FacturaInterna::with('cliente')
            ->whereIn('id', $ids)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
            ->values();

        if ($facturas->isEmpty()) {
            return redirect()->route('factura-internas.pendientes')
                ->with('error', 'Ninguna de las facturas seleccionadas tiene saldo pendiente.');
        }

        $totalSaldo = $facturas->sum(fn (FacturaInterna $f) => $f->saldo_pendiente);
        $facturasPorCliente = $facturas->groupBy('cliente_id');
        $cantidadCobros = $facturasPorCliente->count();

        return view('cobros.multicobro', [
            'facturas' => $facturas,
            'totalSaldo' => $totalSaldo,
            'cantidadCobros' => $cantidadCobros,
            'formasPago' => Cobro::formasPago(),
        ]);
    }

    /**
     * Registrar multicobro: si hay clientes diferentes, crea un cobro por cliente.
     * Si todas las facturas son del mismo cliente, un solo cobro con varias facturas.
     */
    public function storeMulticobro(Request $request)
    {
        $ids = $request->input('factura_interna_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];

        if (empty($ids)) {
            return redirect()->route('factura-internas.pendientes')
                ->with('error', 'Seleccione al menos una factura interna.');
        }

        $facturas = FacturaInterna::with('cliente')
            ->whereIn('id', $ids)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
            ->values();

        if ($facturas->isEmpty()) {
            return redirect()->route('factura-internas.pendientes')
                ->with('error', 'Ninguna factura con saldo pendiente.');
        }

        $validated = $request->validate([
            'monto_total' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $montoTotal = (float) $validated['monto_total'];
        $totalSaldo = $facturas->sum(fn (FacturaInterna $f) => $f->saldo_pendiente);

        if ($totalSaldo <= 0) {
            return redirect()->route('factura-internas.pendientes')->with('error', 'Saldo total inválido.');
        }

        $usuarioId = $request->user()?->usuario_id;
        $facturasPorCliente = $facturas->groupBy('cliente_id');

        $cobrosCreados = [];
        $avisosMikrotik = [];

        foreach ($facturasPorCliente as $clienteId => $facturasCliente) {
            $facturasCliente = $facturasCliente->values();
            $saldoCliente = $facturasCliente->sum(fn (FacturaInterna $f) => $f->saldo_pendiente);
            $montoCliente = round($montoTotal * ($saldoCliente / $totalSaldo), 2);
            if ($montoCliente <= 0) {
                continue;
            }

            $montos = [];
            $acum = 0;
            $n = $facturasCliente->count();
            foreach ($facturasCliente as $i => $f) {
                $saldo = (float) $f->saldo_pendiente;
                if ($i === $n - 1) {
                    $montos[] = round($montoCliente - $acum, 2);
                } else {
                    $m = round($montoCliente * ($saldo / $saldoCliente), 2);
                    $montos[] = $m;
                    $acum += $m;
                }
            }

            $items = [];
            foreach ($facturasCliente as $i => $factura) {
                $monto = $montos[$i];
                if ($monto > 0) {
                    $items[] = ['id' => $factura->id, 'monto' => $monto];
                }
            }

            if (empty($items)) {
                continue;
            }

            $cobro = $this->facturacionService->registrarCobro([
                'cliente_id' => $clienteId,
                'monto' => array_sum(array_column($items, 'monto')),
                'fecha_pago' => $validated['fecha_pago'],
                'forma_pago' => $validated['forma_pago'],
                'referencia' => $validated['referencia'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'factura_interna_items' => $items,
            ], $usuarioId);
            $cobrosCreados[] = $cobro;
            $avisosMikrotik = array_merge($avisosMikrotik, $this->facturacionService->avisosUltimoCobro);
        }

        if (empty($cobrosCreados)) {
            return redirect()->route('factura-internas.pendientes')->with('error', 'No se pudo distribuir el monto.');
        }

        $primero = $cobrosCreados[0];
        $mensaje = count($cobrosCreados) === 1
            ? 'Cobro registrado. Recibo: ' . $primero->numero_recibo
            : count($cobrosCreados) . ' cobros registrados (recibos: ' . collect($cobrosCreados)->pluck('numero_recibo')->join(', ') . ').';

        if (count($cobrosCreados) > 1) {
            $redirect = redirect()->route('cobros.multicobro-result')
                ->with('success', $mensaje)
                ->with('cobros_multicobro_ids', collect($cobrosCreados)->pluck('id')->all());
            $warning = implode(' ', array_unique(array_filter($avisosMikrotik)));
            if ($warning !== '') {
                $redirect->with('warning', $warning);
            }

            return $redirect;
        }

        return $this->redirectTrasCobro($primero, $mensaje);
    }

    /**
     * Redirige al recibo y muestra aviso si MikroTik no respondió al reactivar el servicio.
     */
    protected function redirectTrasCobro(Cobro $cobro, string $mensajeExito): \Illuminate\Http\RedirectResponse
    {
        $redirect = redirect()->route('cobros.show', $cobro)->with('success', $mensajeExito);
        $avisos = $this->avisosMikrotikUltimoCobro();
        if ($avisos !== '') {
            $redirect->with('warning', $avisos);
        }

        return $redirect;
    }

    protected function avisosMikrotikUltimoCobro(): string
    {
        $avisos = $this->facturacionService->avisosUltimoCobro ?? [];

        return implode(' ', array_unique(array_filter($avisos)));
    }

    /**
     * Eliminar cobro.
     */
    public function destroy(Cobro $cobro)
    {
        $montoSaldoFavor = $this->facturacionService->eliminarCobro($cobro);

        $mensaje = 'Cobro eliminado correctamente.';
        if ($montoSaldoFavor > 0.009) {
            $mensaje .= ' Se descontaron '.number_format($montoSaldoFavor, 0, ',', '.').' PYG del saldo a favor del cliente.';
        }

        return redirect()->route('cobros.index')->with('success', $mensaje);
    }

    /**
     * Descargar PDF resumen de cobros (con los mismos filtros del listado).
     */
    public function pdfResumen(Request $request)
    {
        $user = $request->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';

        $query = Cobro::with(['cliente', 'facturaInternas', 'usuario'])
            ->orderBy('id', 'desc');
        $this->applyCobrosListFilters($request, $query, $user, $esAdmin);

        $total = (float) (clone $query)->sum('monto');
        $totalRegistrosFiltrados = (clone $query)->count();
        $cobros = $query->limit(500)->get();
        $ajustes = \App\Models\AjustesGenerales::obtener();

        $pdf = Pdf::loadView('cobros.pdf-resumen', [
            'cobros' => $cobros,
            'total' => $total,
            'totalRegistrosFiltrados' => $totalRegistrosFiltrados,
            'ajustes' => $ajustes,
            'desde' => $request->desde,
            'hasta' => $request->hasta,
        ])->setPaper('a4', 'portrait');

        $nombre = 'resumen-cobros-' . now()->format('Y-m-d-His') . '.pdf';
        return $pdf->download($nombre);
    }

    /**
     * Exportar cobros filtrados a Excel (CSV UTF-8 con separador ; compatible con Excel).
     */
    public function exportarExcel(Request $request): StreamedResponse
    {
        $user = $request->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';

        $query = Cobro::with(['cliente', 'facturaInternas', 'usuario'])
            ->orderBy('id', 'desc');
        $this->applyCobrosListFilters($request, $query, $user, $esAdmin);

        $cobros = $query->get();

        $filename = 'cobros-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($cobros) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($output, [
                'Recibo',
                'Fecha pago',
                'Cliente',
                'Facturas internas',
                'Forma de pago',
                'Monto',
                'Cobrado por',
            ], ';');

            foreach ($cobros as $c) {
                $facturas = $c->facturaInternas->isNotEmpty()
                    ? $c->facturaInternas->pluck('id')->map(fn ($id) => '#'.$id)->implode(', ')
                    : '';

                fputcsv($output, [
                    $c->numero_recibo,
                    optional($c->fecha_pago)->format('d/m/Y H:i') ?? '',
                    trim(($c->cliente->nombre ?? '').' '.($c->cliente->apellido ?? '')),
                    $facturas,
                    \App\Models\Cobro::formasPago()[$c->forma_pago] ?? $c->forma_pago,
                    number_format((float) $c->monto, 0, ',', ''),
                    $c->usuario->name ?? '',
                ], ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Filtros de listado que no son rangos de periodo (usuario, cliente, forma de pago, recibo).
     */
    protected function applyCobrosDimensionFilters(Request $request, Builder $query, $user, bool $esAdmin): void
    {
        if (!$esAdmin) {
            $query->where('usuario_id', $user->usuario_id);
        }
        if ($esAdmin && $request->filled('usuario_id')) {
            $query->where('usuario_id', $request->usuario_id);
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        if ($request->filled('numero_recibo')) {
            $query->where('numero_recibo', 'like', '%' . $request->numero_recibo . '%');
        }
        if ($request->filled('forma_pago')) {
            $formas = array_keys(Cobro::formasPago());
            if (in_array($request->forma_pago, $formas, true)) {
                $query->where('forma_pago', $request->forma_pago);
            }
        }
    }

    /**
     * Mismos filtros que el listado de cobros y el PDF resumen.
     */
    protected function applyCobrosListFilters(Request $request, Builder $query, $user, bool $esAdmin): void
    {
        $this->applyCobrosDimensionFilters($request, $query, $user, $esAdmin);
        if ($request->filled('desde')) {
            $query->whereDate('fecha_pago', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_pago', '<=', $request->hasta);
        }
        if ($request->filled('factura_desde') && $request->filled('factura_hasta')) {
            $fd = $request->factura_desde;
            $fh = $request->factura_hasta;
            $query->whereHas('facturaInternas', function ($q) use ($fd, $fh) {
                $q->whereDate('factura_internas.created_at', '>=', $fd)
                    ->whereDate('factura_internas.created_at', '<=', $fh);
            });
        }
    }

    /**
     * Vista de múltiples recibos tras multicobro.
     */
    public function multicobroResult(Request $request)
    {
        $ids = $request->session()->get('cobros_multicobro_ids', []);
        $ids = is_array($ids) ? array_filter(array_map('intval', $ids)) : [];

        if (empty($ids)) {
            return redirect()->route('cobros.index')->with('info', 'No hay recibos de multicobro para mostrar.');
        }

        $cobros = Cobro::with(['cliente.servicios', 'facturaInternas', 'usuario'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get();

        if ($cobros->isEmpty()) {
            $request->session()->forget('cobros_multicobro_ids');
            return redirect()->route('cobros.index')->with('info', 'No se encontraron los cobros.');
        }

        $ajustes = \App\Models\AjustesGenerales::obtener();

        return view('cobros.multicobro-result', compact('cobros', 'ajustes'));
    }

    /**
     * Recibo de pago (vista para imprimir).
     */
    public function show(Cobro $cobro)
    {
        $cobro->load(['cliente.servicios', 'facturaInternas', 'usuario']);
        $ajustes = \App\Models\AjustesGenerales::obtener();

        return view('cobros.show', compact('cobro', 'ajustes'));
    }

    /**
     * Descargar recibo en PDF (mismo contenido que la vista de impresión).
     */
    public function reciboPdf(Cobro $cobro)
    {
        $user = request()->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';
        if (! $esAdmin && (int) $cobro->usuario_id !== (int) $user->usuario_id) {
            abort(403, 'No podés descargar este recibo.');
        }

        $cobro->load(['cliente.servicios', 'facturaInternas', 'usuario']);
        $ajustes = \App\Models\AjustesGenerales::obtener();

        $modo = request()->query('recibo_modo', 'con_grafico');
        if (! in_array($modo, ['con_grafico', 'sin_grafico', 'sin_grafico_linea'], true)) {
            $modo = 'con_grafico';
        }
        $reciboLineaSimple = $modo === 'sin_grafico_linea';
        $reciboSinGrafico = in_array($modo, ['sin_grafico', 'sin_grafico_linea'], true);

        $logoBase64 = null;
        if (! $reciboSinGrafico && $ajustes && $ajustes->logo && Storage::disk('public')->exists($ajustes->logo)) {
            $mime = Storage::disk('public')->mimeType($ajustes->logo) ?? 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($ajustes->logo));
        }

        // Impresión en navegador usa localStorage (reciboPapelMm); el PDF usa ancho fijo en servidor.
        $anchoMm = 80;
        // Tamaño ticket (puntos: mm × 72 / 25,4); alto amplio para rollo térmico.
        $mmToPt = static fn (float $mm): float => $mm * 72 / 25.4;
        $anchoPt = $mmToPt((float) $anchoMm);
        $altoPt = $mmToPt(600);

        $pdf = Pdf::loadView('cobros.recibo-pdf', [
            'cobro' => $cobro,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
            'reciboSinGrafico' => $reciboSinGrafico,
            'reciboLineaSimple' => $reciboLineaSimple,
            'esMulticobro' => false,
        ])->setPaper([0, 0, $anchoPt, $altoPt], 'portrait');

        $slug = preg_replace('/[^a-zA-Z0-9\-_.]+/', '_', $cobro->numero_recibo);
        $nombre = 'recibo-'.$slug.'.pdf';

        return $pdf->download($nombre);
    }
}
