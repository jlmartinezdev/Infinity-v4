<?php

namespace App\Http\Controllers;

use App\Models\CajaNapPuertoActivo;
use App\Models\Nodo;
use App\Models\Servicio;
use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\RouterIpPool;
use App\Models\MikrotikOperacionPendiente;
use App\Models\PoolIpAsignada;
use App\Support\HerramientasRedPayload;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use App\Services\NetworkPingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Support\ListaClienteServicioViewData;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        if ($request->wantsJson() || $request->ajax()) {
            $payload = ListaClienteServicioViewData::serviciosPayload();

            return response()->json([
                'servicios' => $payload['serviciosParaVue'],
                'nodos' => $payload['nodos']->map(fn (Nodo $n) => $n->toArraySelect())->values()->all(),
                'planes' => $payload['planes'] ?? [],
            ]);
        }

        return view('listas.cliente-servicio', ListaClienteServicioViewData::forPage('servicios'));
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
            'acuerdo_tipo' => ['nullable', 'string', 'in:ninguno,libre,meses'],
            'acuerdo_meses' => ['nullable', 'integer', 'min:1', 'max:24'],
            'acuerdo_desde' => ['nullable', 'date'],
        ]);

        $validated['estado'] = $validated['estado'] ?? 'P';
        $validated['acuerdo_tipo'] = $validated['acuerdo_tipo'] ?? 'ninguno';
        if ($validated['acuerdo_tipo'] !== 'meses') {
            $validated['acuerdo_meses'] = null;
            $validated['acuerdo_desde'] = null;
        } elseif (empty($validated['acuerdo_desde'])) {
            $validated['acuerdo_desde'] = now()->toDateString();
        }

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

        $planIdAnterior = (int) $servicio->plan_id;
        $planAnterior = $servicio->plan ?? Plan::find($planIdAnterior);

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
            'estado' => ['nullable', 'string', 'in:A,S,C,P,X'],
            'mac_address' => ['nullable', 'string', 'max:20'],
            'acuerdo_tipo' => ['nullable', 'string', 'in:ninguno,libre,meses'],
            'acuerdo_meses' => ['nullable', 'integer', 'min:1', 'max:24'],
            'acuerdo_desde' => ['nullable', 'date'],
        ]);
        $validated['acuerdo_tipo'] = $validated['acuerdo_tipo'] ?? 'ninguno';
        if ($validated['acuerdo_tipo'] !== 'meses') {
            $validated['acuerdo_meses'] = null;
            $validated['acuerdo_desde'] = null;
        } elseif (empty($validated['acuerdo_desde'])) {
            $validated['acuerdo_desde'] = now()->toDateString();
        }

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

        if ($planIdAnterior !== (int) $validated['plan_id'] && $planAnterior) {
            try {
                $generarFacturaProrr = $request->boolean('generar_factura_prorrateo_cambio_plan', true);
                $extraCambioPlan = app(FacturacionService::class)->registrarPostCambioPlanServicio(
                    $servicio,
                    $planAnterior,
                    $request->user()?->usuario_id,
                    $generarFacturaProrr
                );
                if ($extraCambioPlan !== '') {
                    $mensaje .= ' '.$extraCambioPlan;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Cambio de plan: factura/ticket: '.$e->getMessage(), [
                    'servicio_id' => $servicio->servicio_id,
                ]);
                $mensaje .= ' No se pudo completar el registro automático de factura/ticket por cambio de plan; revisar manualmente.';
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
     * Cancelar servicio: factura interna prorrateada (día 1 del mes hasta hoy), estado cancelado,
     * PPPoE deshabilitado; si el cliente no tiene otros servicios no cancelados, pasa a inactivo.
     */
    public function cancelar(Request $request, $servicio_id, MikroTikService $mikrotik, FacturacionService $facturacion)
    {
        if (! $request->user()?->tienePermiso('facturas.crear')) {
            abort(403, 'No tenés permiso para generar la factura de cancelación.');
        }

        $servicio = Servicio::with(['plan', 'cliente', 'pool.router'])->findOrFail($servicio_id);

        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->back()->with('error', 'El servicio ya está cancelado.');
        }

        $fechaCancel = Carbon::parse($request->input('fecha_cancelacion', now()->toDateString()))->startOfDay();
        $hoy = now()->startOfDay();
        if ($fechaCancel->gt($hoy)) {
            return redirect()->back()->with('error', 'La fecha de cancelación no puede ser futura.');
        }
        if ($servicio->fecha_instalacion) {
            $iniInst = Carbon::parse($servicio->fecha_instalacion)->startOfDay();
            if ($fechaCancel->lt($iniInst)) {
                return redirect()->back()->with('error', 'La fecha de cancelación no puede ser anterior a la instalación del servicio.');
            }
        }

        $precioPlan = (float) ($servicio->plan?->precio ?? 0);
        $monto = FacturacionService::calcularMontoProrrateoCancelacionMes($fechaCancel, $precioPlan);

        $clienteId = (int) $servicio->cliente_id;
        $soloEsteServicioNoCancelado = Servicio::where('cliente_id', $clienteId)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->count() === 1;

        $mensaje = '';
        $warning = null;

        try {
            DB::transaction(function () use ($servicio, $fechaCancel, $monto, $facturacion, $request, $soloEsteServicioNoCancelado, &$mensaje) {
                $factura = $facturacion->generarFacturaInternaPorCancelacionServicio(
                    $servicio,
                    $fechaCancel,
                    $monto,
                    $request->user()?->usuario_id
                );
                $mensaje = 'Servicio cancelado. Factura interna #'.$factura->id.' por '.number_format($monto, 0, ',', '.').' Gs.';

                $servicio->update([
                    'estado' => Servicio::ESTADO_CANCELADO,
                    'fecha_cancelacion' => $fechaCancel->toDateString(),
                    'fecha_suspension' => null,
                    'motivo_suspension' => null,
                ]);

                if ($soloEsteServicioNoCancelado && $servicio->cliente) {
                    $servicio->cliente->update(['estado' => 'inactivo']);
                    $mensaje .= ' El cliente quedó inactivo (no tenía otros servicios activos).';
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Cancelar servicio: '.$e->getMessage(), [
                'servicio_id' => $servicio_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'No se pudo cancelar el servicio: '.$e->getMessage());
        }

        $servicio->refresh();
        $servicio->load('pool.router');
        if ($servicio->usuario_pppoe && $servicio->pool?->router) {
            $r = $mikrotik->setPppoeDisabledEnRouter($servicio, true);
            if (! $r['success']) {
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
                    ['servicio_id' => $servicio->servicio_id, 'disabled' => true],
                    $r['error'] ?? 'Error',
                    'servicios.cancelar'
                );
                $warning = 'MikroTik: no se pudo deshabilitar PPPoE — '.($r['error'] ?? 'error').'. Quedó registrado para reintento.';
            }
        }

        if ($warning) {
            return redirect()->back()->with('success', $mensaje)->with('warning', $warning);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    /**
     * Dar de baja sin factura prorrateada: cancela el servicio, libera IP y puerto NAP,
     * deshabilita PPPoE; el cliente pasa a inactivo si no tiene otros servicios no cancelados.
     */
    public function darBaja(Request $request, $servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['cliente', 'pool.router', 'cajaNapPuertoActivo'])->findOrFail($servicio_id);

        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->back()->with('error', 'El servicio ya está cancelado.');
        }

        $fechaBaja = Carbon::parse($request->input('fecha_cancelacion', now()->toDateString()))->startOfDay();
        $hoy = now()->startOfDay();
        if ($fechaBaja->gt($hoy)) {
            return redirect()->back()->with('error', 'La fecha de baja no puede ser futura.');
        }
        if ($servicio->fecha_instalacion) {
            $iniInst = Carbon::parse($servicio->fecha_instalacion)->startOfDay();
            if ($fechaBaja->lt($iniInst)) {
                return redirect()->back()->with('error', 'La fecha de baja no puede ser anterior a la instalación del servicio.');
            }
        }

        $clienteId = (int) $servicio->cliente_id;
        $soloEsteServicioNoCancelado = Servicio::where('cliente_id', $clienteId)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->count() === 1;

        $liberoIp = false;
        $liberoPuertoNap = false;

        try {
            DB::transaction(function () use ($servicio, $fechaBaja, $soloEsteServicioNoCancelado, &$liberoIp, &$liberoPuertoNap) {
                $liberoIp = $this->liberarIpServicio($servicio);
                $liberoPuertoNap = $this->liberarPuertoNapServicio($servicio);

                $servicio->update([
                    'estado' => Servicio::ESTADO_CANCELADO,
                    'fecha_cancelacion' => $fechaBaja->toDateString(),
                    'fecha_suspension' => null,
                    'motivo_suspension' => null,
                    'ip' => null,
                ]);

                if ($soloEsteServicioNoCancelado && $servicio->cliente) {
                    $servicio->cliente->update(['estado' => 'inactivo']);
                }
            });
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Dar de baja servicio: '.$e->getMessage(), [
                'servicio_id' => $servicio_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->back()->with('error', 'No se pudo dar de baja el servicio: '.$e->getMessage());
        }

        $mensaje = 'Servicio dado de baja sin factura.';
        if ($liberoIp) {
            $mensaje .= ' IP liberada en el pool.';
        }
        if ($liberoPuertoNap) {
            $mensaje .= ' Puerto NAP liberado.';
        }
        if ($soloEsteServicioNoCancelado) {
            $mensaje .= ' El cliente quedó inactivo.';
        }

        $warning = $this->deshabilitarPppoeTrasBaja($servicio->fresh(['pool.router']), $mikrotik, 'servicios.dar-baja');

        if ($warning) {
            return redirect()->back()->with('success', $mensaje)->with('warning', $warning);
        }

        return redirect()->back()->with('success', $mensaje);
    }

    private function liberarIpServicio(Servicio $servicio): bool
    {
        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '' || ! $servicio->pool_id) {
            return false;
        }

        PoolIpAsignada::where('pool_id', $servicio->pool_id)
            ->where('ip', $ip)
            ->update(['estado' => 'disponible']);

        return true;
    }

    private function liberarPuertoNapServicio(Servicio $servicio): bool
    {
        $actualizado = CajaNapPuertoActivo::where('servicio_id', $servicio->servicio_id)
            ->update([
                'servicio_id' => null,
                'potencia_cliente' => null,
            ]);

        return $actualizado > 0;
    }

    private function deshabilitarPppoeTrasBaja(Servicio $servicio, MikroTikService $mikrotik, string $contexto): ?string
    {
        if (! $servicio->usuario_pppoe || ! $servicio->pool?->router) {
            return null;
        }

        $r = $mikrotik->setPppoeDisabledEnRouter($servicio, true);
        if ($r['success']) {
            return null;
        }

        MikrotikOperacionPendiente::registrarSiFallo(
            MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED,
            ['servicio_id' => $servicio->servicio_id, 'disabled' => true],
            $r['error'] ?? 'Error',
            $contexto
        );

        return 'MikroTik: no se pudo deshabilitar PPPoE — '.($r['error'] ?? 'error').'. Quedó registrado para reintento.';
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
     * Ping ICMP a la IP del servicio (CPE/ONU).
     */
    public function ping($servicio_id, NetworkPingService $pingService)
    {
        $servicio = Servicio::with(['cliente'])->findOrFail($servicio_id);
        $ip = trim((string) ($servicio->ip ?? ''));

        if ($ip === '') {
            return response()->json([
                'success' => false,
                'alive' => false,
                'ip' => null,
                'message' => 'El servicio no tiene IP asignada para hacer ping.',
                'output' => '',
            ], 422);
        }

        try {
            $result = $pingService->ping($ip, 4);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'alive' => false,
                'ip' => $ip,
                'message' => $e->getMessage(),
                'output' => '',
            ], 422);
        }

        $cliente = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
            'cliente' => $cliente !== '' ? $cliente : null,
        ]);
    }

    /**
     * Vista de herramientas de red (ping, MAC, tráfico MikroTik).
     */
    public function herramientasRed($servicio_id)
    {
        $servicio = Servicio::with(HerramientasRedPayload::relaciones())->findOrFail($servicio_id);
        $payload = HerramientasRedPayload::fromServicio($servicio);

        $ticketOrigen = null;
        $ticketId = (int) request()->query('ticket_id');
        if ($ticketId > 0 && $servicio->cliente_id) {
            $ticketOrigen = Ticket::query()
                ->whereKey($ticketId)
                ->where('cliente_id', $servicio->cliente_id)
                ->first(['id', 'descripcion', 'datos_diagnostico', 'created_at', 'prioridad', 'reportado_desde']);
        }

        $herramientasRedConfig = [
            'compact' => false,
            'initialPayload' => $payload,
            'servicios' => [HerramientasRedPayload::opcionServicio($servicio)],
        ];

        return view('servicios.herramientas-red', compact(
            'servicio',
            'ticketOrigen',
            'herramientasRedConfig',
        ));
    }

    /**
     * JSON para reutilizar herramientas de red (detalle cliente / selector de servicio).
     */
    public function herramientasRedDatos($servicio_id)
    {
        $servicio = Servicio::with(HerramientasRedPayload::relaciones())->findOrFail($servicio_id);

        return response()->json(HerramientasRedPayload::fromServicio($servicio));
    }

    /**
     * SSH a antena Ubiquiti del cliente (IP del servicio) → wstalist.
     */
    public function herramientasRedAntena(
        $servicio_id,
        MikroTikService $mikrotik,
        \App\Services\Ubnt\UbntAntenaService $ubnt
    ) {
        @set_time_limit(60);

        $servicio = Servicio::with(['cliente', 'pool.router'])->findOrFail($servicio_id);

        if ($this->servicioEsFibra($servicio)) {
            return response()->json([
                'success' => false,
                'message' => 'La consulta wstalist aplica a clientes wireless/antena, no a fibra/GPON.',
            ], 422);
        }

        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '') {
            return response()->json([
                'success' => false,
                'message' => 'El servicio no tiene IP para conectar por SSH a la antena.',
            ], 422);
        }

        $macEsperada = null;
        $router = $servicio->pool?->router;
        if ($router) {
            $mk = $mikrotik->consultarClienteRed($router, $servicio->ip, $servicio->usuario_pppoe);
            if (! empty($mk['mac'])) {
                $macEsperada = $mk['mac'];
            }
        }
        if (! $macEsperada && filled($servicio->mac_address)) {
            $macEsperada = strtoupper(str_replace('-', ':', (string) $servicio->mac_address));
        }

        $result = $ubnt->consultarWstalist($ip, $macEsperada);

        if ($result['success'] ?? false) {
            try {
                \App\Models\ServicioConexionEvento::registrarSenalAntena(
                    $servicio,
                    [
                        'ip' => $ip,
                        'mac' => $result['mac_remota'] ?? $macEsperada,
                        'router_id' => $router?->router_id,
                        'antena_signal_dbm' => $result['signal_dbm'] ?? null,
                        'antena_snr_db' => $result['snr_db'] ?? null,
                        'antena_radio_iface' => 'wstalist',
                        'payload' => [
                            'host' => $ip,
                            'comando' => $result['comando'] ?? 'wstalist',
                            'noise_floor_dbm' => $result['noise_floor_dbm'] ?? null,
                            'ccq' => $result['ccq'] ?? null,
                            'tx_rx_rate' => $result['tx_rx_rate'] ?? null,
                            'capacity' => $result['capacity'] ?? null,
                            'distance' => $result['distance'] ?? null,
                            'mac_remota' => $result['mac_remota'] ?? null,
                            'ap_mac' => $result['ap_mac'] ?? null,
                            'ap_name' => $result['ap_name'] ?? null,
                            'signal_chains' => $result['signal_chains'] ?? null,
                            'chain_delta' => $result['chain_delta'] ?? null,
                            'dl_linkscore' => $result['stations'][0]['dl_linkscore'] ?? null,
                            'ul_linkscore' => $result['stations'][0]['ul_linkscore'] ?? null,
                        ],
                    ],
                    \App\Models\ServicioConexionEvento::FUENTE_UBNT_SSH
                );
            } catch (\Throwable) {
                // No bloquear la consulta si falla el historial
            }
        }

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
            'ip' => $ip,
            'mac_esperada' => $macEsperada,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * SSH a antena Ubiquiti del cliente → cat /tmp/dhcpd.leases.
     */
    public function herramientasRedAntenaDhcp($servicio_id, \App\Services\Ubnt\UbntAntenaService $ubnt)
    {
        @set_time_limit(60);

        $servicio = Servicio::with(['cliente'])->findOrFail($servicio_id);

        if ($this->servicioEsFibra($servicio)) {
            return response()->json([
                'success' => false,
                'message' => 'La consulta DHCP leases aplica a clientes wireless/antena, no a fibra/GPON.',
            ], 422);
        }

        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '') {
            return response()->json([
                'success' => false,
                'message' => 'El servicio no tiene IP para conectar por SSH a la antena.',
            ], 422);
        }

        $result = $ubnt->consultarDhcpLeases($ip);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
            'ip' => $ip,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Consulta MAC y tráfico (download/upload) en MikroTik para el servicio.
     */
    public function herramientasRedMikrotik($servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router', 'cliente'])->findOrFail($servicio_id);
        $router = $servicio->pool?->router;

        if (! $router) {
            return response()->json([
                'success' => false,
                'message' => 'El servicio no tiene router MikroTik asociado (pool).',
            ], 422);
        }

        $result = $mikrotik->consultarClienteRed(
            $router,
            $servicio->ip,
            $servicio->usuario_pppoe
        );

        if ($result['success'] ?? false) {
            try {
                \App\Models\ServicioConexionEvento::registrarPppoeSiCambio(
                    $servicio,
                    (bool) ($result['online'] ?? false),
                    [
                        'usuario_pppoe' => $servicio->usuario_pppoe,
                        'ip' => $servicio->ip,
                        'mac' => $result['mac'] ?? $servicio->mac_address,
                        'router_id' => $router->router_id,
                        'uptime' => $result['uptime'] ?? null,
                        'payload' => [
                            'mac_fuente' => $result['mac_fuente'] ?? null,
                            'trafico_fuente' => $result['trafico_fuente'] ?? null,
                        ],
                    ]
                );

                if (isset($result['antena_signal_dbm']) || isset($result['signal_dbm'])) {
                    \App\Models\ServicioConexionEvento::registrarSenalAntena($servicio, [
                        'router_id' => $router->router_id,
                        'ip' => $servicio->ip,
                        'mac' => $result['mac'] ?? null,
                        'antena_signal_dbm' => $result['antena_signal_dbm'] ?? $result['signal_dbm'] ?? null,
                        'antena_snr_db' => $result['antena_snr_db'] ?? $result['snr_db'] ?? null,
                        'antena_radio_iface' => $result['antena_radio_iface'] ?? null,
                    ]);
                }
            } catch (\Throwable) {
                // No bloquear la consulta si falla el historial
            }
        }

        $cliente = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
            'cliente' => $cliente !== '' ? $cliente : null,
            'ip' => $servicio->ip,
            'usuario_pppoe' => $servicio->usuario_pppoe,
            'mac_sistema' => $servicio->mac_address,
        ], $result['success'] ? 200 : 422);
    }

    /**
     * Flujo fibra: MAC (MikroTik o sistema) → tabla MAC OLT → PON/ONU → desc/RX.
     */
    public function herramientasRedOlt(
        $servicio_id,
        MikroTikService $mikrotik,
        \App\Services\Olt\VsolGponClient $vsol
    ) {
        $servicio = Servicio::with([
            'plan',
            'pool.olt',
            'pool.router.nodo',
            'cajaNapPuertoActivo.cajaNap.salidaPon.olt',
        ])->findOrFail($servicio_id);

        $esFibra = $this->servicioEsFibra($servicio);
        if (! $esFibra) {
            return response()->json([
                'success' => false,
                'message' => 'Este servicio no parece fibra/GPON (nodo sin GPON ni caja NAP). La consulta OLT aplica a planes de fibra.',
                'es_fibra' => false,
            ], 422);
        }

        $mac = null;
        $macFuente = null;
        $router = $servicio->pool?->router;
        if ($router) {
            $mk = $mikrotik->consultarClienteRed($router, $servicio->ip, $servicio->usuario_pppoe);
            if (! empty($mk['mac'])) {
                $mac = $mk['mac'];
                $macFuente = $mk['mac_fuente'] ?? 'mikrotik';
            }
        }
        if (! $mac && filled($servicio->mac_address)) {
            $mac = strtoupper(str_replace('-', ':', (string) $servicio->mac_address));
            $macFuente = 'sistema';
        }

        if (! $mac) {
            return response()->json([
                'success' => false,
                'message' => 'No hay MAC para buscar: consultá primero MAC en MikroTik o cargá MAC en el servicio.',
                'es_fibra' => true,
            ], 422);
        }

        $olts = $this->oltsCandidatosParaServicio($servicio);
        if ($olts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay OLT con credenciales Telnet asociada (caja NAP → salida PON, o OLTs del nodo).',
                'mac' => $mac,
                'mac_fuente' => $macFuente,
                'es_fibra' => true,
            ], 422);
        }

        $ultimoError = null;
        $ultimoRaw = null;
        $ultimoComando = null;
        foreach ($olts as $olt) {
            try {
                @set_time_limit(180);
                $r = $vsol->localizarMacYConsultarOnu($olt, $mac, true);
                if ($r['success'] && ($r['pon_port'] ?? null) !== null && ($r['onu_index'] ?? null) !== null) {
                    try {
                        \App\Models\ServicioConexionEvento::registrarSenalOptica($servicio, [
                            'mac' => $mac,
                            'olt_id' => $olt->olt_id,
                            'pon_port' => $r['pon_port'],
                            'onu_index' => $r['onu_index'],
                            'rx_power_dbm' => $r['rx_power_dbm'] ?? null,
                            'tx_power_dbm' => $r['tx_power_dbm'] ?? null,
                            'estado' => $r['estado'] ?? null,
                            'descripcion' => $r['descripcion'] ?? null,
                            'payload' => [
                                'mac_fuente' => $macFuente,
                                'vlan' => $r['vlan'] ?? null,
                                'comando' => $r['comando'] ?? null,
                            ],
                        ]);
                    } catch (\Throwable) {
                        // No bloquear la consulta si falla el historial
                    }

                    return response()->json([
                        ...$r,
                        'es_fibra' => true,
                        'mac_fuente' => $macFuente,
                        'olt_id' => $olt->olt_id,
                        'olt' => $olt->codigo ?? $olt->ip,
                    ]);
                }
                $ultimoError = $r['message'] ?? 'MAC no encontrada';
                $ultimoRaw = $r['raw_match'] ?? null;
                $ultimoComando = $r['comando'] ?? null;
            } catch (\Throwable $e) {
                $ultimoError = $e->getMessage();
                $ultimoRaw = null;
                $ultimoComando = null;
            }
        }

        return response()->json([
            'success' => false,
            'message' => $ultimoError ?: 'No se pudo localizar la MAC en las OLTs candidatas.',
            'mac' => $mac,
            'mac_fuente' => $macFuente,
            'es_fibra' => true,
            'comando' => $ultimoComando ?? null,
            'raw_match' => isset($ultimoRaw) ? mb_substr((string) $ultimoRaw, 0, 4000) : null,
            'olts_probadas' => $olts->map(fn ($o) => $o->codigo ?? $o->ip)->values()->all(),
        ], 422);
    }

    /**
     * Localiza la ONU por MAC y escribe description = usuario_pppoe (nombre_id del servicio).
     */
    public function herramientasRedOltDesc(
        $servicio_id,
        MikroTikService $mikrotik,
        \App\Services\Olt\VsolGponClient $vsol
    ) {
        $servicio = Servicio::with([
            'cliente',
            'plan',
            'pool.olt',
            'pool.router.nodo',
            'cajaNapPuertoActivo.cajaNap.salidaPon.olt',
        ])->findOrFail($servicio_id);

        if (! $this->servicioEsFibra($servicio)) {
            return response()->json([
                'success' => false,
                'message' => 'Este servicio no parece fibra/GPON.',
            ], 422);
        }

        $desc = $this->descripcionOnuDesdeServicio($servicio, $vsol);
        if ($desc === '') {
            return response()->json([
                'success' => false,
                'message' => 'El servicio no tiene usuario PPPoE ni nombre de cliente para usar como descripción.',
            ], 422);
        }

        $mac = null;
        $macFuente = null;
        $router = $servicio->pool?->router;
        if ($router) {
            $mk = $mikrotik->consultarClienteRed($router, $servicio->ip, $servicio->usuario_pppoe);
            if (! empty($mk['mac'])) {
                $mac = $mk['mac'];
                $macFuente = $mk['mac_fuente'] ?? 'mikrotik';
            }
        }
        if (! $mac && filled($servicio->mac_address)) {
            $mac = strtoupper(str_replace('-', ':', (string) $servicio->mac_address));
            $macFuente = 'sistema';
        }
        if (! $mac) {
            return response()->json([
                'success' => false,
                'message' => 'No hay MAC para localizar la ONU.',
                'descripcion' => $desc,
            ], 422);
        }

        $olts = $this->oltsCandidatosParaServicio($servicio);
        if ($olts->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay OLT candidata con credenciales Telnet.',
                'descripcion' => $desc,
                'mac' => $mac,
            ], 422);
        }

        $ultimoError = null;
        foreach ($olts as $olt) {
            try {
                @set_time_limit(180);
                $loc = $vsol->localizarMacYConsultarOnu($olt, $mac, false);
                if (! ($loc['success'] ?? false)
                    || ($loc['pon_port'] ?? null) === null
                    || ($loc['onu_index'] ?? null) === null) {
                    $ultimoError = $loc['message'] ?? 'MAC no localizada';
                    continue;
                }

                $pon = (int) $loc['pon_port'];
                $onu = (int) $loc['onu_index'];
                $escrito = $vsol->configurarOnuDescripcion($olt, $pon, $onu, $desc);

                if ($escrito['success']) {
                    \App\Models\OltOnu::query()
                        ->where('olt_id', $olt->olt_id)
                        ->where('pon_port', $pon)
                        ->where('onu_index', $onu)
                        ->update(['descripcion' => $escrito['descripcion']]);
                }

                return response()->json([
                    ...$escrito,
                    'mac' => $mac,
                    'mac_fuente' => $macFuente,
                    'olt' => $olt->codigo ?? $olt->ip,
                    'olt_id' => $olt->olt_id,
                    'descripcion_solicitada' => $desc,
                ], $escrito['success'] ? 200 : 422);
            } catch (\Throwable $e) {
                $ultimoError = $e->getMessage();
            }
        }

        return response()->json([
            'success' => false,
            'message' => $ultimoError ?: 'No se pudo escribir la descripción en la OLT.',
            'descripcion' => $desc,
            'mac' => $mac,
            'mac_fuente' => $macFuente,
        ], 422);
    }

    /**
     * Identificador del servicio para desc ONU: usuario_pppoe (NOMBRE_APELLIDO).
     */
    private function descripcionOnuDesdeServicio(Servicio $servicio, \App\Services\Olt\VsolGponClient $vsol): string
    {
        $pppoe = trim((string) ($servicio->usuario_pppoe ?? ''));
        if ($pppoe !== '') {
            return $vsol->sanitizarDescripcionOnu($pppoe);
        }

        $cliente = $servicio->cliente;
        $nombre = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
        if ($nombre !== '') {
            return $vsol->sanitizarDescripcionOnu($nombre);
        }

        return $vsol->sanitizarDescripcionOnu('SERVICIO_'.$servicio->servicio_id);
    }

    private function servicioEsFibra(Servicio $servicio): bool
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

    private function servicioEsAntena(Servicio $servicio): bool
    {
        if ($this->servicioEsFibra($servicio)) {
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

        return $servicio->pool?->router !== null;
    }

    /**
     * Prioridad: caja NAP → OLT del pool → OLTs del nodo (solo si el pool no tiene OLT).
     *
     * @return \Illuminate\Support\Collection<int, \App\Models\Olt>
     */
    private function oltsCandidatosParaServicio(Servicio $servicio)
    {
        $olts = collect();

        $oltCaja = $servicio->cajaNapPuertoActivo?->cajaNap?->salidaPon?->olt;
        if ($oltCaja && $oltCaja->tieneCredencialesGestion()) {
            $olts->push($oltCaja);
        }

        $oltPool = $servicio->pool?->olt;
        if ($oltPool && $oltPool->tieneCredencialesGestion() && ! $olts->contains('olt_id', $oltPool->olt_id)) {
            $olts->push($oltPool);
        }

        // Si el pool ya indica OLT, no mezclar con todas las del nodo del router
        // (caso: un router atiende clientes de otro nodo diferenciados por pool).
        if ($servicio->pool?->olt_id) {
            return $olts->values();
        }

        $nodoId = $servicio->pool?->router?->nodo_id
            ?? $servicio->cajaNapPuertoActivo?->cajaNap?->nodo_id;

        if ($nodoId) {
            \App\Models\Olt::query()
                ->where('nodo_id', $nodoId)
                ->orderBy('codigo')
                ->get()
                ->each(function ($olt) use ($olts) {
                    if ($olt->tieneCredencialesGestion() && ! $olts->contains('olt_id', $olt->olt_id)) {
                        $olts->push($olt);
                    }
                });
        }

        return $olts->values();
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
