<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use Illuminate\Http\Request;

/**
 * Endpoints del portal del cliente (su propia cuenta).
 */
class PortalController extends ApiController
{
    public function resumen(Request $request)
    {
        $cliente = $request->user()->cliente()->with(['servicios.plan'])->firstOrFail();

        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $totalPendiente = (float) (FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoExpr.') as total')
            ->value('total') ?? 0);

        $saldoFavor = (float) $cliente->servicios->sum(fn (Servicio $s) => (float) ($s->saldo_a_favor ?? 0));

        return $this->ok([
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
                'direccion' => $cliente->direccion,
                'estado' => $cliente->estado,
            ],
            'resumen' => [
                'total_pendiente' => $totalPendiente,
                'saldo_a_favor' => $saldoFavor,
                'servicios' => $cliente->servicios->count(),
            ],
            'servicios' => $cliente->servicios->map(fn (Servicio $s) => [
                'servicio_id' => $s->servicio_id,
                'estado' => $s->estado,
                'estado_label' => Servicio::estadosDisponibles()[$s->estado] ?? $s->estado,
                'plan' => $s->plan?->nombre,
                'velocidad' => $s->plan?->velocidad,
                'precio' => $s->plan?->precio,
                'ip' => $s->ip,
            ]),
        ]);
    }

    public function facturas(Request $request)
    {
        $clienteId = $request->user()->cliente_id;
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        $query = FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->boolean('solo_pendientes')) {
            $query->whereRaw($saldoExpr.' > 0.009');
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $page = $query->paginate($perPage);

        $page->getCollection()->transform(fn (FacturaInterna $f) => [
            'id' => $f->id,
            'estado' => $f->estado,
            'total' => (float) $f->total,
            'saldo_pendiente' => (float) $f->saldo_pendiente,
            'fecha_emision' => optional($f->fecha_emision)?->toDateString(),
            'fecha_vencimiento' => optional($f->fecha_vencimiento)?->toDateString(),
            'periodo_desde' => optional($f->periodo_desde)?->toDateString(),
            'periodo_hasta' => optional($f->periodo_hasta)?->toDateString(),
            'tipo_factura' => $f->tipo_factura,
        ]);

        return $this->ok($page);
    }

    public function cobros(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        $cobros = Cobro::query()
            ->where('cliente_id', $request->user()->cliente_id)
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->ok($cobros);
    }

    public function tickets(Request $request)
    {
        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        $tickets = Ticket::with(['ticketAsunto', 'asignado:usuario_id,name'])
            ->where('cliente_id', $request->user()->cliente_id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->ok($tickets);
    }

    public function crearTicket(Request $request)
    {
        $validated = $request->validate([
            'ticket_asunto_id' => ['required', 'integer', 'exists:ticket_asuntos,id'],
            'descripcion' => ['required', 'string', 'max:5000'],
            'prioridad' => ['nullable', 'string', 'in:baja,media,alta'],
        ]);

        $ticket = Ticket::create([
            'cliente_id' => $request->user()->cliente_id,
            'ticket_asunto_id' => $validated['ticket_asunto_id'],
            'descripcion' => trim($validated['descripcion']),
            'prioridad' => $validated['prioridad'] ?? 'media',
            'estado' => 'pendiente',
            'reportado_desde' => 'app',
            'usuario_id' => $request->user()->usuario_id,
        ]);

        $ticket->load(['ticketAsunto']);

        return $this->ok($ticket, 'Ticket creado', 201);
    }

    public function asuntosTicket()
    {
        return $this->ok(TicketAsunto::orderBy('nombre')->get(['id', 'nombre']));
    }
}
