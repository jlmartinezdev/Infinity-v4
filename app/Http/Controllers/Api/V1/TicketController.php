<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Ticket;
use App\Models\TicketAsunto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketController extends ApiController
{
    public function index(Request $request)
    {
        $query = Ticket::with([
            'cliente:cliente_id,nombre,apellido,cedula',
            'ticketAsunto',
            'asignado:usuario_id,name',
        ])->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } elseif ($request->boolean('ocultar_cerrados')) {
            $query->whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado']);
        }
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('asignado_id')) {
            $query->where('asignado_id', (int) $request->asignado_id);
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        return $this->ok($query->paginate($perPage));
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
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'observaciones' => ['nullable', 'string'],
        ]);

        $validated['usuario_id'] = $request->user()->usuario_id;
        $validated['estado'] = $validated['estado'] ?? 'pendiente';
        $validated['prioridad'] = $validated['prioridad'] ?? 'media';
        $validated['reportado_desde'] = 'app';

        $ticket = Ticket::create($validated);
        $ticket->load(['cliente', 'ticketAsunto', 'asignado:usuario_id,name']);

        return $this->ok($ticket, 'Ticket creado', 201);
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['cliente', 'ticketAsunto', 'usuario:usuario_id,name', 'asignado:usuario_id,name']);

        return $this->ok($ticket);
    }

    public function updateEstado(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:pendiente,en_proceso,resuelto,cerrado,cancelado'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $ticket->estado = $validated['estado'];
        if (array_key_exists('observaciones', $validated)) {
            $ticket->observaciones = $validated['observaciones'];
        }
        if (in_array($validated['estado'], ['resuelto', 'cerrado'], true)) {
            $ticket->fecha_cierre = now();
        }
        $ticket->save();
        $ticket->load(['cliente', 'ticketAsunto', 'asignado:usuario_id,name']);

        return $this->ok($ticket, 'Estado actualizado');
    }

    public function asuntos()
    {
        return $this->ok(TicketAsunto::orderBy('nombre')->get(['id', 'nombre']));
    }
}
