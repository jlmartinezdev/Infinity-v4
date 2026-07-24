<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Router;
use App\Models\ServicioConexionEvento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicioPppoeEventoController extends Controller
{
    public function index(Request $request)
    {
        $filtrada = $this->queryFiltrada($request);

        $gruposSub = (clone $filtrada)
            ->join('servicios', 'servicios.servicio_id', '=', 'servicio_conexion_eventos.servicio_id')
            ->select('servicios.cliente_id')
            ->selectRaw('COUNT(*) as total_eventos')
            ->selectRaw('MAX(servicio_conexion_eventos.ocurrio_at) as ultimo_ocurrio_at')
            ->groupBy('servicios.cliente_id');

        $grupos = DB::query()
            ->fromSub($gruposSub, 'grupos_pppoe')
            ->orderByDesc('ultimo_ocurrio_at')
            ->paginate(30)
            ->withQueryString();

        $clienteIds = collect($grupos->items())
            ->pluck('cliente_id')
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $clientes = $clienteIds->isNotEmpty()
            ? Cliente::query()->whereIn('cliente_id', $clienteIds)->get()->keyBy('cliente_id')
            : collect();

        $ultimosPorCliente = collect();
        if ($grupos->count() > 0) {
            $eventosRecientes = (clone $filtrada)
                ->with(['servicio.cliente', 'router'])
                ->whereHas('servicio', function (Builder $q) use ($grupos) {
                    $ids = collect($grupos->items())->pluck('cliente_id')->unique()->values();
                    $q->where(function (Builder $inner) use ($ids) {
                        $conCliente = $ids->filter(fn ($id) => $id !== null)->values();
                        if ($conCliente->isNotEmpty()) {
                            $inner->whereIn('cliente_id', $conCliente);
                        }
                        if ($ids->contains(null)) {
                            $inner->orWhereNull('cliente_id');
                        }
                    });
                })
                ->orderByDesc('ocurrio_at')
                ->orderByDesc('servicio_conexion_evento_id')
                ->get();

            $ultimosPorCliente = $eventosRecientes->groupBy(fn (ServicioConexionEvento $ev) => $ev->servicio?->cliente_id ?? 'sin-cliente')
                ->map->first();
        }

        $filas = collect($grupos->items())->map(function ($grupo) use ($clientes, $ultimosPorCliente) {
            $clave = $grupo->cliente_id ?? 'sin-cliente';
            $ultimo = $ultimosPorCliente->get($clave);
            $cliente = $grupo->cliente_id ? $clientes->get((int) $grupo->cliente_id) : null;

            return (object) [
                'cliente_id' => $grupo->cliente_id,
                'cliente' => $cliente,
                'cliente_nombre' => $cliente
                    ? trim($cliente->nombre.' '.($cliente->apellido ?? ''))
                    : ($ultimo?->usuario_pppoe ?: 'Sin cliente'),
                'total_eventos' => (int) $grupo->total_eventos,
                'ultimo_ocurrio_at' => $grupo->ultimo_ocurrio_at,
                'ultimo_evento' => $ultimo,
            ];
        });

        $grupos->setCollection($filas);

        $routers = Router::query()
            ->orderBy('nombre')
            ->get(['router_id', 'nombre']);

        $resumenHoy = ServicioConexionEvento::query()
            ->pppoe()
            ->fuenteMikrotik()
            ->whereDate('ocurrio_at', today())
            ->selectRaw('tipo, COUNT(*) as total')
            ->groupBy('tipo')
            ->pluck('total', 'tipo');

        return view('servicios.pppoe-eventos.index', [
            'grupos' => $grupos,
            'routers' => $routers,
            'resumenHoy' => $resumenHoy,
        ]);
    }

    /** @return Builder<ServicioConexionEvento> */
    private function queryFiltrada(Request $request): Builder
    {
        $query = ServicioConexionEvento::query()->pppoe();

        $fuente = $request->input('fuente', 'mikrotik');
        if ($fuente === 'mikrotik') {
            $query->fuenteMikrotik();
        } elseif ($fuente !== '' && $fuente !== 'todas') {
            $query->where('fuente', $fuente);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->input('tipo'));
        }

        if ($request->filled('router_id')) {
            $query->where('router_id', (int) $request->input('router_id'));
        }

        if ($request->filled('buscar')) {
            $term = trim((string) $request->input('buscar'));
            $query->where(function ($q) use ($term) {
                $q->where('usuario_pppoe', 'like', '%'.$term.'%')
                    ->orWhere('ip', 'like', '%'.$term.'%')
                    ->orWhere('mac_address', 'like', '%'.$term.'%')
                    ->orWhereHas('servicio.cliente', function ($cq) use ($term) {
                        $cq->where('nombre', 'like', '%'.$term.'%')
                            ->orWhere('apellido', 'like', '%'.$term.'%')
                            ->orWhere('cedula', 'like', '%'.$term.'%');
                    });
            });
        }

        if ($request->filled('fecha_desde')) {
            $query->whereDate('ocurrio_at', '>=', $request->input('fecha_desde'));
        }

        if ($request->filled('fecha_hasta')) {
            $query->whereDate('ocurrio_at', '<=', $request->input('fecha_hasta'));
        }

        return $query;
    }
}
