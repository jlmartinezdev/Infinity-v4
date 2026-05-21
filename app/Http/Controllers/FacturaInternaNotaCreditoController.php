<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\FacturaInternaNotaCredito;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturaInternaNotaCreditoController extends Controller
{
    public function index(): View
    {
        $clientes = Cliente::orderBy('nombre')->get(['cliente_id', 'nombre', 'apellido']);

        return view('factura-internas.notas-credito.index', compact('clientes'));
    }

    /**
     * Listado paginado JSON para la vista Vue.
     */
    public function list(Request $request)
    {
        $query = FacturaInternaNotaCredito::query()
            ->with(['facturaInterna.cliente', 'usuario'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('cliente_id')) {
            $clienteId = (int) $request->cliente_id;
            if ($clienteId > 0) {
                $query->whereHas('facturaInterna', fn ($q) => $q->where('cliente_id', $clienteId));
            }
        }

        if ($request->filled('desde')) {
            $query->whereDate('created_at', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('created_at', '<=', $request->hasta);
        }

        $busqueda = trim($request->input('q', ''));
        if ($busqueda !== '') {
            $term = '%'.addcslashes($busqueda, '%_\\').'%';
            $query->where(function ($w) use ($busqueda, $term) {
                if (ctype_digit($busqueda)) {
                    $id = (int) $busqueda;
                    $w->where('factura_interna_notas_credito.id', $id)
                        ->orWhere('factura_interna_notas_credito.factura_interna_id', $id);
                }
                $w->orWhereHas('facturaInterna.cliente', function ($cq) use ($term) {
                    $cq->where('nombre', 'like', $term)
                        ->orWhere('apellido', 'like', $term)
                        ->orWhere('cedula', 'like', $term);
                });
            });
        }

        $statsQuery = (clone $query)->reorder();
        $cantidad = (int) (clone $statsQuery)->count('factura_interna_notas_credito.id');
        $totalMonto = (float) (clone $statsQuery)->sum('monto');

        $perPage = min(50, max(5, (int) $request->get('per_page', 20)));
        $paginator = $query->paginate($perPage);

        $paginator->through(function (FacturaInternaNotaCredito $nc) {
            $f = $nc->facturaInterna;
            $c = $f?->cliente;
            $usuario = $nc->usuario;

            return [
                'id' => $nc->id,
                'factura_interna_id' => (int) $nc->factura_interna_id,
                'monto' => (float) $nc->monto,
                'motivo' => $nc->motivo,
                'moneda' => $f?->moneda ?? 'PYG',
                'created_at' => $nc->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'cliente_id' => $c ? (int) $c->cliente_id : null,
                'cliente_nombre' => $c ? trim(($c->nombre ?? '').' '.($c->apellido ?? '')) : '',
                'cliente_cedula' => $c?->cedula ?? '',
                'factura_estado' => $f?->estado,
                'factura_total' => $f ? (float) $f->total : null,
                'usuario_nombre' => $usuario?->name ?? $usuario?->email ?? '',
            ];
        });

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'stats' => [
                'cantidad' => $cantidad,
                'total_monto' => $totalMonto,
            ],
        ]);
    }
}
