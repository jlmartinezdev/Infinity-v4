<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Servicio;
use Illuminate\Http\Request;

class ServicioController extends ApiController
{
    public function index(Request $request)
    {
        $query = Servicio::with(['cliente:cliente_id,nombre,apellido,cedula', 'plan:plan_id,nombre,precio,velocidad'])
            ->orderByDesc('servicio_id');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', (int) $request->cliente_id);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('q')) {
            $q = trim((string) $request->q);
            $query->where(function ($builder) use ($q) {
                $builder->where('usuario_pppoe', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('nombre', 'like', "%{$q}%")
                            ->orWhere('apellido', 'like', "%{$q}%")
                            ->orWhere('cedula', 'like', "%{$q}%");
                    });
            });
        }

        $perPage = min(50, max(1, (int) $request->get('per_page', 20)));

        return $this->ok($query->paginate($perPage));
    }

    public function show(Servicio $servicio)
    {
        $servicio->load(['cliente', 'plan', 'pool.router']);

        return $this->ok([
            'servicio_id' => $servicio->servicio_id,
            'cliente_id' => $servicio->cliente_id,
            'estado' => $servicio->estado,
            'estado_label' => Servicio::estadosDisponibles()[$servicio->estado] ?? $servicio->estado,
            'ip' => $servicio->ip,
            'usuario_pppoe' => $servicio->usuario_pppoe,
            'mac_address' => $servicio->mac_address,
            'pppoe_status' => $servicio->pppoe_status,
            'fecha_instalacion' => optional($servicio->fecha_instalacion)?->toDateString(),
            'fecha_suspension' => optional($servicio->fecha_suspension)?->toDateString(),
            'motivo_suspension' => $servicio->motivo_suspension,
            'saldo_a_favor' => (float) ($servicio->saldo_a_favor ?? 0),
            'cliente' => $servicio->cliente,
            'plan' => $servicio->plan,
            'router' => $servicio->pool?->router ? [
                'router_id' => $servicio->pool->router->router_id,
                'nombre' => $servicio->pool->router->nombre,
            ] : null,
        ]);
    }
}
