<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Support\PortalReciboPresenter;
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

        $proximaVencimiento = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->whereNotNull('fecha_vencimiento')
            ->orderBy('fecha_vencimiento')
            ->value('fecha_vencimiento');

        $clienteDesde = $cliente->servicios
            ->map(fn (Servicio $s) => $s->fecha_instalacion)
            ->filter()
            ->sort()
            ->first();
        if (! $clienteDesde && $cliente->fecha_otorgamiento) {
            $clienteDesde = $cliente->fecha_otorgamiento;
        }

        $cfgDisp = app(\App\Services\Portal\PortalAppConfigService::class)->resumen()['disponibilidad_pct'] ?? null;
        if ($cfgDisp !== null && $cfgDisp !== '') {
            $disponibilidad = (float) $cfgDisp;
        } else {
            $totalSvc = max(1, $cliente->servicios->count());
            $activos = $cliente->servicios->where('estado', Servicio::ESTADO_ACTIVO)->count();
            $disponibilidad = $cliente->servicios->isEmpty()
                ? null
                : round(90 + (10 * ($activos / $totalSvc)), 1);
        }

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
                'saldo_favor' => $saldoFavor,
                'servicios' => $cliente->servicios->count(),
                'proxima_vencimiento' => $proximaVencimiento
                    ? \Carbon\Carbon::parse($proximaVencimiento)->toDateString()
                    : null,
                'disponibilidad_pct' => $disponibilidad,
                'cliente_desde' => $clienteDesde
                    ? \Carbon\Carbon::parse($clienteDesde)->toDateString()
                    : null,
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
        $presenter = new PortalReciboPresenter;

        $page = Cobro::query()
            ->where('cliente_id', $request->user()->cliente_id)
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->paginate($perPage);

        $page->getCollection()->transform(fn (Cobro $c) => $presenter->listadoItem($c));

        return $this->ok($page);
    }

    /**
     * Recibo listo para pintar en la app (mismo estilo que Infinity).
     */
    public function cobro(Request $request, int $cobro)
    {
        $model = Cobro::query()
            ->where('id', $cobro)
            ->where('cliente_id', $request->user()->cliente_id)
            ->first();

        if (! $model) {
            return $this->fail('Recibo no encontrado.', 404);
        }

        return $this->ok((new PortalReciboPresenter)->detalle($model));
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
            'prioridad' => ['nullable', 'string', 'in:baja,media,alta,Baja,Media,Alta'],
            'datos_diagnostico' => ['nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                if (is_array($value)) {
                    return;
                }

                if (is_string($value)) {
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('El campo datos_diagnostico debe ser un JSON válido.');
                    }

                    return;
                }

                $fail('El campo datos_diagnostico debe ser un JSON válido.');
            }],
        ]);

        $prioridad = strtolower((string) ($validated['prioridad'] ?? 'media'));
        $datosDiagnostico = null;
        if (array_key_exists('datos_diagnostico', $validated) && $validated['datos_diagnostico'] !== null && $validated['datos_diagnostico'] !== '') {
            $raw = $validated['datos_diagnostico'];
            $datosDiagnostico = is_string($raw) ? json_decode($raw, true) : $raw;
        }

        $ticket = Ticket::create([
            'cliente_id' => $request->user()->cliente_id,
            'ticket_asunto_id' => $validated['ticket_asunto_id'],
            'descripcion' => trim($validated['descripcion']),
            'datos_diagnostico' => $datosDiagnostico,
            'prioridad' => $prioridad,
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
