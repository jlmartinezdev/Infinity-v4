<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Models\User;
use App\Services\FacturacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $busqueda = trim((string) $request->get('q', ''));
        $query = Ticket::with(['cliente.servicios.pool.router', 'pedido', 'ticketAsunto', 'usuario', 'asignado'])
            ->orderBy('created_at', 'desc');

        $cola = trim((string) $request->get('cola', ''));
        if ($cola === 'abiertos') {
            $query->whereIn('estado', ['pendiente', 'en_camino', 'en_proceso']);
        } elseif ($cola === 'pendientes' || $cola === 'pendiente') {
            $query->where('estado', 'pendiente');
        } elseif ($cola === 'en_camino') {
            $query->where('estado', 'en_camino');
        } elseif ($cola === 'en_proceso') {
            $query->where('estado', 'en_proceso');
        } elseif ($cola === 'resueltos') {
            $query->whereIn('estado', ['resuelto', 'cerrado']);
        } elseif ($cola === 'alta') {
            $query->where('prioridad', 'alta')->whereIn('estado', ['pendiente', 'en_camino', 'en_proceso']);
        } elseif ($cola === 'sin_asignar') {
            $query->whereNull('asignado_id')->whereIn('estado', ['pendiente', 'en_camino', 'en_proceso']);
        } elseif ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } elseif ($request->boolean('ocultar_resuelto_cerrado')) {
            $query->whereNotIn('estado', ['resuelto', 'cerrado']);
        }

        if ($request->filled('prioridad') && $cola !== 'alta') {
            $query->where('prioridad', $request->prioridad);
        }
        if ($request->boolean('sin_asignar') && $cola !== 'sin_asignar') {
            $query->whereNull('asignado_id')->whereIn('estado', ['pendiente', 'en_camino', 'en_proceso']);
        }
        if ($request->filled('ticket_asunto_id')) {
            $query->where('ticket_asunto_id', $request->ticket_asunto_id);
        }
        if ($request->filled('asignado_id')) {
            $query->where('asignado_id', (int) $request->asignado_id);
        }

        if ($busqueda !== '') {
            $term = '%'.addcslashes($busqueda, '%_\\').'%';
            $query->where(function ($w) use ($busqueda, $term) {
                if (ctype_digit($busqueda)) {
                    $w->orWhere('id', (int) $busqueda)
                        ->orWhere('pedido_id', (int) $busqueda);
                }
                $w->orWhere('descripcion', 'like', $term)
                    ->orWhere('observaciones', 'like', $term)
                    ->orWhereHas('ticketAsunto', fn ($a) => $a->where('nombre', 'like', $term))
                    ->orWhereHas('asignado', fn ($u) => $u->where('name', 'like', $term))
                    ->orWhereHas('cliente', function ($c) use ($term) {
                        $c->where('nombre', 'like', $term)
                            ->orWhere('apellido', 'like', $term)
                            ->orWhere('cedula', 'like', $term)
                            ->orWhere('telefono', 'like', $term)
                            ->orWhereRaw("CONCAT(COALESCE(nombre,''), ' ', COALESCE(apellido,'')) LIKE ?", [$term]);
                    })
                    ->orWhereHas('cliente.servicios', fn ($s) => $s->where('ip', 'like', $term));
            });
        } elseif ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }

        $perPagePermitidos = [10, 15, 25, 50, 100];
        $perPage = (int) $request->get('per_page', 15);
        if (! in_array($perPage, $perPagePermitidos, true)) {
            $perPage = 15;
        }

        $tickets = $query->paginate($perPage)->withQueryString();
        $asuntos = TicketAsunto::orderBy('nombre')->get();
        $clienteFiltro = null;
        if ($busqueda === '' && $request->filled('cliente_id')) {
            $clienteFiltro = Cliente::query()->find((int) $request->cliente_id);
        }

        // Métricas de KPIs y resumen para tabs en una sola consulta optimizada
        $kpis = Ticket::query()
            ->selectRaw("
                COUNT(*) as total,
                COUNT(CASE WHEN estado IN ('pendiente', 'en_camino', 'en_proceso') THEN 1 END) as abiertos,
                COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as pendientes,
                COUNT(CASE WHEN estado = 'en_camino' THEN 1 END) as en_camino,
                COUNT(CASE WHEN estado = 'en_proceso' THEN 1 END) as en_proceso,
                COUNT(CASE WHEN estado IN ('resuelto', 'cerrado') THEN 1 END) as resueltos,
                COUNT(CASE WHEN prioridad = 'alta' AND estado IN ('pendiente', 'en_camino', 'en_proceso') THEN 1 END) as alta,
                COUNT(CASE WHEN estado IN ('pendiente', 'en_camino', 'en_proceso') AND asignado_id IS NULL THEN 1 END) as sin_asignar,
                COUNT(CASE WHEN estado IN ('resuelto', 'cerrado') AND DATE(COALESCE(fecha_cierre, updated_at)) = ? THEN 1 END) as resueltos_hoy
            ", [now()->toDateString()])
            ->first();

        $ticketsPendientesCount = (int) ($kpis->pendientes ?? 0);
        $tecnicos = User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name']);

        return view('tickets.index', compact('tickets', 'asuntos', 'busqueda', 'clienteFiltro', 'ticketsPendientesCount', 'tecnicos', 'kpis', 'cola'));
    }

    public function create(Request $request)
    {
        $asuntos = TicketAsunto::orderBy('nombre')->get();
        $clientes = Cliente::orderBy('nombre')->get();
        $tecnicos = User::staff()->activos()->orderBy('name')->get();
        $pedidos = Pedido::with('cliente')->orderBy('fecha_pedido', 'desc')->limit(200)->get();
        $clientePresetId = $request->filled('cliente_id') ? (int) $request->cliente_id : null;

        return view('tickets.create', compact('asuntos', 'clientes', 'tecnicos', 'pedidos', 'clientePresetId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,pedido_id'],
            'ticket_asunto_id' => ['required', 'integer', 'exists:ticket_asuntos,id'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['nullable', 'string', 'in:pendiente,en_proceso,resuelto,cerrado,cancelado'],
            'prioridad' => ['nullable', 'string', 'in:baja,media,alta'],
            'reportado_desde' => ['nullable', 'string', 'max:50'],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'observaciones' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['usuario_id'] = $request->user()->usuario_id;
        $validated['estado'] = $validated['estado'] ?? 'pendiente';
        $validated['prioridad'] = $validated['prioridad'] ?? 'media';
        $validated['descripcion'] = isset($validated['descripcion']) && $validated['descripcion'] !== null
            ? trim((string) $validated['descripcion'])
            : null;
        $validated['observaciones'] = isset($validated['observaciones']) && $validated['observaciones'] !== null
            ? trim((string) $validated['observaciones'])
            : null;

        // Evita duplicados por doble envío (red lenta, reintento del navegador, doble click).
        $ticketDuplicado = Ticket::query()
            ->where('usuario_id', $validated['usuario_id'])
            ->where('ticket_asunto_id', $validated['ticket_asunto_id'])
            ->where('cliente_id', $validated['cliente_id'] ?? null)
            ->where('pedido_id', $validated['pedido_id'] ?? null)
            ->where('estado', $validated['estado'])
            ->where('prioridad', $validated['prioridad'])
            ->where('descripcion', $validated['descripcion'])
            ->where('created_at', '>=', now()->subMinutes(3))
            ->latest('id')
            ->first();

        if ($ticketDuplicado) {
            return back()
                ->withInput()
                ->withErrors([
                    'ticket_asunto_id' => 'Ya existe un ticket muy reciente con los mismos datos (ID #'.$ticketDuplicado->id.'). Verifique antes de crear otro.',
                ]);
        }

        if ($request->hasFile('imagen')) {
            $validated['imagen'] = $request->file('imagen')->store('tickets', 'public');
        }

        Ticket::create($validated);

        return redirect()->route('tickets.index')->with('success', 'Ticket creado correctamente.');
    }

    public function edit(Ticket $ticket)
    {
        $ticket->load(['cliente', 'pedido', 'ticketAsunto', 'usuario', 'asignado']);
        $asuntos = TicketAsunto::orderBy('nombre')->get();
        $clientes = Cliente::orderBy('nombre')->get();
        $tecnicos = User::staff()->activos()->orderBy('name')->get();
        $pedidos = Pedido::with('cliente')->orderBy('fecha_pedido', 'desc')->limit(200)->get();

        return view('tickets.edit', compact('ticket', 'asuntos', 'clientes', 'tecnicos', 'pedidos'));
    }

    public function update(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'pedido_id' => ['nullable', 'integer', 'exists:pedidos,pedido_id'],
            'ticket_asunto_id' => ['required', 'integer', 'exists:ticket_asuntos,id'],
            'descripcion' => ['nullable', 'string'],
            'estado' => ['required', 'string', 'in:pendiente,en_proceso,resuelto,cerrado,cancelado'],
            'prioridad' => ['required', 'string', 'in:baja,media,alta'],
            'reportado_desde' => ['nullable', 'string', 'max:50'],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'observaciones' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ]);

        if (in_array($validated['estado'], ['resuelto', 'cerrado', 'cancelado'], true) && ! $ticket->fecha_cierre) {
            $validated['fecha_cierre'] = now();
        }

        if ($request->hasFile('imagen')) {
            if ($ticket->imagen) {
                Storage::disk('public')->delete($ticket->imagen);
            }
            $validated['imagen'] = $request->file('imagen')->store('tickets', 'public');
        }

        $ticket->update($validated);

        return redirect()->route('tickets.index')->with('success', 'Ticket actualizado correctamente.');
    }

    public function destroy(Ticket $ticket)
    {
        if ($ticket->imagen) {
            Storage::disk('public')->delete($ticket->imagen);
        }
        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket eliminado correctamente.');
    }

    /**
     * Redirige al formulario de nueva cita en agenda con parámetros desde el ticket:
     * - tipo=pedido si el ticket tiene pedido, sino tipo=general con título sugerido
     * - pedido_id y fecha preseleccionados cuando aplica
     */
    public function crearAgenda(Ticket $ticket)
    {
        $params = [
            'from_ticket' => $ticket->id,
            'fecha' => now()->format('Y-m-d'),
        ];
        if ($ticket->cliente_id) {
            $params['cliente_id'] = $ticket->cliente_id;
        }
        if ($ticket->pedido_id) {
            $params['tipo'] = 'pedido';
            $params['pedido_id'] = $ticket->pedido_id;
        } else {
            $params['tipo'] = 'general';
            $asunto = $ticket->ticketAsunto?->nombre ?? 'Ticket #'.$ticket->id;
            $params['titulo'] = \Str::limit($asunto, 120);
        }

        return redirect()->route('agenda.create', $params);
    }

    /**
     * Actualizar solo el estado del ticket (desde modal en índice).
     */
    public function updateEstado(Request $request, Ticket $ticket)
    {
        $estadosValidos = implode(',', array_keys(Ticket::estados()));
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:'.$estadosValidos],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
        ]);
        $data = ['estado' => $validated['estado']];
        if ($request->exists('asignado_id')) {
            $data['asignado_id'] = $validated['asignado_id'] ?? null;
        }
        if (in_array($validated['estado'], ['resuelto', 'cerrado', 'cancelado'], true) && ! $ticket->fecha_cierre) {
            $data['fecha_cierre'] = now();
        }
        $ticket->update($data);

        return response()->json(['success' => true, 'estado' => $ticket->estado, 'asignado_id' => $ticket->asignado_id]);
    }

    /**
     * Genera factura interna pendiente por cobro del ticket (monto libre).
     */
    public function facturar(Request $request, Ticket $ticket, FacturacionService $facturacion)
    {
        if (! $ticket->cliente_id) {
            return response()->json(['message' => 'El ticket debe tener un cliente asociado para facturar.'], 422);
        }
        if ($ticket->factura_interna_id) {
            return response()->json(['message' => 'Este ticket ya tiene una factura interna registrada.'], 422);
        }

        $validated = $request->validate([
            'monto' => ['required', 'numeric', 'min:1'],
        ]);
        $monto = round((float) $validated['monto'], 2);

        $ticket->load('cliente');
        if (! $ticket->cliente) {
            return response()->json(['message' => 'Cliente no encontrado.'], 422);
        }

        try {
            $factura = $facturacion->generarFacturaInternaPorCobroTicket(
                $ticket->cliente,
                $ticket,
                $monto,
                $request->user()->usuario_id
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo generar la factura interna.'], 500);
        }

        return response()->json([
            'success' => true,
            'factura_interna_id' => $factura->id,
            'message' => 'Factura interna generada correctamente.',
        ]);
    }

    /**
     * Actualización masiva de tickets (asignar técnico, cambiar estado o marcar resuelto).
     */
    public function bulkUpdate(Request $request)
    {
        $estadosValidos = implode(',', array_keys(Ticket::estados()));
        $validated = $request->validate([
            'ticket_ids' => ['required', 'array', 'min:1'],
            'ticket_ids.*' => ['integer', 'exists:tickets,id'],
            'accion' => ['required', 'string', 'in:asignar_tecnico,cambiar_estado,marcar_resuelto'],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'estado' => ['nullable', 'string', 'in:'.$estadosValidos],
        ]);

        $ids = $validated['ticket_ids'];
        $count = count($ids);

        DB::transaction(function () use ($validated, $ids) {
            if ($validated['accion'] === 'asignar_tecnico') {
                Ticket::whereIn('id', $ids)->update([
                    'asignado_id' => $validated['asignado_id'] ?? null,
                ]);
            } elseif ($validated['accion'] === 'cambiar_estado') {
                $estado = $validated['estado'];
                if ($estado) {
                    $updateData = ['estado' => $estado];
                    if (in_array($estado, ['resuelto', 'cerrado', 'cancelado'], true)) {
                        Ticket::whereIn('id', $ids)
                            ->whereNull('fecha_cierre')
                            ->update(['fecha_cierre' => now()]);
                    }
                    Ticket::whereIn('id', $ids)->update($updateData);
                }
            } elseif ($validated['accion'] === 'marcar_resuelto') {
                Ticket::whereIn('id', $ids)
                    ->whereNull('fecha_cierre')
                    ->update(['fecha_cierre' => now()]);
                Ticket::whereIn('id', $ids)->update(['estado' => 'resuelto']);
            }
        });

        return response()->json([
            'success' => true,
            'count' => $count,
            'message' => "Se actualizaron {$count} tickets correctamente.",
        ]);
    }
}

