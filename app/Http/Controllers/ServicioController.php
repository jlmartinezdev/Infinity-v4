<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use App\Models\Servicio;
use App\Models\Cliente;
use App\Models\Plan;
use App\Models\RouterIpPool;
use App\Models\MikrotikOperacionPendiente;
use App\Models\PoolIpAsignada;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $query = Servicio::with(['cliente', 'plan', 'pool.router.nodo'])
            ->orderBy('servicio_id', 'desc');

        // Cargar todos los servicios; Vue hace filtrado y paginación en el cliente
        $servicios = $query->get();
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->orderBy('nombre')
            ->get(['cliente_id', 'cedula', 'nombre', 'apellido']);

        $serviciosParaVue = $servicios->map(function ($s) {
            return [
                'servicio_id' => $s->servicio_id,
                'cliente' => $s->cliente ? ['cliente_id' => $s->cliente->cliente_id, 'nombre' => $s->cliente->nombre, 'apellido' => $s->cliente->apellido, 'cedula' => $s->cliente->cedula] : null,
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
            ];
        })->values()->all();

        $nodos = Nodo::orderBy('descripcion')->get(['nodo_id', 'descripcion']);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'servicios' => $serviciosParaVue,
                'nodos' => $nodos->map(fn (Nodo $n) => [
                    'nodo_id' => $n->nodo_id,
                    'descripcion' => $n->descripcion,
                ])->values()->all(),
            ]);
        }

        return view('servicios.index', [
            'servicios' => $servicios,
            'clientes' => $clientes,
            'serviciosParaVue' => $serviciosParaVue,
            'nodos' => $nodos,
        ]);
    }

    /**
     * Devuelve las IPs disponibles de un pool (estado = disponible) en JSON.
     */
    public function ipsDisponibles(Request $request)
    {
        $poolId = $request->get('pool_id');
        if (!$poolId) {
            return response()->json(['ips' => []]);
        }

        $ips = PoolIpAsignada::where('pool_id', $poolId)
            ->where('estado', 'disponible')
            ->whereRaw("ip NOT LIKE '%.255'")
            ->pluck('ip')
            ->values()
            ->all();

        // Ordenar IPs como números (octetos) ascendente
        usort($ips, function ($a, $b) {
            $octA = array_map('intval', explode('.', $a));
            $octB = array_map('intval', explode('.', $b));
            for ($i = 0; $i < 4; $i++) {
                if ($octA[$i] !== $octB[$i]) {
                    return $octA[$i] - $octB[$i];
                }
            }
            return 0;
        });

        return response()->json(['ips' => array_values($ips)]);
    }

    public function create(Request $request)
    {
        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->orderBy('nombre')
            ->get();
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        $pools = RouterIpPool::with('router')->where('activo', true)->orderBy('pool_id')->get();

        $clienteId = $request->get('cliente_id');

        return view('servicios.create', compact('clientes', 'planes', 'pools', 'clienteId'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'ip' => ['nullable', 'string', 'max:15', function ($attribute, $value, $fail) {
                if ($value && str_ends_with(trim($value), '.255')) {
                    $fail('La IP no puede terminar en .255 (reservada para broadcast).');
                }
            }],
            'usuario_pppoe' => ['nullable', 'string', 'max:100'],
            'password_pppoe' => ['nullable', 'string', 'max:20'],
            'fecha_instalacion' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'in:A,S,C'],
            'mac_address' => ['nullable', 'string', 'max:20'],
        ]);

        $validated['estado'] = $validated['estado'] ?? 'P';

        // Generar usuario y contraseña PPPoE si vienen vacíos (crear desde cliente)
        if (empty(trim((string) ($validated['usuario_pppoe'] ?? '')))) {
            $cliente = Cliente::find($validated['cliente_id']);
            $nombre = trim($cliente->nombre ?? '');
            $apellido = trim($cliente->apellido ?? '');
            $partes = array_filter([$nombre, $apellido]);
            $usuarioPppoe = str_replace(' ', '_', Str::upper(Str::ascii(implode('_', $partes))));
            $usuarioPppoe = preg_replace('/[^A-Z0-9._-]/', '', $usuarioPppoe);
            if (strlen($usuarioPppoe) < 2) {
                $usuarioPppoe = 'CLIENTE' . $validated['cliente_id'];
            }
            // Asegurar unicidad: si ya existe, agregar sufijo _2, _3, etc.
            $base = $usuarioPppoe;
            $sufijo = 1;
            while (Servicio::where('usuario_pppoe', $usuarioPppoe)->exists()) {
                $sufijo++;
                $usuarioPppoe = $base . '_' . $sufijo;
            }
            $validated['usuario_pppoe'] = $usuarioPppoe;
        }
        if (empty(trim((string) ($validated['password_pppoe'] ?? '')))) {
            $validated['password_pppoe'] = Str::random(8);
        }

        Servicio::create($validated);

        if (!empty($validated['ip'])) {
            PoolIpAsignada::where('pool_id', $validated['pool_id'])
                ->where('ip', $validated['ip'])
                ->update(['estado' => 'asignada']);
        }

        return redirect()->route('servicios.index')->with('success', 'Servicio creado correctamente.');
    }

    public function edit($servicio_id)
    {
        $servicio = Servicio::with(['cliente', 'plan', 'pool', 'servicioHotspot.router'])
            ->findOrFail($servicio_id);

        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->orderBy('nombre')->get();
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        $pools = RouterIpPool::with('router')->where('activo', true)->orderBy('pool_id')->get();

        return view('servicios.edit', compact('servicio', 'clientes', 'planes', 'pools'));
    }

    public function update(Request $request, $servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router', 'plan.perfilPppoe', 'cliente'])->findOrFail($servicio_id);

        $validated = $request->validate([
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'ip' => ['nullable', 'string', 'max:15', function ($attribute, $value, $fail) {
                if ($value && str_ends_with(trim($value), '.255')) {
                    $fail('La IP no puede terminar en .255 (reservada para broadcast).');
                }
            }],
            'usuario_pppoe' => ['nullable', 'string', 'max:100'],
            'password_pppoe' => ['nullable', 'string', 'max:20'],
            'fecha_instalacion' => ['nullable', 'date'],
            'fecha_cancelacion' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'in:A,S,C,P'],
            'mac_address' => ['nullable', 'string', 'max:20'],
        ]);

        $poolOldId = (int) $servicio->pool_id;
        $usuarioOld = trim((string) ($servicio->usuario_pppoe ?? ''));
        $routerOld = $servicio->pool?->router;

        $usuarioNew = trim((string) ($validated['usuario_pppoe'] ?? ''));
        $poolNewId = (int) $validated['pool_id'];

        $ipAnterior = $servicio->ip;
        $poolAnterior = $servicio->pool_id;

        if ($ipAnterior && ($poolAnterior != $validated['pool_id'] || $ipAnterior !== ($validated['ip'] ?? null))) {
            PoolIpAsignada::where('pool_id', $poolAnterior)->where('ip', $ipAnterior)->update(['estado' => 'disponible']);
        }

        if (!empty($validated['ip'])) {
            PoolIpAsignada::where('pool_id', $validated['pool_id'])
                ->where('ip', $validated['ip'])
                ->update(['estado' => 'asignada']);
        }

        $servicio->update($validated);

        $servicio->refresh();
        $servicio->load(['pool.router', 'plan.perfilPppoe', 'cliente']);

        $mensaje = 'Servicio actualizado correctamente.';

        $debeQuitarSecretoAnterior = $routerOld && $usuarioOld !== ''
            && ($poolOldId !== $poolNewId || $usuarioOld !== $usuarioNew);
        if ($debeQuitarSecretoAnterior) {
            $quitar = $mikrotik->removePppoeSecretByName($routerOld, $usuarioOld);
            if (! $quitar['success']) {
                $mensaje .= ' No se pudo quitar el usuario PPPoE anterior en MikroTik: ' . ($quitar['error'] ?? '') . '.';
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET,
                    ['router_id' => $routerOld->router_id, 'usuario_pppoe' => $usuarioOld],
                    $quitar['error'] ?? 'Error al eliminar secreto',
                    'servicios.update'
                );
            } elseif (! empty($quitar['removed'])) {
                $mensaje .= ' Usuario PPPoE anterior eliminado del router.';
            }
        }

        if ($usuarioNew !== '' && $servicio->pool?->router && $servicio->estaActivo()) {
            $sync = $mikrotik->syncPppoeServicio($servicio);
            if ($sync['success']) {
                $mensaje .= ' Sincronizado con MikroTik.';
            } else {
                $mensaje .= ' No se pudo sincronizar con MikroTik: ' . ($sync['error'] ?? 'error desconocido') . '. Podés reintentar con «Sincronizar PPPoE».';
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                    ['servicio_id' => $servicio->servicio_id],
                    $sync['error'] ?? 'Error al sincronizar',
                    'servicios.update'
                );
            }
        }

        return redirect()->route('servicios.index')->with('success', $mensaje);
    }

    public function destroy($servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with('pool.router')->findOrFail($servicio_id);

        $resultadoMk = $mikrotik->quitarPppoeAlBorrarServicio($servicio, 'servicios.destroy');

        if ($servicio->ip) {
            PoolIpAsignada::where('pool_id', $servicio->pool_id)
                ->where('ip', $servicio->ip)
                ->update(['estado' => 'disponible']);
        }

        $servicio->delete();

        $mensaje = 'Servicio eliminado correctamente.';
        if ($resultadoMk['aviso']) {
            $mensaje .= ' '.$resultadoMk['aviso'].' Quedó registrado para reintento automático en operaciones MikroTik pendientes.';

            return redirect()->route('servicios.index')->with('warning', $mensaje);
        }

        return redirect()->route('servicios.index')->with('success', $mensaje);
    }

    /**
     * Reactivar servicio (sistema + router).
     */
    public function activar($servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with('pool.router')->findOrFail($servicio_id);
        $servicio->activar();
        if ($servicio->usuario_pppoe && $servicio->pool?->router) {
            $r = $mikrotik->setPppoeDisabledEnRouter($servicio, false);
            if (! $r['success']) {
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
                    ['servicio_id' => $servicio->servicio_id, 'disabled' => false],
                    $r['error'] ?? 'Error',
                    'servicios.activar'
                );
                return redirect()->back()
                    ->with('success', 'Servicio reactivado en el sistema.')
                    ->with('warning', 'MikroTik: no se pudo habilitar PPPoE — ' . ($r['error'] ?? 'error') . '. Quedó registrado para reintento.');
            }
        }

        return redirect()->back()->with('success', 'Servicio reactivado correctamente.');
    }

    /**
     * Suspender servicio (sistema + router).
     */
    public function suspender($servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with('pool.router')->findOrFail($servicio_id);
        $servicio->suspender();

        if ($servicio->usuario_pppoe && $servicio->pool?->router) {
            $r = $mikrotik->setPppoeDisabledEnRouter($servicio, true);
            if (! $r['success']) {
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
                    ['servicio_id' => $servicio->servicio_id, 'disabled' => true],
                    $r['error'] ?? 'Error',
                    'servicios.suspender'
                );
                return redirect()->back()
                    ->with('success', 'Servicio suspendido en el sistema.')
                    ->with('warning', 'MikroTik: no se pudo deshabilitar PPPoE — ' . ($r['error'] ?? 'error') . '. Quedó registrado para reintento.');
            }
        }

        return redirect()->back()->with('success', 'Servicio suspendido correctamente.');
    }

    /**
     * Sincronizar usuario PPPoE del servicio al router.
     */
    public function syncPppoe($servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router', 'plan.perfilPppoe', 'cliente'])->findOrFail($servicio_id);

        try {
            $result = $mikrotik->syncPppoeServicio($servicio);
        } catch (\Throwable $e) {
            MikrotikOperacionPendiente::registrarSiFallo(
                MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                ['servicio_id' => $servicio->servicio_id],
                $e->getMessage(),
                'servicios.sync-pppoe'
            );

            return redirect()->back()->with('error', 'Error de conexión al router: ' . $e->getMessage());
        }

        if ($result['success']) {
            return redirect()->back()->with('success', 'Usuario PPPoE sincronizado en el router.');
        }

        MikrotikOperacionPendiente::registrarSiFallo(
            MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
            ['servicio_id' => $servicio->servicio_id],
            $result['error'] ?? 'Error al sincronizar',
            'servicios.sync-pppoe'
        );

        return redirect()->back()->with('error', $result['error'] ?? 'Error al sincronizar.');
    }

    /**
     * Formulario para migrar servicio a otro nodo.
     */
    public function migrarForm($servicio_id)
    {
        $servicio = Servicio::with(['cliente', 'plan', 'pool.router.nodo'])->findOrFail($servicio_id);
        $nodoActualId = $servicio->pool?->router?->nodo_id;

        $poolsDestino = RouterIpPool::with(['router.nodo'])
            ->where('activo', true)
            ->whereHas('router', function ($q) use ($nodoActualId) {
                if ($nodoActualId) {
                    $q->where('nodo_id', '!=', $nodoActualId);
                }
            })
            ->orderBy('pool_id')
            ->get();

        $nodos = Nodo::orderBy('descripcion')->get();
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        $ipsDisponiblesUrl = route('servicios.ips-disponibles');

        return view('servicios.migrar', compact('servicio', 'poolsDestino', 'nodos', 'planes', 'ipsDisponiblesUrl'));
    }

    /**
     * Procesar migración de servicio a otro nodo.
     */
    public function migrarStore(Request $request, $servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router', 'plan.perfilPppoe', 'cliente'])->findOrFail($servicio_id);

        $validated = $request->validate([
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
            'ip' => ['required', 'string', 'max:15', function ($attribute, $value, $fail) {
                if (str_ends_with(trim($value), '.255')) {
                    $fail('La IP no puede terminar en .255 (reservada para broadcast).');
                }
            }],
        ]);

        $poolDestino = RouterIpPool::with('router')->findOrFail($validated['pool_id']);
        $nodoActualId = $servicio->pool?->router?->nodo_id;
        if ($poolDestino->router?->nodo_id == $nodoActualId) {
            return redirect()->back()->withInput()->with('error', 'Debe seleccionar un pool de otro nodo.');
        }

        $ipDisponible = PoolIpAsignada::where('pool_id', $validated['pool_id'])
            ->where('ip', $validated['ip'])
            ->where('estado', 'disponible')
            ->exists();

        if (! $ipDisponible) {
            return redirect()->back()->withInput()->with('error', 'La IP seleccionada no está disponible en el pool destino.');
        }

        $routerOrigen = $servicio->pool?->router;
        $usuarioPppoeOrigen = $servicio->usuario_pppoe ? trim($servicio->usuario_pppoe) : '';
        $resultadoEliminarOrigen = null;
        if ($routerOrigen && $usuarioPppoeOrigen !== '') {
            $resultadoEliminarOrigen = $mikrotik->removePppoeSecretByName($routerOrigen, $usuarioPppoeOrigen);
        }

        $ipAnterior = $servicio->ip;
        $poolAnterior = $servicio->pool_id;

        if ($ipAnterior && $poolAnterior) {
            PoolIpAsignada::where('pool_id', $poolAnterior)->where('ip', $ipAnterior)->update(['estado' => 'disponible']);
        }

        PoolIpAsignada::where('pool_id', $validated['pool_id'])
            ->where('ip', $validated['ip'])
            ->update(['estado' => 'asignada']);

        $updateData = [
            'pool_id' => $validated['pool_id'],
            'ip' => $validated['ip'],
            'pppoe_synced' => null,
            'pppoe_status' => null,
        ];
        if (!empty($validated['plan_id'])) {
            $updateData['plan_id'] = $validated['plan_id'];
        }
        $servicio->update($updateData);

        $servicio->refresh();
        $servicio->load(['pool.router', 'plan.perfilPppoe', 'cliente']);

        $mensaje = 'Servicio migrado correctamente al nuevo nodo.';
        if ($resultadoEliminarOrigen !== null) {
            if ($resultadoEliminarOrigen['success']) {
                if (! empty($resultadoEliminarOrigen['removed'])) {
                    $mensaje .= ' Usuario PPPoE eliminado del MikroTik del nodo anterior.';
                }
            } else {
                $mensaje .= ' No se pudo eliminar el usuario PPPoE en el router anterior: ' . ($resultadoEliminarOrigen['error'] ?? 'error desconocido') . '. Revisá el router o eliminá el secreto a mano.';
                if ($routerOrigen && $usuarioPppoeOrigen !== '') {
                    MikrotikOperacionPendiente::registrarSiFallo(
                        MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET,
                        ['router_id' => $routerOrigen->router_id, 'usuario_pppoe' => $usuarioPppoeOrigen],
                        $resultadoEliminarOrigen['error'] ?? 'Error',
                        'servicios.migrar'
                    );
                }
            }
        }
        if ($servicio->usuario_pppoe && $servicio->pool?->router) {
            $syncResult = $mikrotik->syncPppoeServicio($servicio);
            if ($syncResult['success']) {
                $mensaje .= ' Sincronizado con MikroTik en el nuevo nodo.';
            } else {
                $mensaje .= ' Migración OK pero sincronización MikroTik en el nuevo nodo falló: ' . ($syncResult['error'] ?? 'error desconocido') . '. Podés sincronizar manualmente desde el servicio.';
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                    ['servicio_id' => $servicio->servicio_id],
                    $syncResult['error'] ?? 'Error',
                    'servicios.migrar'
                );
            }
        }

        return redirect()->route('servicios.index')->with('success', $mensaje);
    }
}
