<?php

namespace App\Http\Controllers;

use App\Models\CajaNapPuertoActivo;
use App\Models\Cliente;
use App\Models\MikrotikOperacionPendiente;
use App\Models\Nodo;
use App\Models\Plan;
use App\Models\PoolIpAsignada;
use App\Models\RouterIpPool;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\TipoTecnologia;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use App\Services\NetworkPingService;
use App\Services\PedidoNodoOpcionesService;
use App\Support\CpeInventario;
use App\Support\HerramientasRedPayload;
use App\Support\ListaClienteServicioViewData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if (! $poolId) {
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
        $clienteId = $request->get('cliente_id');
        $pppoeSugeridoUsuario = null;
        $pppoeSugeridoPassword = null;
        $pppoeBaseCliente = null;
        $pppoeOcupados = [];
        $pppoeExistentes = collect();
        $esServicioAdicional = false;

        if ($clienteId) {
            $clienteForm = Cliente::with('servicios')->find($clienteId);
            $serviciosVigentes = $clienteForm?->servicios
                ->filter(fn (Servicio $s) => $s->estado !== Servicio::ESTADO_CANCELADO)
                ->values() ?? collect();
            $esServicioAdicional = $serviciosVigentes->isNotEmpty();
            $pppoeExistentes = $serviciosVigentes
                ->filter(fn (Servicio $s) => filled($s->usuario_pppoe))
                ->values();
            $pppoeOcupados = Servicio::query()
                ->whereNotNull('usuario_pppoe')
                ->where('usuario_pppoe', '!=', '')
                ->pluck('usuario_pppoe')
                ->all();
            $pppoeBaseCliente = Servicio::baseUsuarioPppoeDesdeCliente($clienteForm, (int) $clienteId);
            if ($esServicioAdicional) {
                $pppoeSugeridoUsuario = Servicio::siguienteUsuarioPppoeLibre($pppoeBaseCliente, $pppoeOcupados);
                $pppoeSugeridoPassword = Servicio::generarPasswordPppoe(
                    $pppoeExistentes->pluck('password_pppoe')->all()
                );
            }
        }

        return view('servicios.create', array_merge(
            compact(
                'clientes',
                'clienteId',
                'pppoeSugeridoUsuario',
                'pppoeSugeridoPassword',
                'pppoeBaseCliente',
                'pppoeOcupados',
                'pppoeExistentes',
                'esServicioAdicional'
            ),
            $this->catalogosFormularioServicio()
        ));
    }

    public function store(Request $request)
    {
        $usuarioIngresado = Servicio::normalizarFragmentoUsuarioPppoe((string) $request->input('usuario_pppoe', ''));
        $request->merge([
            'usuario_pppoe' => $usuarioIngresado !== '' ? $usuarioIngresado : null,
            'password_pppoe' => trim((string) $request->input('password_pppoe', '')) ?: null,
        ]);

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'tecnologia_id' => ['required', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'alias' => ['nullable', 'string', 'max:80'],
            'ip' => ['nullable', 'string', 'max:15', function ($attribute, $value, $fail) {
                if ($value && str_ends_with(trim($value), '.255')) {
                    $fail('La IP no puede terminar en .255 (reservada para broadcast).');
                }
            }],
            'usuario_pppoe' => ['nullable', 'string', 'max:100', Rule::unique('servicios', 'usuario_pppoe')],
            'password_pppoe' => ['nullable', 'string', 'max:20'],
            'fecha_instalacion' => ['nullable', 'date'],
            'estado' => ['nullable', 'string', 'in:A,S,C'],
            'mac_address' => ['nullable', 'string', 'max:20'],
            'tr069_serial' => ['nullable', 'string', 'max:64'],
            'tr069_product_class' => ['nullable', 'string', 'max:64'],
            'cpe_acceso' => ['nullable', 'string', 'in:ssh,acs'],
            'cpe_onu' => ['nullable', 'string', 'max:64'],
            'cpe_onu_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_onu,'.CpeInventario::OTRO],
            'cpe_router' => ['nullable', 'string', 'max:64'],
            'cpe_router_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_router,'.CpeInventario::OTRO],
            'cpe_antena' => ['nullable', 'string', 'max:64'],
            'cpe_antena_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_antena,'.CpeInventario::OTRO],
            'cpe_notas' => ['nullable', 'string', 'max:120'],
            'acuerdo_tipo' => ['nullable', 'string', 'in:ninguno,libre,meses'],
            'acuerdo_meses' => ['nullable', 'integer', 'min:1', 'max:24'],
            'acuerdo_desde' => ['nullable', 'date'],
        ], [
            'usuario_pppoe.unique' => 'Ese usuario PPPoE ya está en uso. Elegí uno distinto al del otro servicio.',
        ]);
        $validated = $this->normalizarCpeInventario($validated);
        $validated = $this->validarPlanDeTecnologia($validated);
        $validated['alias'] = trim((string) ($validated['alias'] ?? '')) ?: null;

        $validated['estado'] = $validated['estado'] ?? 'P';
        $validated['acuerdo_tipo'] = $validated['acuerdo_tipo'] ?? 'ninguno';
        if ($validated['acuerdo_tipo'] !== 'meses') {
            $validated['acuerdo_meses'] = null;
            $validated['acuerdo_desde'] = null;
        } elseif (empty($validated['acuerdo_desde'])) {
            $validated['acuerdo_desde'] = now()->toDateString();
        }

        // Generar usuario y contraseña PPPoE distintos si vienen vacíos (crear desde cliente)
        $cliente = Cliente::with('servicios')->find($validated['cliente_id']);
        if (empty(trim((string) ($validated['usuario_pppoe'] ?? '')))) {
            $validated['usuario_pppoe'] = Servicio::usuarioPppoeDesdeClienteYAlias(
                $cliente,
                $validated['alias'] ?? null,
                null,
                (int) $validated['cliente_id']
            );
        }
        if (empty(trim((string) ($validated['password_pppoe'] ?? '')))) {
            $evitar = $cliente?->servicios->pluck('password_pppoe')->all() ?? [];
            $validated['password_pppoe'] = Servicio::generarPasswordPppoe($evitar);
        }

        $yaTeniaOtros = Servicio::where('cliente_id', $validated['cliente_id'])->exists();

        Servicio::create($validated);

        if (! empty($validated['ip'])) {
            PoolIpAsignada::where('pool_id', $validated['pool_id'])
                ->where('ip', $validated['ip'])
                ->update(['estado' => 'asignada']);
        }

        $mensaje = 'Servicio creado correctamente.';
        if ($yaTeniaOtros) {
            $mensaje .= ' Usá «Finalizar instalación» en la pestaña Servicio para generar la factura prorrateada.';
        }

        return redirect()
            ->route('clientes.detalle', ['cliente' => $validated['cliente_id'], 'tab' => 'servicio'])
            ->with('success', $mensaje);
    }

    public function edit($servicio_id)
    {
        $servicio = Servicio::with(['cliente', 'plan', 'pool', 'servicioHotspot.router'])
            ->findOrFail($servicio_id);

        $clientes = Cliente::whereIn('estado', ['activo', 'inactivo', 'suspendido'])->orderBy('nombre')->get();

        return view('servicios.edit', array_merge(
            compact('servicio', 'clientes'),
            $this->catalogosFormularioServicio($servicio)
        ));
    }

    public function update(Request $request, $servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router', 'plan.perfilPppoe', 'cliente'])->findOrFail($servicio_id);

        $planIdAnterior = (int) $servicio->plan_id;
        $planAnterior = $servicio->plan ?? Plan::find($planIdAnterior);

        $validated = $request->validate([
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'tecnologia_id' => ['required', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'alias' => ['nullable', 'string', 'max:80'],
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
            'tr069_serial' => ['nullable', 'string', 'max:64'],
            'tr069_product_class' => ['nullable', 'string', 'max:64'],
            'cpe_acceso' => ['nullable', 'string', 'in:ssh,acs'],
            'cpe_onu' => ['nullable', 'string', 'max:64'],
            'cpe_onu_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_onu,'.CpeInventario::OTRO],
            'cpe_router' => ['nullable', 'string', 'max:64'],
            'cpe_router_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_router,'.CpeInventario::OTRO],
            'cpe_antena' => ['nullable', 'string', 'max:64'],
            'cpe_antena_otro' => ['nullable', 'string', 'max:64', 'required_if:cpe_antena,'.CpeInventario::OTRO],
            'cpe_notas' => ['nullable', 'string', 'max:120'],
            'acuerdo_tipo' => ['nullable', 'string', 'in:ninguno,libre,meses'],
            'acuerdo_meses' => ['nullable', 'integer', 'min:1', 'max:24'],
            'acuerdo_desde' => ['nullable', 'date'],
        ]);
        $validated = $this->normalizarCpeInventario($validated);
        $validated = $this->validarPlanDeTecnologia($validated);
        $validated['alias'] = trim((string) ($validated['alias'] ?? '')) ?: null;
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

        if (! empty($validated['ip'])) {
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
                $mensaje .= ' No se pudo quitar el usuario PPPoE anterior en MikroTik: '.($quitar['error'] ?? '').'.';
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
                $mensaje .= ' No se pudo sincronizar con MikroTik: '.($sync['error'] ?? 'error desconocido').'. Podés reintentar con «Sincronizar PPPoE».';
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
                    ->with('warning', 'MikroTik: no se pudo habilitar PPPoE — '.($r['error'] ?? 'error').'. Quedó registrado para reintento.');
            }
        }

        return redirect()->back()->with('success', 'Servicio reactivado correctamente.');
    }

    /**
     * Cierra la instalación de un servicio adicional (sin pedido): activa, sincroniza PPPoE
     * y genera la factura interna prorrateada con la misma regla de calendario que los pedidos.
     */
    public function finalizarInstalacion(Request $request, $servicio_id, FacturacionService $facturacion, MikroTikService $mikrotik)
    {
        if (! $request->user()?->tienePermiso('facturas.crear')) {
            abort(403, 'No tenés permiso para generar la factura de instalación.');
        }

        $servicio = Servicio::with(['plan', 'cliente', 'pool.router', 'plan.perfilPppoe'])->findOrFail($servicio_id);

        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()->back()->with('error', 'No se puede finalizar un servicio cancelado.');
        }

        if (! $servicio->esCandidatoFinalizarInstalacion()) {
            return redirect()->back()->with('error', 'Este servicio se finaliza desde el pedido de instalación, o ya no corresponde cerrar la instalación este mes.');
        }

        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();
        $omitirFacturaPorCalendario = ! FacturacionService::puedeEmitirFacturaPorInstalacion();
        $yaFacturado = $facturacion->servicioIdsConFacturaEnPeriodo(
            [(int) $servicio->servicio_id],
            $inicioMes,
            $finMes
        ) !== [];

        $facturaInternaId = null;
        $omitirFacturaPorAcuerdo = false;

        try {
            DB::transaction(function () use ($request, $servicio, $facturacion, $inicioMes, $finMes, $omitirFacturaPorCalendario, $yaFacturado, &$facturaInternaId, &$omitirFacturaPorAcuerdo) {
                if (! $servicio->fecha_instalacion) {
                    $servicio->fecha_instalacion = now()->toDateString();
                }
                $servicio->estado = Servicio::ESTADO_ACTIVO;
                $servicio->fecha_suspension = null;
                $servicio->motivo_suspension = null;
                $servicio->save();

                if ($servicio->cliente && strtolower((string) $servicio->cliente->estado) !== 'activo') {
                    $servicio->cliente->update(['estado' => 'activo']);
                }

                $omitirFacturaPorAcuerdo = $servicio->acuerdoAplicaEnPeriodo($inicioMes, $finMes);
                if ($omitirFacturaPorCalendario || $yaFacturado || $omitirFacturaPorAcuerdo) {
                    return;
                }

                $resultado = $facturacion->generarFacturaInternaDesdeServicios(
                    [(int) $servicio->servicio_id],
                    $inicioMes,
                    $finMes,
                    $request->user()?->usuario_id,
                    sprintf('Servicio #%s — factura prorrateada por instalación.', $servicio->servicio_id)
                );
                $facturaInternaId = $resultado['primera']?->id;
            });
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', 'No se pudo generar la factura interna: '.$e->getMessage());
        }

        $servicio->refresh();
        $servicio->load(['pool.router', 'plan.perfilPppoe', 'cliente']);

        $warning = null;
        if ($servicio->usuario_pppoe && $servicio->pool?->router) {
            $syncResult = $mikrotik->syncPppoeServicio($servicio);
            if (! $syncResult['success']) {
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                    ['servicio_id' => $servicio->servicio_id],
                    $syncResult['error'] ?? 'Error',
                    'servicios.finalizar-instalacion'
                );
                $warning = 'MikroTik: no se pudo sincronizar PPPoE — '.($syncResult['error'] ?? 'error').'. Quedó registrado para reintento.';
            }
        }

        $mensaje = 'Instalación finalizada. El servicio quedó activo.';
        if ($facturaInternaId) {
            $mensaje .= ' Se generó la factura interna prorrateada del mes.';
        } elseif ($yaFacturado) {
            $mensaje .= ' El servicio ya tenía factura en el período actual.';
        } elseif ($omitirFacturaPorAcuerdo) {
            $mensaje .= ' No se generó factura interna: el servicio tiene acuerdo de no facturación en este período.';
        } elseif ($omitirFacturaPorCalendario) {
            $mensaje .= ' No se generó factura interna: solo se emite desde el día 7 hasta fin de mes.';
        }

        $redirect = redirect()
            ->route('clientes.detalle', ['cliente' => $servicio->cliente_id, 'tab' => 'servicio'])
            ->with('success', $mensaje);

        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
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
                    ->with('warning', 'MikroTik: no se pudo deshabilitar PPPoE — '.($r['error'] ?? 'error').'. Quedó registrado para reintento.');
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

        $servicio = Servicio::with(HerramientasRedPayload::relaciones())->findOrFail($servicio_id);

        if (! $this->servicioEsAntena($servicio)) {
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

        $servicio = Servicio::with(HerramientasRedPayload::relaciones())->findOrFail($servicio_id);

        if (! $this->servicioEsAntena($servicio)) {
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
     * Resumen TR-069 del CPE vía GenieACS (último Inform, modelo, WAN, SSID).
     */
    public function herramientasRedTr069($servicio_id, \App\Services\GenieAcs\GenieAcsService $acs)
    {
        @set_time_limit(45);
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $result = $acs->resumen($servicio);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Hosts LAN reportados por el CPE (TR-069).
     */
    public function herramientasRedTr069Hosts($servicio_id, \App\Services\GenieAcs\GenieAcsService $acs)
    {
        @set_time_limit(45);
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $result = $acs->hosts($servicio);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function herramientasRedTr069Reboot($servicio_id, \App\Services\GenieAcs\GenieAcsService $acs)
    {
        @set_time_limit(45);
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $result = $acs->reboot($servicio);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function herramientasRedTr069Refresh($servicio_id, \App\Services\GenieAcs\GenieAcsService $acs)
    {
        @set_time_limit(45);
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $result = $acs->refresh($servicio);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    public function herramientasRedTr069Password($servicio_id, Request $request, \App\Services\GenieAcs\GenieAcsService $acs)
    {
        @set_time_limit(45);
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $tipo = $request->input('tipo', 'wifi');
        if (! in_array($tipo, ['wifi', 'admin'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Tipo inválido (wifi o admin).',
                'servicio_id' => $servicio->servicio_id,
            ], 422);
        }
        $min = $tipo === 'admin' ? 4 : 8;
        $max = $tipo === 'admin' ? 64 : 63;
        $password = (string) $request->input('password', '');
        if (strlen($password) < $min || strlen($password) > $max) {
            return response()->json([
                'success' => false,
                'message' => $tipo === 'wifi'
                    ? 'La clave WiFi debe tener entre 8 y 63 caracteres.'
                    : 'La clave del router debe tener entre 4 y 64 caracteres.',
                'servicio_id' => $servicio->servicio_id,
            ], 422);
        }
        $wifiId = $request->input('wifi_id');
        $wifiId = is_string($wifiId) && $wifiId !== '' ? $wifiId : 'all';
        $result = $acs->setPassword($servicio, $tipo, $password, $wifiId);

        return response()->json([
            ...$result,
            'servicio_id' => $servicio->servicio_id,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

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
        return HerramientasRedPayload::esFibra($servicio);
    }

    private function servicioEsAntena(Servicio $servicio): bool
    {
        return HerramientasRedPayload::esAntena($servicio);
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

            return redirect()->back()->with('error', 'Error de conexión al router: '.$e->getMessage());
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
        if (! empty($validated['plan_id'])) {
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
                $mensaje .= ' No se pudo eliminar el usuario PPPoE en el router anterior: '.($resultadoEliminarOrigen['error'] ?? 'error desconocido').'. Revisá el router o eliminá el secreto a mano.';
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
                $mensaje .= ' Migración OK pero sincronización MikroTik en el nuevo nodo falló: '.($syncResult['error'] ?? 'error desconocido').'. Podés sincronizar manualmente desde el servicio.';
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

    /**
     * Formulario para cambiar tecnología (antena ↔ fibra) con plan equivalente por precio.
     */
    public function cambiarTecnologiaForm($servicio_id)
    {
        $servicio = Servicio::with(['cliente', 'plan.tipoTecnologia', 'pool.router.nodo'])->findOrFail($servicio_id);
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()
                ->route('clientes.detalle', ['cliente' => $servicio->cliente_id, 'tab' => 'servicio'])
                ->with('error', 'No se puede cambiar la tecnología de un servicio cancelado.');
        }

        $catalogos = $this->catalogosFormularioServicio($servicio);
        $tecnologiaActualId = $servicio->plan?->tecnologia_id ? (int) $servicio->plan->tecnologia_id : null;
        $kindActual = $tecnologiaActualId
            ? ($catalogos['tecnologiaKinds'][(string) $tecnologiaActualId] ?? $this->kindDeTecnologia($tecnologiaActualId))
            : 'otro';

        $tecnologiaDestinoDefault = $catalogos['tecnologias']->first(function (TipoTecnologia $t) use ($catalogos, $kindActual, $tecnologiaActualId) {
            $kind = $catalogos['tecnologiaKinds'][(string) $t->tecnologia_id] ?? 'otro';
            if ($kindActual === 'wireless') {
                return $kind === 'gpon';
            }
            if ($kindActual === 'gpon') {
                return $kind === 'wireless';
            }

            return (int) $t->tecnologia_id !== (int) $tecnologiaActualId;
        });

        $nodoActual = $servicio->pool?->router?->nodo;
        $nodos = Nodo::orderBy('descripcion')->get();

        $planesPayload = $catalogos['planes']->map(fn (Plan $p) => [
            'plan_id' => (int) $p->plan_id,
            'tecnologia_id' => (int) $p->tecnologia_id,
            'nombre' => $p->nombre,
            'velocidad' => $p->velocidad,
            'precio' => (float) $p->precio,
        ])->values()->all();

        $poolsPayload = $catalogos['pools']->map(function (RouterIpPool $p) {
            [$gpon, $wireless] = $this->flagsPoolTecnologia($p);
            $nodo = $p->router?->nodo;
            $desc = trim((string) ($p->descripcion ?? ''));
            $rango = trim((string) ($p->ip_range ?? ''));
            $router = $p->router?->nombre ?? '—';
            $nodoNom = $nodo?->descripcion ?? 'Nodo';
            $detalle = $desc !== '' ? $desc : ($rango !== '' ? $rango : '#'.$p->pool_id);

            return [
                'pool_id' => (int) $p->pool_id,
                'nodo_id' => $nodo?->nodo_id ? (int) $nodo->nodo_id : null,
                'label' => $nodoNom.' — '.$detalle.' ('.$router.')',
                'gpon' => $gpon,
                'wireless' => $wireless,
            ];
        })->values()->all();

        $nodosPayload = $nodos->map(fn (Nodo $n) => [
            'nodo_id' => (int) $n->nodo_id,
            'descripcion' => $n->descripcion,
            'gpon' => $n->manejaGpon(),
            'wireless' => $n->manejaWireless(),
        ])->values()->all();

        $config = [
            'tecnologiaActualId' => $tecnologiaActualId,
            'kindActual' => $kindActual,
            'precioActual' => (float) ($servicio->plan->precio ?? 0),
            'planActualId' => (int) $servicio->plan_id,
            'nodoActualId' => $nodoActual?->nodo_id ? (int) $nodoActual->nodo_id : null,
            'nodoActualGpon' => (bool) ($nodoActual?->manejaGpon()),
            'nodoActualWireless' => (bool) ($nodoActual?->manejaWireless()),
            'poolActualId' => $servicio->pool_id ? (int) $servicio->pool_id : null,
            'ipActual' => $servicio->ip,
            'tecnologiaDestinoDefaultId' => $tecnologiaDestinoDefault?->tecnologia_id
                ? (int) $tecnologiaDestinoDefault->tecnologia_id
                : null,
            'tecnologiaKinds' => $catalogos['tecnologiaKinds'],
            'planes' => $planesPayload,
            'pools' => $poolsPayload,
            'nodos' => $nodosPayload,
            'ipsUrl' => route('servicios.ips-disponibles'),
            'old' => [
                'tecnologia_id' => old('tecnologia_id'),
                'plan_id' => old('plan_id'),
                'mantener_nodo' => old('mantener_nodo'),
                'nodo_id' => old('nodo_id'),
                'pool_id' => old('pool_id'),
                'ip' => old('ip'),
            ],
        ];

        return view('servicios.cambiar-tecnologia', array_merge($catalogos, compact(
            'servicio',
            'tecnologiaActualId',
            'kindActual',
            'tecnologiaDestinoDefault',
            'nodoActual',
            'config'
        )));
    }

    /**
     * Procesar cambio de tecnología, plan equivalente y nodo/pool.
     */
    public function cambiarTecnologiaStore(Request $request, $servicio_id, MikroTikService $mikrotik)
    {
        $servicio = Servicio::with(['pool.router.nodo', 'plan.perfilPppoe', 'plan.tipoTecnologia', 'cliente'])->findOrFail($servicio_id);
        if ($servicio->estado === Servicio::ESTADO_CANCELADO) {
            return redirect()
                ->route('clientes.detalle', ['cliente' => $servicio->cliente_id, 'tab' => 'servicio'])
                ->with('error', 'No se puede cambiar la tecnología de un servicio cancelado.');
        }

        $planIdAnterior = (int) $servicio->plan_id;
        $planAnterior = $servicio->plan;
        $tecnologiaActualId = $planAnterior?->tecnologia_id ? (int) $planAnterior->tecnologia_id : null;

        $validated = $request->validate([
            'tecnologia_id' => ['required', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'mantener_nodo' => ['required', 'in:0,1'],
            'nodo_id' => ['nullable', 'integer', 'exists:nodos,nodo_id'],
            'pool_id' => ['required', 'integer', 'exists:router_ip_pools,pool_id'],
            'ip' => ['required', 'string', 'max:15', function ($attribute, $value, $fail) {
                if (str_ends_with(trim((string) $value), '.255')) {
                    $fail('La IP no puede terminar en .255 (reservada para broadcast).');
                }
            }],
        ]);

        if ($tecnologiaActualId && (int) $validated['tecnologia_id'] === $tecnologiaActualId) {
            throw ValidationException::withMessages([
                'tecnologia_id' => 'Elegí una tecnología distinta a la actual.',
            ]);
        }

        $kindDestino = $this->kindDeTecnologia((int) $validated['tecnologia_id']);
        $mantenerNodo = (string) $validated['mantener_nodo'] === '1';
        $validated = $this->validarPlanDeTecnologia($validated);

        $poolDestino = RouterIpPool::with('router.nodo')->findOrFail($validated['pool_id']);
        $nodoDestino = $poolDestino->router?->nodo;
        $nodoActual = $servicio->pool?->router?->nodo;
        $nodoActualId = $nodoActual?->nodo_id ? (int) $nodoActual->nodo_id : null;

        if (! $this->poolCompatibleConKind($poolDestino, $kindDestino)) {
            throw ValidationException::withMessages([
                'pool_id' => 'El pool no corresponde a la tecnología seleccionada.',
            ]);
        }

        if ($mantenerNodo) {
            if (! $nodoActualId) {
                throw ValidationException::withMessages([
                    'mantener_nodo' => 'Este servicio no tiene nodo actual; elegí otro nodo.',
                ]);
            }
            if ((int) ($nodoDestino?->nodo_id) !== $nodoActualId) {
                throw ValidationException::withMessages([
                    'pool_id' => 'Para mantener el nodo, el pool debe ser del nodo actual.',
                ]);
            }
            if (! $this->nodoCompatibleConKind($nodoActual, $kindDestino)) {
                throw ValidationException::withMessages([
                    'mantener_nodo' => 'El nodo actual no soporta la tecnología destino. Elegí otro nodo.',
                ]);
            }
        } else {
            $nodoIdPedido = (int) ($validated['nodo_id'] ?? 0);
            if ($nodoIdPedido < 1) {
                throw ValidationException::withMessages([
                    'nodo_id' => 'Seleccioná el nodo destino.',
                ]);
            }
            if ($nodoActualId && $nodoIdPedido === $nodoActualId) {
                throw ValidationException::withMessages([
                    'nodo_id' => 'Elegí un nodo distinto, o marcá «mantener nodo».',
                ]);
            }
            if ((int) ($nodoDestino?->nodo_id) !== $nodoIdPedido) {
                throw ValidationException::withMessages([
                    'pool_id' => 'El pool no pertenece al nodo seleccionado.',
                ]);
            }
            if ($nodoDestino && ! $this->nodoCompatibleConKind($nodoDestino, $kindDestino)) {
                throw ValidationException::withMessages([
                    'nodo_id' => 'El nodo seleccionado no soporta esa tecnología.',
                ]);
            }
        }

        $ipNueva = trim((string) $validated['ip']);
        $mismoPool = (int) $servicio->pool_id === (int) $poolDestino->pool_id;
        $mismaIp = $mismoPool && $ipNueva === trim((string) ($servicio->ip ?? ''));
        if (! $mismaIp) {
            $ipDisponible = PoolIpAsignada::where('pool_id', $poolDestino->pool_id)
                ->where('ip', $ipNueva)
                ->where('estado', 'disponible')
                ->exists();
            if (! $ipDisponible) {
                throw ValidationException::withMessages([
                    'ip' => 'La IP seleccionada no está disponible en el pool destino.',
                ]);
            }
        }

        $routerOrigen = $servicio->pool?->router;
        $usuarioPppoe = $servicio->usuario_pppoe ? trim($servicio->usuario_pppoe) : '';
        $poolOldId = (int) $servicio->pool_id;
        $ipAnterior = $servicio->ip;

        $resultadoEliminarOrigen = null;
        $routerDestinoId = $poolDestino->router?->router_id;
        $cambiaRouter = $routerOrigen && $routerDestinoId && (int) $routerOrigen->router_id !== (int) $routerDestinoId;
        if ($cambiaRouter && $usuarioPppoe !== '') {
            $resultadoEliminarOrigen = $mikrotik->removePppoeSecretByName($routerOrigen, $usuarioPppoe);
        }

        if ($ipAnterior && ! $mismaIp) {
            PoolIpAsignada::where('pool_id', $poolOldId)->where('ip', $ipAnterior)->update(['estado' => 'disponible']);
            PoolIpAsignada::where('pool_id', $poolDestino->pool_id)
                ->where('ip', $ipNueva)
                ->update(['estado' => 'asignada']);
        }

        $updateData = [
            'plan_id' => (int) $validated['plan_id'],
            'pool_id' => (int) $poolDestino->pool_id,
            'ip' => $ipNueva,
        ];
        if ($kindDestino === 'gpon') {
            $updateData['cpe_antena'] = null;
        } elseif ($kindDestino === 'wireless') {
            $updateData['cpe_onu'] = null;
        }
        if ($cambiaRouter) {
            $updateData['pppoe_synced'] = null;
            $updateData['pppoe_status'] = null;
        }

        $servicio->update($updateData);
        $servicio->refresh();
        $servicio->load(['pool.router', 'plan.perfilPppoe', 'cliente']);

        $mensaje = 'Tecnología actualizada correctamente.';
        if ($resultadoEliminarOrigen !== null) {
            if ($resultadoEliminarOrigen['success']) {
                if (! empty($resultadoEliminarOrigen['removed'])) {
                    $mensaje .= ' Usuario PPPoE eliminado del MikroTik del nodo anterior.';
                }
            } else {
                $mensaje .= ' No se pudo eliminar el usuario PPPoE en el router anterior: '.($resultadoEliminarOrigen['error'] ?? 'error desconocido').'.';
                if ($routerOrigen && $usuarioPppoe !== '') {
                    MikrotikOperacionPendiente::registrarSiFallo(
                        MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET,
                        ['router_id' => $routerOrigen->router_id, 'usuario_pppoe' => $usuarioPppoe],
                        $resultadoEliminarOrigen['error'] ?? 'Error',
                        'servicios.cambiar-tecnologia'
                    );
                }
            }
        }

        if ($usuarioPppoe !== '' && $servicio->pool?->router && $servicio->estaActivo()) {
            $syncResult = $mikrotik->syncPppoeServicio($servicio);
            if ($syncResult['success']) {
                $mensaje .= ' Sincronizado con MikroTik.';
            } else {
                $mensaje .= ' No se pudo sincronizar con MikroTik: '.($syncResult['error'] ?? 'error desconocido').'. Podés reintentar con «Sincronizar PPPoE».';
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                    ['servicio_id' => $servicio->servicio_id],
                    $syncResult['error'] ?? 'Error',
                    'servicios.cambiar-tecnologia'
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
                \Illuminate\Support\Facades\Log::warning('Cambio de tecnología: factura/ticket: '.$e->getMessage(), [
                    'servicio_id' => $servicio->servicio_id,
                ]);
                $mensaje .= ' No se pudo completar el registro automático de factura/ticket por cambio de plan; revisar manualmente.';
            }
        }

        return redirect()
            ->route('clientes.detalle', ['cliente' => $servicio->cliente_id, 'tab' => 'servicio'])
            ->with('success', $mensaje);
    }

    /**
     * @return array{planes: \Illuminate\Support\Collection, pools: \Illuminate\Support\Collection, tecnologias: \Illuminate\Support\Collection, tecnologiaKinds: array<string, string>}
     */
    private function catalogosFormularioServicio(?Servicio $servicio = null): array
    {
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        if ($servicio?->plan && ! $planes->contains(fn (Plan $p) => (int) $p->plan_id === (int) $servicio->plan_id)) {
            $planes = $planes->prepend($servicio->plan);
        }

        $tecnologias = TipoTecnologia::orderBy('descripcion')->get();
        $tecnologiaKinds = [];
        foreach ($tecnologias as $t) {
            $id = (string) $t->tecnologia_id;
            if (PedidoNodoOpcionesService::descripcionEsGpon($t->descripcion)) {
                $tecnologiaKinds[$id] = 'gpon';
            } elseif (PedidoNodoOpcionesService::descripcionEsWireless($t->descripcion)) {
                $tecnologiaKinds[$id] = 'wireless';
            } else {
                $tecnologiaKinds[$id] = 'otro';
            }
        }

        $pools = RouterIpPool::with(['router.nodo', 'olt'])->where('activo', true)->orderBy('pool_id')->get();

        return compact('planes', 'pools', 'tecnologias', 'tecnologiaKinds');
    }

    private function validarPlanDeTecnologia(array $validated): array
    {
        $plan = Plan::find($validated['plan_id'] ?? null);
        if (! $plan || (int) $plan->tecnologia_id !== (int) $validated['tecnologia_id']) {
            throw ValidationException::withMessages([
                'plan_id' => 'El plan no corresponde a la tecnología seleccionada.',
            ]);
        }

        unset($validated['tecnologia_id']);

        return $validated;
    }

    private function normalizarCpeInventario(array $validated): array
    {
        $kind = $this->kindDeTecnologia(isset($validated['tecnologia_id']) ? (int) $validated['tecnologia_id'] : null);

        $validated['cpe_onu'] = CpeInventario::resolverSeleccion(
            'onu',
            $validated['cpe_onu'] ?? null,
            $validated['cpe_onu_otro'] ?? null
        );
        $validated['cpe_router'] = CpeInventario::resolverSeleccion(
            'router',
            $validated['cpe_router'] ?? null,
            $validated['cpe_router_otro'] ?? null
        );
        $validated['cpe_antena'] = CpeInventario::resolverSeleccion(
            'antena',
            $validated['cpe_antena'] ?? null,
            $validated['cpe_antena_otro'] ?? null
        );

        if ($kind === 'gpon') {
            $validated['cpe_antena'] = null;
        } elseif ($kind === 'wireless') {
            $validated['cpe_onu'] = null;
        }

        unset($validated['cpe_onu_otro'], $validated['cpe_router_otro'], $validated['cpe_antena_otro']);

        return $validated;
    }

    private function kindDeTecnologia(?int $tecnologiaId): string
    {
        if (! $tecnologiaId) {
            return 'otro';
        }
        $tipo = TipoTecnologia::find($tecnologiaId);
        if ($tipo && PedidoNodoOpcionesService::descripcionEsGpon($tipo->descripcion)) {
            return 'gpon';
        }
        if ($tipo && PedidoNodoOpcionesService::descripcionEsWireless($tipo->descripcion)) {
            return 'wireless';
        }

        return 'otro';
    }

    /**
     * @return array{0: bool, 1: bool} [gpon, wireless]
     */
    private function flagsPoolTecnologia(RouterIpPool $pool): array
    {
        $nodo = $pool->router?->nodo;
        $gpon = (bool) $pool->olt_id || ($nodo && $nodo->manejaGpon());
        $wireless = $nodo && $nodo->manejaWireless();
        if (! $gpon && ! $wireless) {
            return [true, true];
        }

        return [$gpon, $wireless];
    }

    private function poolCompatibleConKind(RouterIpPool $pool, string $kind): bool
    {
        [$gpon, $wireless] = $this->flagsPoolTecnologia($pool);
        if ($kind === 'gpon') {
            return $gpon;
        }
        if ($kind === 'wireless') {
            return $wireless;
        }

        return true;
    }

    private function nodoCompatibleConKind(?Nodo $nodo, string $kind): bool
    {
        if (! $nodo) {
            return false;
        }
        if ($kind === 'gpon') {
            return $nodo->manejaGpon();
        }
        if ($kind === 'wireless') {
            return $nodo->manejaWireless();
        }

        return true;
    }
}
