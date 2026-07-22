<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Services\FacturacionService;
use Illuminate\Http\Request;

class CobroController extends ApiController
{
    public function __construct(
        protected FacturacionService $facturacionService
    ) {}

    public function index(Request $request)
    {
        $query = Cobro::with(['cliente:cliente_id,nombre,apellido,cedula', 'usuario:usuario_id,name'])
            ->orderByDesc('id');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_pago', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_pago', '<=', $request->fecha_hasta);
        }

        $user = $request->user();
        if ($user && ! $user->esAdministrador()) {
            $query->where('usuario_id', $user->usuario_id);
        } elseif ($request->filled('usuario_id')) {
            $query->where('usuario_id', (int) $request->usuario_id);
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        return $this->ok($query->paginate($perPage));
    }

    public function show(Cobro $cobro)
    {
        $cobro->load(['cliente', 'facturaInternas', 'usuario:usuario_id,name']);

        return $this->ok($cobro);
    }

    public function store(Request $request)
    {
        $ids = $request->input('factura_interna_ids', []);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];

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

        $usuarioId = $request->user()?->usuario_id;
        $monto = (float) $validated['monto'];

        if (count($ids) > 1) {
            $facturas = FacturaInterna::whereIn('id', $ids)
                ->where('cliente_id', $validated['cliente_id'])
                ->whereIn('estado', ['pendiente', 'emitida'])
                ->get()
                ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
                ->values();

            if ($facturas->isEmpty()) {
                return $this->fail('Ninguna factura seleccionada tiene saldo pendiente.', 422);
            }

            $totalSaldo = $facturas->sum(fn (FacturaInterna $f) => $f->saldo_pendiente);
            $montos = [];
            $acum = 0;
            $n = $facturas->count();
            foreach ($facturas as $i => $f) {
                $saldo = (float) $f->saldo_pendiente;
                if ($i === $n - 1) {
                    $montos[] = round($monto - $acum, 2);
                } else {
                    $m = round($monto * ($saldo / $totalSaldo), 2);
                    $montos[] = $m;
                    $acum += $m;
                }
            }

            $items = [];
            foreach ($facturas as $i => $factura) {
                if ($montos[$i] > 0) {
                    $items[] = ['id' => $factura->id, 'monto' => $montos[$i]];
                }
            }

            $cobro = $this->facturacionService->registrarCobro([
                'cliente_id' => $validated['cliente_id'],
                'monto' => $monto,
                'fecha_pago' => $validated['fecha_pago'],
                'forma_pago' => $validated['forma_pago'],
                'referencia' => $validated['referencia'] ?? null,
                'concepto' => $validated['concepto'] ?? null,
                'observaciones' => $validated['observaciones'] ?? null,
                'factura_interna_items' => $items,
            ], $usuarioId);

            $cobro->load(['cliente', 'facturaInternas']);

            return $this->ok($cobro, 'Cobro registrado. Recibo: '.$cobro->numero_recibo, 201);
        }

        if (! empty($ids)) {
            $validated['factura_interna_id'] = $ids[0];
        }

        $saldoAntes = null;
        $facturaOrigenId = null;
        if (! empty($validated['factura_interna_id'])) {
            $factura = FacturaInterna::find($validated['factura_interna_id']);
            if ($factura) {
                $saldoAntes = $factura->saldo_pendiente;
                $facturaOrigenId = $factura->id;
            }
        }

        $cobro = $this->facturacionService->registrarCobro($validated, $usuarioId);

        if ($saldoAntes !== null && $facturaOrigenId !== null) {
            if ($monto > $saldoAntes) {
                $this->facturacionService->sumarSaldoAFavorCliente(
                    (int) $validated['cliente_id'],
                    $monto - $saldoAntes,
                    $facturaOrigenId
                );
            }
        } else {
            $this->facturacionService->sumarSaldoAFavorCliente(
                (int) $validated['cliente_id'],
                $monto
            );
        }

        $cobro->load(['cliente', 'facturaInternas']);

        return $this->ok($cobro, 'Cobro registrado. Recibo: '.$cobro->numero_recibo, 201);
    }

    public function facturasPendientes(Request $request)
    {
        $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
        ]);

        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $facturas = FacturaInterna::query()
            ->where('cliente_id', (int) $request->cliente_id)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get()
            ->map(fn (FacturaInterna $f) => [
                'id' => $f->id,
                'total' => (float) $f->total,
                'saldo_pendiente' => (float) $f->saldo_pendiente,
                'fecha_emision' => optional($f->fecha_emision)?->toDateString(),
                'fecha_vencimiento' => optional($f->fecha_vencimiento)?->toDateString(),
                'periodo_desde' => optional($f->periodo_desde)?->toDateString(),
                'periodo_hasta' => optional($f->periodo_hasta)?->toDateString(),
                'estado' => $f->estado,
            ]);

        return $this->ok($facturas);
    }
}
