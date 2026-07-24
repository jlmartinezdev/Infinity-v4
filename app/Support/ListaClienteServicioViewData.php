<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\Nodo;
use App\Models\Servicio;
use App\Services\FacturacionService;

/**
 * Datos compartidos para la pantalla unificada Clientes / Servicios.
 */
class ListaClienteServicioViewData
{
    public static function forPage(string $listaTab): array
    {
        $user = auth()->user();
        $puedeClientes = $user?->tienePermiso('clientes-lista.ver') ?? false;
        $puedeServicios = $user?->tienePermiso('servicios-lista.ver') ?? false;

        if ($listaTab === 'servicios' && ! $puedeServicios && $puedeClientes) {
            $listaTab = 'clientes';
        }
        if ($listaTab === 'clientes' && ! $puedeClientes && $puedeServicios) {
            $listaTab = 'servicios';
        }

        $data = [
            'listaTab' => $listaTab,
            'puedeVerClientes' => $puedeClientes,
            'puedeVerServicios' => $puedeServicios,
            'urlClientes' => route('clientes.index'),
            'urlServicios' => route('servicios.index'),
        ];

        if ($puedeClientes) {
            $data['clientes'] = self::clientesCompletos();
        }

        if ($puedeServicios) {
            $data = array_merge($data, self::serviciosPayload());
        }

        return $data;
    }

    /**
     * @return array{serviciosParaVue: list<array<string, mixed>>, nodos: \Illuminate\Support\Collection<int, Nodo>, clientesFiltro: \Illuminate\Support\Collection<int, Cliente>}
     */
    public static function serviciosPayload(): array
    {
        $servicios = Servicio::with(['cliente', 'plan', 'pool.router.nodo', 'pool.olt', 'cajaNapPuertoActivo'])
            ->orderBy('servicio_id', 'desc')
            ->get();

        $clientesFiltro = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->orderBy('nombre')
            ->get(['cliente_id', 'cedula', 'nombre', 'apellido']);

        $saldoFacturasPorCliente = app(FacturacionService::class)->mapSaldoPendienteInternasPorClienteIds(
            $servicios->pluck('cliente_id')->unique()->filter()->values()->all()
        );

        $serviciosParaVue = $servicios->map(function ($s) use ($saldoFacturasPorCliente) {
            $cid = $s->cliente_id ? (int) $s->cliente_id : null;

            return [
                'servicio_id' => $s->servicio_id,
                'saldo_facturas_pendiente' => $cid ? (float) ($saldoFacturasPorCliente[$cid] ?? 0) : 0,
                'cliente' => $s->cliente ? ['cliente_id' => $s->cliente->cliente_id, 'nombre' => $s->cliente->nombre, 'apellido' => $s->cliente->apellido, 'cedula' => $s->cliente->cedula, 'url_ubicacion' => trim((string) ($s->cliente->url_ubicacion ?? ''))] : null,
                'plan' => $s->plan ? ['nombre' => $s->plan->nombre] : null,
                'pool' => $s->pool ? [
                    'router' => $s->pool->router ? [
                        'nombre' => $s->pool->router->nombre,
                        'ip' => $s->pool->router->ip,
                        'nodo' => $s->pool->router->nodo ? [
                            'nodo_id' => $s->pool->router->nodo->nodo_id,
                            'descripcion' => $s->pool->router->nodo->descripcion,
                        ] : null,
                    ] : null,
                ] : null,
                'ip' => $s->ip,
                'usuario_pppoe' => $s->usuario_pppoe,
                'password_pppoe' => $s->password_pppoe,
                'fecha_instalacion' => $s->fecha_instalacion?->format('Y-m-d'),
                'fecha_instalacion_formatted' => $s->fecha_instalacion?->format('d/m/Y'),
                'estado' => $s->estado ?? 'P',
                'estado_pago' => $s->estado_pago ?? null,
                'app_tv' => (bool) ($s->app_tv ?? false),
                'tecnologia' => self::servicioEsFibra($s) ? 'fibra' : (self::servicioEsAntena($s) ? 'antena' : null),
                'acuerdo_tipo' => $s->acuerdo_tipo ?? 'ninguno',
                'acuerdo_meses' => $s->acuerdo_meses,
                'acuerdo_desde' => $s->acuerdo_desde?->format('Y-m-d'),
            ];
        })->values()->all();

        return [
            'serviciosParaVue' => $serviciosParaVue,
            'nodos' => Nodo::orderBy('descripcion')->get(),
            'clientesFiltro' => $clientesFiltro,
        ];
    }

    private static function clientesCompletos()
    {
        return Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->with(['servicios.plan', 'servicios.pool'])
            ->orderBy('cliente_id', 'desc')
            ->get();
    }

    private static function servicioEsFibra(Servicio $servicio): bool
    {
        if ($servicio->cajaNapPuertoActivo) {
            return true;
        }
        if ($servicio->pool?->olt_id) {
            return true;
        }
        if ($servicio->pool?->router?->nodo?->manejaGpon()) {
            return true;
        }
        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));
        if (str_contains($planNombre, 'fibra') || str_contains($planNombre, 'gpon') || str_contains($planNombre, 'ftth')) {
            return true;
        }

        return false;
    }

    private static function servicioEsAntena(Servicio $servicio): bool
    {
        if (self::servicioEsFibra($servicio)) {
            return false;
        }

        if (trim((string) ($servicio->ip ?? '')) === '') {
            return false;
        }

        if ($servicio->pool?->router?->nodo?->manejaWireless()) {
            return true;
        }

        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));
        if (str_contains($planNombre, 'wireless') || str_contains($planNombre, 'antena') || str_contains($planNombre, 'radio')) {
            return true;
        }

        return false;
    }
}
