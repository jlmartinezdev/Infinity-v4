<?php

namespace App\Http\Controllers;

use App\Models\AjustesGenerales;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FacturaDetalle;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\Impuesto;
use App\Services\FacturacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacturaInternaController extends Controller
{
    public function index(Request $request)
    {
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);

        return view('factura-internas.index', compact('clientes'));
    }

    /**
     * Listado paginado para la SPA Vue (filtros + búsqueda).
     */
    public function list(Request $request)
    {
        $query = FacturaInterna::query()
            ->with(['cliente'])
            ->orderBy('fecha_emision', 'desc')
            ->orderBy('id', 'desc');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        $busqueda = trim($request->input('q', ''));
        if ($busqueda !== '') {
            $raw = $busqueda;
            $term = '%'.addcslashes($raw, '%_\\').'%';
            $query->where(function ($w) use ($raw, $term) {
                if (ctype_digit($raw)) {
                    $w->where('factura_internas.id', (int) $raw);
                }
                $w->orWhereHas('cliente', function ($cq) use ($term) {
                    $cq->where('nombre', 'like', $term)
                        ->orWhere('apellido', 'like', $term)
                        ->orWhere('cedula', 'like', $term);
                });
            });
        }

        $perPage = min(50, max(5, (int) $request->get('per_page', 15)));
        $paginator = $query->paginate($perPage);

        $paginator->through(function (FacturaInterna $f) {
            return [
                'id' => $f->id,
                'cliente_id' => $f->cliente_id,
                'cliente_nombre' => trim(($f->cliente->nombre ?? '').' '.($f->cliente->apellido ?? '')),
                'cliente_cedula' => $f->cliente->cedula ?? null,
                'periodo_desde' => $f->periodo_desde?->format('Y-m-d'),
                'periodo_hasta' => $f->periodo_hasta?->format('Y-m-d'),
                'fecha_emision' => $f->fecha_emision?->format('Y-m-d'),
                'estado' => $f->estado,
                'total' => (float) $f->total,
                'moneda' => $f->moneda ?? 'PYG',
            ];
        });

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Ejecuta el comando Artisan crear-factura-internas (facturas automáticas del mes, con --force).
     */
    public function ejecutarCrearFacturaInternas()
    {
        try {
            Artisan::call('crear-factura-internas');
            $output = trim(Artisan::output());
            $resumen = $output !== ''
                ? Str::limit(preg_replace('/\s+/', ' ', $output), 500, '…')
                : 'Sin salida del comando.';

            return redirect()
                ->route('factura-internas.index')
                ->with('success', 'Tarea crear-factura-internas ejecutada. '.$resumen);
        } catch (\Throwable $e) {
            return redirect()
                ->route('factura-internas.index')
                ->with('error', 'No se pudo ejecutar la tarea: '.$e->getMessage());
        }
    }

    /**
     * Lista de facturas internas con saldo pendiente de pago (para cobro).
     * Filtro por búsqueda: nombre o cédula del cliente.
     */
    public function pendientes(Request $request)
    {
        return view('factura-internas.pendientes', [
            'facturas' => $this->facturasPendientesPaginadas($request),
        ]);
    }

    /**
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    private function facturasPendientesPaginadas(Request $request)
    {
        $query = FacturaInterna::with(['cliente', 'promesaPago'])
            ->where('estado', 'pendiente')
            ->whereRaw('total > COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id');

        if ($request->filled('buscar')) {
            $term = '%'.trim($request->buscar).'%';
            $query->whereHas('cliente', function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                    ->orWhere('apellido', 'like', $term)
                    ->orWhere('cedula', 'like', $term);
            });
        }

        return $query->paginate(20)->withQueryString();
    }

    public function show(FacturaInterna $factura_interna)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto', 'usuario', 'cobros']);
        $ajustes = AjustesGenerales::obtener();

        return view('factura-internas.show', compact('factura_interna', 'ajustes'));
    }

    /**
     * Descarga la factura interna en PDF (misma información que la vista de detalle).
     */
    public function pdf(FacturaInterna $factura_interna)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto', 'usuario', 'cobros']);
        $ajustes = AjustesGenerales::obtener();

        $logoBase64 = null;
        if ($ajustes && $ajustes->logo && Storage::disk('public')->exists($ajustes->logo)) {
            $mime = Storage::disk('public')->mimeType($ajustes->logo) ?? 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($ajustes->logo));
        }

        $nombreArchivo = 'factura-interna-'.$factura_interna->id.'.pdf';

        return Pdf::loadView('factura-internas.pdf', [
            'factura_interna' => $factura_interna,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
        ])->setPaper('a4', 'portrait')->download($nombreArchivo);
    }

    public function edit(FacturaInterna $factura_interna)
    {
        $factura_interna->load(['cliente', 'detalles.impuesto']);

        $fechaPagoParaEdicion = $factura_interna->fecha_pago;
        $fechaPagoDesdeCobros = false;
        $cobrosFactura = collect();
        $formaPagoActual = null;

        if ($factura_interna->estado === 'pagada') {
            $ultimoCobro = $factura_interna->cobros()
                ->orderByDesc('fecha_pago')
                ->orderByDesc('id')
                ->first();
            if ($ultimoCobro && $ultimoCobro->fecha_pago) {
                $fechaPagoParaEdicion = $ultimoCobro->fecha_pago;
                $fechaPagoDesdeCobros = true;
            }

            $factura_interna->load('cobros');
            $cobrosFactura = $factura_interna->cobros->sortByDesc('id')->values();
            if ($cobrosFactura->isNotEmpty()) {
                $formas = $cobrosFactura->pluck('forma_pago')->filter()->unique();
                $formaPagoActual = $formas->count() === 1 ? $formas->first() : $cobrosFactura->first()->forma_pago;
            }
        }

        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);
        $impuestos = Impuesto::activos();
        $estados = FacturaInterna::estados();
        $formasPago = Cobro::formasPago();

        return view('factura-internas.edit', compact(
            'factura_interna',
            'clientes',
            'impuestos',
            'estados',
            'fechaPagoParaEdicion',
            'fechaPagoDesdeCobros',
            'cobrosFactura',
            'formaPagoActual',
            'formasPago'
        ));
    }

    public function update(Request $request, FacturaInterna $factura_interna, FacturacionService $facturacionService)
    {
        $estadoAnterior = $factura_interna->estado;

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'periodo_desde' => ['required', 'date'],
            'periodo_hasta' => ['required', 'date', 'after_or_equal:periodo_desde'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date'],
            'fecha_pago' => ['nullable', 'date'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'estado' => ['required', 'string', 'in:emitida,anulada,pendiente,pagada,cancelada'],
            'moneda' => ['required', 'string', 'max:10'],
            'observaciones' => ['nullable', 'string', 'max:500'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.id' => ['nullable', 'integer'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'forma_pago' => ['nullable', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
        ]);

        $factura_interna->load('detalles');
        $detallesRequest = $request->input('detalles', []);
        $idsEnRequest = collect($detallesRequest)->pluck('id')->filter()->values()->all();

        DB::transaction(function () use ($factura_interna, $validated, $detallesRequest, $idsEnRequest, $estadoAnterior, $facturacionService) {
            $factura_interna->update([
                'cliente_id' => $validated['cliente_id'],
                'periodo_desde' => $validated['periodo_desde'],
                'periodo_hasta' => $validated['periodo_hasta'],
                'fecha_emision' => $validated['fecha_emision'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                'fecha_pago' => $validated['fecha_pago'] ?? null,
                'estado' => $validated['estado'],
                'moneda' => $validated['moneda'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            $detalleIdsDeEstaFactura = $factura_interna->detalles->pluck('id')->all();
            $idsAEliminar = array_diff($detalleIdsDeEstaFactura, $idsEnRequest);
            if (! empty($idsAEliminar)) {
                FacturaInternaDetalle::where('factura_interna_id', $factura_interna->id)
                    ->whereIn('id', $idsAEliminar)
                    ->delete();
            }

            $subtotal = 0;
            $totalImpuestos = 0;
            $total = 0;

            foreach ($detallesRequest as $item) {
                $cantidad = (float) $item['cantidad'];
                $precioUnitario = (float) $item['precio_unitario'];
                $impuesto = ! empty($item['impuesto_id']) ? Impuesto::find($item['impuesto_id']) : null;
                $calc = FacturaDetalle::calcularDesdePrecio($cantidad, $precioUnitario, $impuesto);

                $datosDetalle = [
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precioUnitario,
                    'impuesto_id' => $item['impuesto_id'] ?? null,
                    'subtotal' => $calc['subtotal'],
                    'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                    'monto_impuesto' => $calc['monto_impuesto'],
                    'total' => $calc['total'],
                ];

                if (! empty($item['id']) && in_array((int) $item['id'], $detalleIdsDeEstaFactura, true)) {
                    $detalle = FacturaInternaDetalle::where('id', $item['id'])->where('factura_interna_id', $factura_interna->id)->first();
                    if ($detalle) {
                        $datosDetalle['servicio_id'] = $detalle->servicio_id;
                        $detalle->update($datosDetalle);
                    }
                } else {
                    $datosDetalle['factura_interna_id'] = $factura_interna->id;
                    $datosDetalle['servicio_id'] = null;
                    FacturaInternaDetalle::create($datosDetalle);
                }

                $subtotal += $calc['subtotal'];
                $totalImpuestos += $calc['monto_impuesto'];
                $total += $calc['total'];
            }

            $descuento = round((float) ($validated['descuento'] ?? 0), 2);
            $totalBruto = round($total, 2);
            $totalFinal = max(0, $totalBruto - $descuento);

            $factura_interna->update([
                'subtotal' => round($subtotal, 2),
                'total_impuestos' => round($totalImpuestos, 2),
                'descuento' => $descuento,
                'total' => $totalFinal,
            ]);

            if ($estadoAnterior === 'pagada' && $validated['estado'] !== 'pagada') {
                $this->removerCobrosAlDejarDeEstarPagada($factura_interna, $facturacionService);
            }

            $factura_interna->refresh();

            if ($validated['estado'] === 'pagada') {
                $factura_interna->load('cobros');
                if ($factura_interna->cobros->isNotEmpty() && ! empty($validated['forma_pago'])) {
                    foreach ($factura_interna->cobros as $cobro) {
                        $cobro->update(['forma_pago' => $validated['forma_pago']]);
                    }
                }

                if (! empty($validated['fecha_pago'])) {
                    $ultimoCobro = $factura_interna->cobros()
                        ->orderByDesc('fecha_pago')
                        ->orderByDesc('id')
                        ->first();
                    if ($ultimoCobro) {
                        $nuevaFecha = Carbon::parse($validated['fecha_pago'])->startOfDay();
                        if ($ultimoCobro->fecha_pago instanceof \DateTimeInterface) {
                            $nuevaFecha = Carbon::parse($validated['fecha_pago'])
                                ->setTimeFromTimeString(Carbon::parse($ultimoCobro->fecha_pago)->format('H:i:s'));
                        }
                        $ultimoCobro->update(['fecha_pago' => $nuevaFecha]);
                    }
                }
            }
        });

        return redirect()->route('factura-internas.index')
            ->with('success', 'Factura interna actualizada.');
    }

    public function destroy(Request $request, FacturaInterna $factura_interna, FacturacionService $facturacionService)
    {
        $clienteId = $factura_interna->cliente_id;
        $servicioIds = $factura_interna->detalles()->whereNotNull('servicio_id')->pluck('servicio_id')->unique()->values()->all();

        $factura_interna->cobros()->delete();
        $factura_interna->detalles()->delete();
        $factura_interna->delete();

        $facturacionService->revisarEstadoPagoServiciosTrasEliminarFacturaInterna($clienteId, $servicioIds);

        if ($request->ajax()) {
            return response()->json(['message' => 'Factura interna eliminada.']);
        }

        return redirect()->route('factura-internas.index')
            ->with('success', 'Factura interna eliminada.');
    }

    /**
     * Al pasar de pagada a otro estado (p. ej. pendiente): elimina o desvincula cobros.
     * Si el cobro solo cubría esta factura, se borra el registro de cobro; si cubría varias, solo se quita el vínculo y el monto correspondiente.
     */
    private function removerCobrosAlDejarDeEstarPagada(FacturaInterna $factura, FacturacionService $facturacionService): void
    {
        $factura->load('cobros');
        foreach ($factura->cobros as $cobro) {
            $otras = $cobro->facturaInternas()->where('factura_internas.id', '!=', $factura->id)->count();
            if ($otras === 0) {
                $facturacionService->eliminarCobro($cobro);

                continue;
            }

            $pivot = DB::table('cobro_factura_interna')
                ->where('cobro_id', $cobro->id)
                ->where('factura_interna_id', $factura->id)
                ->first();
            if (! $pivot) {
                continue;
            }
            $montoQuitar = (float) $pivot->monto;
            $cobro->facturaInternas()->detach($factura->id);
            $cobro->refresh();
            $nuevoMonto = max(0, (float) $cobro->monto - $montoQuitar);
            if ($nuevoMonto <= 0) {
                $facturacionService->eliminarCobro($cobro);
            } else {
                $cobro->update(['monto' => $nuevoMonto]);
            }
        }

        $facturacionService->actualizarEstadoPagoServiciosDeFacturaInterna($factura->id, 'pendiente');
    }
}
