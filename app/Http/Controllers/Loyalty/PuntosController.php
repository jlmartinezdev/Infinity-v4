<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClientePuntos;
use App\Models\LoyaltyRegla;
use App\Models\PuntosMovimiento;
use App\Services\Loyalty\PuntosService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PuntosController extends Controller
{
    public function __construct(
        private readonly PuntosService $puntos
    ) {}

    public function index(Request $request)
    {
        $reglas = LoyaltyRegla::query()->orderBy('orden')->orderBy('nombre')->get();
        $movimientos = PuntosMovimiento::with(['cliente', 'creador'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $saldoCliente = null;
        $clienteBuscado = null;
        if ($request->filled('cedula')) {
            $clienteBuscado = Cliente::query()
                ->where('cedula', trim($request->cedula))
                ->first();
            if ($clienteBuscado) {
                $saldoCliente = $this->puntos->saldo((int) $clienteBuscado->cliente_id);
            }
        }

        $inicioMes = now()->startOfMonth();
        $reglaPago = $reglas->firstWhere('evento', LoyaltyRegla::EVENTO_PAGO)
            ?? $reglas->firstWhere('codigo', 'pago_recibido');

        return view('loyalty.puntos.index', [
            'reglas' => $reglas,
            'movimientos' => $movimientos,
            'clienteBuscado' => $clienteBuscado,
            'saldoCliente' => $saldoCliente,
            'eventos' => LoyaltyRegla::eventos(),
            'frecuencias' => LoyaltyRegla::frecuencias(),
            'reglaPago' => $reglaPago,
            'puntosPorDiaPago' => $reglaPago?->puntosPorDiaHasta() ?? [],
            'diasPagoMax' => LoyaltyRegla::DIAS_PAGO_CONFIGURABLES,
            'stats' => [
                'reglas_activas' => $reglas->where('activa', true)->count(),
                'clientes_con_saldo' => ClientePuntos::query()->where('saldo', '>', 0)->count(),
                'puntos_circulacion' => (int) ClientePuntos::query()->sum('saldo'),
                'movimientos_mes' => PuntosMovimiento::query()->where('created_at', '>=', $inicioMes)->count(),
                'acreditados_mes' => (int) PuntosMovimiento::query()
                    ->where('created_at', '>=', $inicioMes)
                    ->where('puntos', '>', 0)
                    ->sum('puntos'),
            ],
        ]);
    }

    public function storeRegla(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:60', 'unique:loyalty_reglas,codigo', 'regex:/^[a-z0-9_\\-]+$/'],
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'puntos' => ['nullable', 'integer'],
            'evento' => ['required', Rule::in(array_keys(LoyaltyRegla::eventos()))],
            'frecuencia' => ['nullable', Rule::in(array_keys(LoyaltyRegla::frecuencias()))],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fase' => ['nullable', 'integer', 'min:1', 'max:9'],
            'activa' => ['nullable', 'boolean'],
            'visible_portal' => ['nullable', 'boolean'],
            'dia_puntos' => ['nullable', 'array'],
            'dia_puntos.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['codigo'] = Str::lower($data['codigo']);
        $data['activa'] = $request->boolean('activa', true);
        $data['visible_portal'] = $request->boolean('visible_portal', true);
        $data['puntos'] = (int) ($data['puntos'] ?? 0);
        $data['orden'] = (int) ($data['orden'] ?? 0);
        $data['fase'] = filled($data['fase'] ?? null) ? (int) $data['fase'] : null;
        $data['frecuencia'] = $data['frecuencia'] ?? match ($data['evento']) {
            LoyaltyRegla::EVENTO_BIENVENIDA => LoyaltyRegla::FRECUENCIA_UNICA,
            LoyaltyRegla::EVENTO_PAGO => LoyaltyRegla::FRECUENCIA_MENSUAL,
            default => LoyaltyRegla::FRECUENCIA_EVENTO,
        };

        if ($data['evento'] === LoyaltyRegla::EVENTO_PAGO) {
            $data['condiciones'] = LoyaltyRegla::condicionesPagoDesdeMapa($request->input('dia_puntos', []));
            $mapa = $data['condiciones']['puntos_por_dia'];
            if ($mapa !== []) {
                $data['puntos'] = max(array_map('intval', $mapa));
            }
        }

        LoyaltyRegla::create($data);

        return back()->with('success', 'Regla creada.');
    }

    public function updateRegla(Request $request, LoyaltyRegla $regla)
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'puntos' => ['nullable', 'integer'],
            'evento' => ['required', Rule::in(array_keys(LoyaltyRegla::eventos()))],
            'frecuencia' => ['nullable', Rule::in(array_keys(LoyaltyRegla::frecuencias()))],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'fase' => ['nullable', 'integer', 'min:1', 'max:9'],
            'activa' => ['nullable', 'boolean'],
            'visible_portal' => ['nullable', 'boolean'],
            'dia_puntos' => ['nullable', 'array'],
            'dia_puntos.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $data['activa'] = $request->boolean('activa', true);
        $data['visible_portal'] = $request->boolean('visible_portal', true);
        $data['puntos'] = (int) ($data['puntos'] ?? 0);
        $data['orden'] = (int) ($data['orden'] ?? 0);
        $data['fase'] = filled($data['fase'] ?? null) ? (int) $data['fase'] : null;
        $data['frecuencia'] = $data['frecuencia'] ?? $regla->frecuenciaInferida();

        if ($data['evento'] === LoyaltyRegla::EVENTO_PAGO) {
            $data['condiciones'] = LoyaltyRegla::condicionesPagoDesdeMapa($request->input('dia_puntos', []));
            $mapa = $data['condiciones']['puntos_por_dia'];
            if ($mapa !== []) {
                $data['puntos'] = max(array_map('intval', $mapa));
            }
        } else {
            $data['condiciones'] = null;
        }

        $regla->update($data);

        return back()->with('success', 'Regla actualizada.');
    }

    public function guardarPuntosPorDia(Request $request, LoyaltyRegla $regla)
    {
        if ($regla->evento !== LoyaltyRegla::EVENTO_PAGO) {
            return back()->with('error', 'Solo reglas de pago admiten puntos por día.');
        }

        $request->validate([
            'dia_puntos' => ['nullable', 'array'],
            'dia_puntos.*' => ['nullable', 'integer', 'min:0'],
            'activa' => ['nullable', 'boolean'],
        ]);

        $condiciones = LoyaltyRegla::condicionesPagoDesdeMapa($request->input('dia_puntos', []));
        $mapa = $condiciones['puntos_por_dia'];

        $regla->condiciones = $condiciones;
        $regla->puntos = $mapa !== [] ? max(array_map('intval', $mapa)) : (int) $regla->puntos;
        if ($request->has('activa')) {
            $regla->activa = $request->boolean('activa');
        }
        $regla->save();

        return back()->with('success', 'Puntos por día de pago guardados (solo factura de servicio).');
    }

    public function toggleRegla(LoyaltyRegla $regla)
    {
        $regla->activa = ! $regla->activa;
        $regla->save();

        return back()->with('success', $regla->activa
            ? 'Regla activada: '.$regla->nombre
            : 'Regla desactivada: '.$regla->nombre);
    }

    public function destroyRegla(LoyaltyRegla $regla)
    {
        $regla->delete();

        return back()->with('success', 'Regla eliminada.');
    }

    public function destroyMovimiento(PuntosMovimiento $movimiento)
    {
        try {
            $this->puntos->eliminarMovimiento($movimiento);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Movimiento eliminado y saldo actualizado.');
    }

    public function ajustar(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'puntos' => ['required', 'integer', 'not_in:0'],
            'concepto' => ['required', 'string', 'max:255'],
        ]);

        try {
            if ($data['puntos'] > 0) {
                $this->puntos->acreditar(
                    (int) $data['cliente_id'],
                    (int) $data['puntos'],
                    $data['concepto'],
                    'ajuste',
                    ['created_by' => auth()->user()?->usuario_id]
                );
            } else {
                $this->puntos->debitar(
                    (int) $data['cliente_id'],
                    abs((int) $data['puntos']),
                    $data['concepto'],
                    'ajuste',
                    ['created_by' => auth()->user()?->usuario_id]
                );
            }
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Ajuste de puntos aplicado.');
    }
}
