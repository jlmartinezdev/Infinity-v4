<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Servicio;
use App\Models\Ticket;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function stats(Request $request)
    {
        $hoy = now()->toDateString();

        $cobrosHoyQuery = Cobro::query()->whereDate('fecha_pago', $hoy);
        if ($request->user() && ! $request->user()->esAdministrador()) {
            $cobrosHoyQuery->where('usuario_id', $request->user()->usuario_id);
        }

        return $this->ok([
            'clientes_activos' => Cliente::where('estado', 'activo')->count(),
            'servicios_activos' => Servicio::where('estado', Servicio::ESTADO_ACTIVO)->count(),
            'servicios_suspendidos' => Servicio::where('estado', Servicio::ESTADO_SUSPENDIDO)->count(),
            'tickets_abiertos' => Ticket::whereNotIn('estado', ['resuelto', 'cerrado', 'cancelado'])->count(),
            'cobros_hoy_monto' => (float) $cobrosHoyQuery->sum('monto'),
            'cobros_hoy_cantidad' => (clone $cobrosHoyQuery)->count(),
        ]);
    }
}
