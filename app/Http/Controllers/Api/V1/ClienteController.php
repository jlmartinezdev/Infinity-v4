<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Services\WhatsApp\ClientePorTelefonoService;
use Illuminate\Http\Request;

class ClienteController extends ApiController
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $query = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido', 'solo_pedido'])
            ->orderBy('nombre');

        if (strlen($q) >= 2) {
            $query->where(function ($builder) use ($q) {
                $builder->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
                if (ctype_digit($q) && strlen($q) <= 10) {
                    $builder->orWhere('cliente_id', (int) $q);
                }
            });
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));
        $clientes = $query->paginate($perPage);

        return $this->ok($clientes);
    }

    public function porTelefono(Request $request, ClientePorTelefonoService $lookup)
    {
        $telefono = trim((string) $request->query('telefono', ''));
        if ($telefono === '') {
            return $this->fail('Indicá telefono.', 422);
        }

        return $this->ok($lookup->buscar($telefono));
    }

    public function buscar(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return $this->ok([]);
        }

        $clientes = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido', 'solo_pedido'])
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
                if (ctype_digit($q) && strlen($q) <= 10) {
                    $query->orWhere('cliente_id', (int) $q);
                }
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula', 'telefono', 'estado']);

        return $this->ok($clientes);
    }

    public function show(Cliente $cliente)
    {
        $cliente->load(['servicios.plan', 'servicios.pool']);

        $saldoPendienteExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $totalPendientePago = (float) (FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoPendienteExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoPendienteExpr.') as total')
            ->value('total') ?? 0);

        $totalSaldoFavor = (float) $cliente->servicios->sum(
            fn (Servicio $s) => (float) ($s->saldo_a_favor ?? 0)
        );

        $facturas = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (FacturaInterna $f) => $this->mapFactura($f));

        $cobros = Cobro::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->with(['usuario:usuario_id,name'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $tickets = Ticket::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->with(['ticketAsunto', 'asignado:usuario_id,name'])
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return $this->ok([
            'cliente' => $cliente,
            'resumen' => [
                'total_pendiente' => $totalPendientePago,
                'saldo_a_favor' => $totalSaldoFavor,
                'servicios' => $cliente->servicios->count(),
            ],
            'servicios' => $cliente->servicios->map(fn (Servicio $s) => $this->mapServicio($s)),
            'facturas' => $facturas,
            'cobros' => $cobros,
            'tickets' => $tickets,
        ]);
    }

    private function mapServicio(Servicio $s): array
    {
        return [
            'servicio_id' => $s->servicio_id,
            'estado' => $s->estado,
            'estado_label' => Servicio::estadosDisponibles()[$s->estado] ?? $s->estado,
            'ip' => $s->ip,
            'usuario_pppoe' => $s->usuario_pppoe,
            'fecha_instalacion' => optional($s->fecha_instalacion)?->toDateString(),
            'saldo_a_favor' => (float) ($s->saldo_a_favor ?? 0),
            'plan' => $s->plan ? [
                'plan_id' => $s->plan->plan_id,
                'nombre' => $s->plan->nombre,
                'precio' => $s->plan->precio,
                'velocidad' => $s->plan->velocidad ?? null,
            ] : null,
        ];
    }

    private function mapFactura(FacturaInterna $f): array
    {
        return [
            'id' => $f->id,
            'estado' => $f->estado,
            'total' => (float) $f->total,
            'saldo_pendiente' => (float) $f->saldo_pendiente,
            'fecha_emision' => optional($f->fecha_emision)?->toDateString(),
            'fecha_vencimiento' => optional($f->fecha_vencimiento)?->toDateString(),
            'periodo_desde' => optional($f->periodo_desde)?->toDateString(),
            'periodo_hasta' => optional($f->periodo_hasta)?->toDateString(),
            'tipo_factura' => $f->tipo_factura,
        ];
    }
}
