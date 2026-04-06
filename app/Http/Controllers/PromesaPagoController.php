<?php

namespace App\Http\Controllers;

use App\Models\FacturaInterna;
use App\Models\MikrotikOperacionPendiente;
use App\Models\PromesaPago;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PromesaPagoController extends Controller
{
    public function __construct(
        protected FacturacionService $facturacionService,
        protected MikroTikService $mikrotik
    ) {}

    /**
     * Listado de promesas de pago registradas (filtro por cliente y vigencia).
     */
    public function index(Request $request): View
    {
        $query = PromesaPago::with(['facturaInterna.cliente', 'usuario'])
            ->orderBy('vencimiento_at')
            ->orderBy('id');

        if ($request->filled('estado')) {
            if ($request->estado === 'vigente') {
                $query->where('vencimiento_at', '>', now());
            } elseif ($request->estado === 'vencida') {
                $query->where('vencimiento_at', '<=', now());
            }
        }

        if ($request->filled('buscar')) {
            $term = '%'.trim($request->buscar).'%';
            $query->whereHas('cliente', function ($q) use ($term) {
                $q->where('nombre', 'like', $term)
                    ->orWhere('apellido', 'like', $term)
                    ->orWhere('cedula', 'like', $term);
            });
        }

        $promesas = $query->paginate(25)->withQueryString();

        return view('promesas-pago.index', compact('promesas'));
    }

    public function create(FacturaInterna $factura_interna): RedirectResponse|View
    {
        $factura_interna->load('cliente', 'promesaPago');

        if ($factura_interna->saldo_pendiente <= 0) {
            return redirect()
                ->route('factura-internas.pendientes')
                ->with('error', 'Esta factura no tiene saldo pendiente; no aplica promesa de pago.');
        }

        if (! in_array($factura_interna->estado, ['pendiente', 'emitida'], true)) {
            return redirect()
                ->route('factura-internas.pendientes')
                ->with('error', 'Solo se pueden registrar promesas sobre facturas pendientes o emitidas con saldo.');
        }

        $defaultVencimiento = now()->addDay()->setTime(18, 0)->format('Y-m-d\TH:i');

        return view('promesas-pago.create', [
            'factura' => $factura_interna,
            'defaultVencimiento' => $defaultVencimiento,
        ]);
    }

    public function store(Request $request, FacturaInterna $factura_interna): RedirectResponse
    {
        $factura_interna->load('cliente');

        if ($factura_interna->saldo_pendiente <= 0) {
            return redirect()
                ->route('factura-internas.pendientes')
                ->with('error', 'Esta factura no tiene saldo pendiente.');
        }

        $validated = $request->validate([
            'vencimiento_at' => ['required', 'date', 'after:now'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $vencimiento = Carbon::parse($validated['vencimiento_at'], config('app.timezone'));

        $serviciosReactivados = DB::transaction(function () use ($factura_interna, $validated, $vencimiento, $request) {
            PromesaPago::updateOrCreate(
                ['factura_interna_id' => $factura_interna->id],
                [
                    'cliente_id' => $factura_interna->cliente_id,
                    'vencimiento_at' => $vencimiento,
                    'observaciones' => $validated['observaciones'] ?? null,
                    'usuario_id' => $request->user()?->usuario_id,
                ]
            );

            return $this->facturacionService->activarServiciosTrasPromesaDePago($factura_interna->cliente);
        });

        foreach ($serviciosReactivados as $servicio) {
            if ($servicio->usuario_pppoe && $servicio->pool?->router) {
                $r = $this->mikrotik->setPppoeDisabledEnRouter($servicio, false);
                if (! $r['success']) {
                    MikrotikOperacionPendiente::registrarSiFallo(
                        MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
                        ['servicio_id' => $servicio->servicio_id, 'disabled' => false],
                        $r['error'] ?? 'Error',
                        'promesas-pago.store'
                    );
                }
            }
        }

        return redirect()
            ->route('promesas-pago.index')
            ->with('success', 'Promesa de pago registrada. Si el servicio estaba suspendido por falta de pago, quedó activado hasta el vencimiento acordado.');
    }
}
