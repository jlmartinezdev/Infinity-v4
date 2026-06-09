<?php

namespace App\Http\Controllers;

use App\Models\Cobro;
use App\Models\CobroRendicion;
use App\Services\CobroRendicionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CobroRendicionController extends Controller
{
    public function __construct(
        private CobroRendicionService $rendicionService
    ) {}

    public function index(Request $request)
    {
        $pendientes = $this->rendicionService->resumenPendientesPorCobrador();
        $totalPendiente = (float) $pendientes->sum('monto');
        $totalCobrosPendientes = (int) $pendientes->sum('cantidad');

        $sinCobrador = $this->rendicionService->queryPendientes()
            ->whereNull('usuario_id')
            ->count();

        $rendiciones = CobroRendicion::query()
            ->with(['cobrador', 'tesorero'])
            ->orderByDesc('fecha_rendicion')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('cobros-rendiciones.index', compact(
            'pendientes',
            'totalPendiente',
            'totalCobrosPendientes',
            'sinCobrador',
            'rendiciones'
        ));
    }

    public function show(CobroRendicion $cobro_rendicion)
    {
        $cobro_rendicion->load([
            'cobrador',
            'tesorero',
            'cobros' => fn ($q) => $q->with('cliente')->orderBy('fecha_pago')->orderBy('id'),
        ]);

        return view('cobros-rendiciones.show', ['rendicion' => $cobro_rendicion]);
    }

    public function pendientesUsuario(int $usuario)
    {
        $cobros = $this->rendicionService->queryPendientes($usuario)
            ->with('cliente')
            ->orderBy('fecha_pago')
            ->orderBy('id')
            ->get();

        return response()->json([
            'usuario_id' => $usuario,
            'cantidad' => $cobros->count(),
            'monto' => round((float) $cobros->sum('monto'), 2),
            'cobros' => $cobros->map(fn (Cobro $c) => [
                'id' => $c->id,
                'numero_recibo' => $c->numero_recibo,
                'fecha_pago' => optional($c->fecha_pago)->format('d/m/Y H:i'),
                'cliente' => trim(($c->cliente?->nombre ?? '').' '.($c->cliente?->apellido ?? '')),
                'monto' => (float) $c->monto,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usuario_cobrador_id' => ['required', 'integer', 'exists:users,usuario_id'],
            'fecha_rendicion' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $rendicion = $this->rendicionService->registrar(
                (int) $validated['usuario_cobrador_id'],
                Carbon::parse($validated['fecha_rendicion']),
                $request->user()?->usuario_id,
                $validated['observaciones'] ?? null
            );
        } catch (\RuntimeException $e) {
            return redirect()->route('cobros-rendiciones.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('cobros-rendiciones.show', $rendicion)
            ->with('success', 'Rendición registrada: '.number_format((float) $rendicion->monto, 0, ',', '.').' Gs. recibidos de '.$rendicion->cobrador?->name.'.');
    }
}
