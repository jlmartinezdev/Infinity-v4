<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $query = Ticket::with(['cliente.servicios', 'pedido', 'ticketAsunto', 'usuario', 'asignado'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } elseif ($request->boolean('ocultar_resuelto_cerrado')) {
            $query->whereNotIn('estado', ['resuelto', 'cerrado']);
        }
        if ($request->filled('ticket_asunto_id')) {
            $query->where('ticket_asunto_id', $request->ticket_asunto_id);
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        $tickets = $query->paginate(15)->withQueryString();
        $asuntos = TicketAsunto::orderBy('nombre')->get();
        $clientes = Cliente::orderBy('nombre')->get();

        return view('tickets.index', compact('tickets', 'asuntos', 'clientes'));
    }

    public function create(Request $request)
    {
        $asuntos = TicketAsunto::orderBy('nombre')->get();
        $clientes = Cliente::orderBy('nombre')->get();
        $tecnicos = User::where('estado', 'activo')->orderBy('name')->get();
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
            'asignado_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
            'observaciones' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ]);

        $validated['usuario_id'] = $request->user()->usuario_id;
        $validated['estado'] = $validated['estado'] ?? 'pendiente';
        $validated['prioridad'] = $validated['prioridad'] ?? 'media';

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
        $tecnicos = User::where('estado', 'activo')->orderBy('name')->get();
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
            'asignado_id' => ['nullable', 'integer', 'exists:users,usuario_id'],
            'observaciones' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'max:2048'],
        ]);

        if (in_array($validated['estado'], ['resuelto', 'cerrado', 'cancelado'], true) && !$ticket->fecha_cierre) {
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
            'from_ticket' => $ticket->ticket_id,
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
            $asunto = $ticket->ticketAsunto?->nombre ?? 'Ticket #' . $ticket->ticket_id;
            $params['titulo'] = \Str::limit($asunto, 120);
        }
        return redirect()->route('agenda.create', $params);
    }

    /**
     * Actualizar solo el estado del ticket (desde modal en índice).
     */
    public function updateEstado(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:pendiente,en_proceso,resuelto,cerrado,cancelado'],
        ]);
        $ticket->update(['estado' => $validated['estado']]);
        return response()->json(['success' => true, 'estado' => $ticket->estado]);
    }
}
