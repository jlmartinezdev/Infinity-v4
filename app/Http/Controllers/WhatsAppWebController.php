<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Helpers\TelefonoParaguayHelper;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\EstadoPedido;
use App\Models\EstadoPedidoDetalle;
use App\Models\FacturaInterna;
use App\Models\Pedido;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\TicketAsunto;
use App\Models\User;
use App\Models\WhatsappAsunto;
use App\Models\WhatsappContacto;
use App\Models\WhatsappMensaje;
use App\Services\FacturacionService;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WhatsAppWebController extends Controller
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
    ) {}

    public function index(): View
    {
        $ultimos = WhatsappMensaje::query()
            ->latest('id')
            ->limit(8)
            ->get();

        $desdeFallidos = now()->subDays(7);
        $fallidosQuery = WhatsappMensaje::query()
            ->where('direccion', WhatsappMensaje::DIRECCION_SALIDA)
            ->where('estado', WhatsappMensaje::ESTADO_FALLIDO)
            ->where('created_at', '>=', $desdeFallidos);

        $conteos = [
            'hoy' => WhatsappMensaje::query()->whereDate('created_at', today())->count(),
            'salida' => WhatsappMensaje::query()->where('direccion', 'salida')->whereDate('created_at', today())->count(),
            'entrada' => WhatsappMensaje::query()->where('direccion', 'entrada')->whereDate('created_at', today())->count(),
            'fallidos' => (clone $fallidosQuery)->count(),
        ];

        $mensajesFallidos = (clone $fallidosQuery)
            ->with('cliente:cliente_id,nombre,apellido')
            ->latest('id')
            ->limit(40)
            ->get();

        $fallidosPorCodigo = (clone $fallidosQuery)
            ->selectRaw("COALESCE(NULLIF(error_code, ''), 'sin_codigo') as codigo, COUNT(*) as total")
            ->groupBy('codigo')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return view('whatsapp.index', [
            'configured' => $this->whatsapp->isConfigured(),
            'enabled' => (bool) config('whatsapp.enabled'),
            'phoneNumberId' => (string) config('whatsapp.phone_number_id'),
            'businessAccountId' => (string) config('whatsapp.business_account_id'),
            'apiVersion' => (string) config('whatsapp.api_version'),
            'events' => config('whatsapp.events', []),
            'templatesConfig' => config('whatsapp.templates', []),
            'plantillasMeta' => $this->whatsapp->listTemplates(),
            'ultimos' => $ultimos,
            'mensajesFallidos' => $mensajesFallidos,
            'fallidosPorCodigo' => $fallidosPorCodigo,
            'conteos' => $conteos,
            'puedeEditar' => auth()->user()?->tienePermiso('whatsapp.editar') ?? false,
        ]);
    }

    public function mensajes(Request $request): View
    {
        $tel = $this->normalizeTel($request->get('tel'));

        $user = auth()->user();
        $puedeCrearPedido = $user?->tienePermiso('pedidos.crear') ?? false;

        $pedidoFormConfig = null;
        if ($puedeCrearPedido) {
            $estadoPedido = EstadoPedido::orderBy('descripcion')->first();
            if (! $estadoPedido) {
                $estadoPedido = EstadoPedido::create(['descripcion' => 'Pendiente']);
            }
            $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
            $pedidoFormConfig = [
                'pedidoId' => 'Nuevo',
                'planes' => $planes->map(fn (Plan $p) => [
                    'plan_id' => $p->plan_id,
                    'nombre' => $p->nombre,
                    'precio' => $p->precio ?? null,
                    'tecnologia_id' => $p->tecnologia_id ?? null,
                ])->values()->all(),
                'estadoId' => (int) ($estadoPedido->estado_id ?? 1),
                'buscarClienteUrl' => route('pedidos.buscar-cliente'),
                'verificarTelefonoUrl' => route('pedidos.verificar-telefono'),
                'cedulaTemporalUrl' => route('pedidos.cedula-temporal'),
                'consultarPadronUrl' => route('pedidos.consultar-padron'),
                'submitUrl' => route('pedidos.store'),
                'cancelUrl' => route('pedidos.index'),
                'csrfToken' => csrf_token(),
            ];
        }

        return view('whatsapp.mensajes', [
            'telInicial' => $tel,
            'buscarInicial' => trim((string) $request->get('buscar', '')),
            'configured' => $this->whatsapp->isConfigured(),
            'puedeEditar' => $user?->tienePermiso('whatsapp.editar') ?? false,
            'puedeCrearTicket' => $user?->tienePermiso('tickets.crear') ?? false,
            'puedeCrearPedido' => $puedeCrearPedido,
            'puedeCrearCobro' => $user?->tienePermiso('cobros.crear') ?? false,
            'pedidoFormConfig' => $pedidoFormConfig,
            'urls' => [
                'conversaciones' => route('whatsapp.conversaciones'),
                'hilo' => route('whatsapp.hilo'),
                'marcarLeidos' => route('whatsapp.marcar-leidos'),
                'asignarAsunto' => route('whatsapp.asignar-asunto'),
                'guardarContacto' => route('whatsapp.guardar-contacto'),
                'buscarClienteContacto' => route('whatsapp.buscar-cliente'),
                'asuntos' => route('whatsapp.asuntos.json'),
                'enviar' => route('whatsapp.enviar.store'),
                'enviarAdjunto' => route('whatsapp.enviar-adjunto'),
                'reintentarTpl' => url('/whatsapp/mensajes/__ID__/reintentar'),
                'enviarPlantilla' => route('whatsapp.enviar'),
                'mediaTpl' => url('/whatsapp/mensajes/__ID__/media'),
                'rapidoMeta' => route('whatsapp.rapido.meta'),
                'rapidoTicket' => route('whatsapp.rapido.ticket'),
                'rapidoPedido' => route('whatsapp.rapido.pedido'),
                'rapidoCobroMeta' => route('whatsapp.rapido.cobro-meta'),
                'rapidoCobro' => route('whatsapp.rapido.cobro'),
                'buscarCliente' => route('clientes.buscar'),
                'buscarClienteCedula' => route('pedidos.buscar-cliente'),
                'cedulaTemporal' => route('pedidos.cedula-temporal'),
                'consultarPadron' => route('pedidos.consultar-padron'),
                'ticketEditTpl' => url('/tickets/__ID__/edit'),
                'pedidoEditTpl' => url('/pedidos/__ID__/edit'),
                'clienteDetalleTpl' => url('/clientes/__ID__/detalle'),
                'cobroShowTpl' => url('/cobros/__ID__'),
            ],
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    /**
     * Datos para modales rápidos (ticket / pedido) sin salir del chat.
     */
    public function rapidoMeta(Request $request): JsonResponse
    {
        $user = $request->user();
        $puedeTicket = $user?->tienePermiso('tickets.crear') ?? false;
        $puedePedido = $user?->tienePermiso('pedidos.crear') ?? false;

        $clienteId = $request->filled('cliente_id') ? (int) $request->cliente_id : null;
        $telefono = $this->normalizeTel($request->get('telefono'));

        $cliente = null;
        if ($clienteId) {
            $cliente = Cliente::query()->where('cliente_id', $clienteId)->first();
        }
        if (! $cliente && $telefono) {
            $cliente = $this->whatsapp->findClienteByPhone($telefono);
        }

        $payload = [
            'puede_ticket' => $puedeTicket,
            'puede_pedido' => $puedePedido,
            'cliente' => $cliente ? [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'telefono' => $cliente->telefono,
            ] : null,
            'telefono' => $telefono,
            'ticket_asuntos' => [],
            'planes' => [],
            'tecnicos' => [],
            'hoy' => now()->toDateString(),
        ];

        if ($puedeTicket) {
            $payload['ticket_asuntos'] = TicketAsunto::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (TicketAsunto $a) => ['id' => $a->id, 'nombre' => $a->nombre])
                ->values();

            $payload['tecnicos'] = User::staff()->activos()->orderBy('name')
                ->get(['usuario_id', 'name'])
                ->map(fn (User $u) => ['id' => $u->usuario_id, 'nombre' => $u->name])
                ->values();
        }

        if ($puedePedido) {
            $payload['planes'] = Plan::query()
                ->where('estado', 'activo')
                ->orderBy('nombre')
                ->get(['plan_id', 'nombre'])
                ->map(fn (Plan $p) => ['id' => $p->plan_id, 'nombre' => $p->nombre])
                ->values();
        }

        return response()->json($payload);
    }

    public function storeTicketRapido(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'ticket_asunto_id' => ['required', 'integer', 'exists:ticket_asuntos,id'],
            'descripcion' => ['nullable', 'string'],
            'prioridad' => ['nullable', 'string', 'in:baja,media,alta'],
            'asignado_id' => ['nullable', 'integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $validated['usuario_id'] = $request->user()->usuario_id;
        $validated['estado'] = 'pendiente';
        $validated['prioridad'] = $validated['prioridad'] ?? 'media';
        $validated['reportado_desde'] = 'whatsapp';
        $validated['descripcion'] = isset($validated['descripcion']) && $validated['descripcion'] !== null
            ? trim((string) $validated['descripcion'])
            : null;

        $tel = $this->normalizeTel($validated['telefono'] ?? null);
        if ($tel && filled($validated['descripcion'])) {
            // noop — telefono solo contexto
        }
        if ($tel && ! filled($validated['descripcion'])) {
            $validated['descripcion'] = 'Creado desde WhatsApp ('.$tel.')';
        } elseif ($tel && filled($validated['descripcion']) && ! str_contains((string) $validated['descripcion'], $tel)) {
            $validated['descripcion'] = trim($validated['descripcion']."\n\n[WhatsApp: ".$tel.']');
        }

        unset($validated['telefono']);

        $duplicado = Ticket::query()
            ->where('usuario_id', $validated['usuario_id'])
            ->where('ticket_asunto_id', $validated['ticket_asunto_id'])
            ->where('cliente_id', $validated['cliente_id'] ?? null)
            ->where('estado', $validated['estado'])
            ->where('prioridad', $validated['prioridad'])
            ->where('descripcion', $validated['descripcion'])
            ->where('created_at', '>=', now()->subMinutes(3))
            ->latest('id')
            ->first();

        if ($duplicado) {
            return response()->json([
                'success' => false,
                'message' => 'Ya existe un ticket muy reciente con los mismos datos (ID #'.$duplicado->id.').',
                'ticket_id' => $duplicado->id,
                'url' => route('tickets.edit', $duplicado),
            ], 422);
        }

        $ticket = Ticket::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ticket #'.$ticket->id.' creado.',
            'ticket_id' => $ticket->id,
            'url' => route('tickets.edit', $ticket),
        ]);
    }

    public function storePedidoRapido(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cedula' => ['required', 'string', 'max:20'],
                'nombre' => ['required', 'string', 'max:100'],
                'apellido' => ['nullable', 'string', 'max:100'],
                'telefono' => ['nullable', 'string', 'max:30'],
                'fecha_pedido' => ['required', 'date'],
                'ubicacion' => ['nullable', 'string'],
                'maps_gps' => ['nullable', 'string'],
                'lat' => ['nullable', 'numeric', 'between:-90,90'],
                'lon' => ['nullable', 'numeric', 'between:-180,180'],
                'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
                'prioridad_instalacion' => ['nullable', 'integer', 'in:1,2,3'],
                'observaciones' => ['nullable', 'string'],
                'descripcion' => ['nullable', 'string'],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: 'Datos inválidos.',
                'errors' => $e->errors(),
            ], 422);
        }

        if (empty($validated['ubicacion']) && empty($validated['maps_gps'])
            && ($validated['lat'] ?? null) === null) {
            return response()->json([
                'success' => false,
                'message' => 'Indicá ubicación, link de Maps o coordenadas GPS.',
                'errors' => ['maps_gps' => ['Indicá ubicación o GPS.']],
            ], 422);
        }

        $telefonoNorm = TelefonoParaguayHelper::normalize($validated['telefono'] ?? null);
        if ($telefonoNorm !== null && $telefonoNorm !== '') {
            $clienteMismaCedula = Cliente::where('cedula', $validated['cedula'])->first();
            $excluirClienteId = $clienteMismaCedula?->cliente_id;
            if (TelefonoParaguayHelper::telefonoUsadoPorOtroClienteConPedido($telefonoNorm, $excluirClienteId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este teléfono ya está en otro pedido (cliente distinto).',
                    'errors' => ['telefono' => ['Teléfono en uso.']],
                ], 422);
            }
        }

        $cliente = Cliente::where('cedula', $validated['cedula'])->first();
        if (! $cliente) {
            $cliente = Cliente::create([
                'cedula' => $validated['cedula'],
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'estado' => 'solo_pedido',
            ]);
        } else {
            $cliente->update([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? $cliente->apellido,
                'telefono' => $validated['telefono'] ?? $cliente->telefono,
            ]);
        }

        $lat = $validated['lat'] ?? null;
        $lon = $validated['lon'] ?? null;
        if (($lat === null || $lon === null) && ! empty($validated['maps_gps'])) {
            $extracted = MapsUrlHelper::extractLatLonFromMapsUrl($validated['maps_gps']);
            $lat = $lat ?? $extracted['lat'];
            $lon = $lon ?? $extracted['lon'];
        }

        if (empty($validated['maps_gps']) && $lat !== null && $lon !== null) {
            $validated['maps_gps'] = 'https://www.google.com/maps?q='.rawurlencode($lat.','.$lon);
        }

        $ubicacion = $validated['ubicacion'] ?? $validated['maps_gps'] ?? '';
        $pedido = Pedido::create([
            'cliente_id' => $cliente->cliente_id,
            'fecha_pedido' => $validated['fecha_pedido'],
            'ubicacion' => $ubicacion,
            'maps_gps' => $validated['maps_gps'] ?? null,
            'lat' => $lat,
            'lon' => $lon,
            'plan_id' => $validated['plan_id'] ?? null,
            'prioridad_instalacion' => $validated['prioridad_instalacion'] ?? 2,
            'observaciones' => $validated['observaciones'] ?? null,
            'descripcion' => $validated['descripcion'] ?? ('Creado desde WhatsApp'),
        ]);

        $primerEstado = EstadoPedido::orderBy('estado_id')->first();
        if (! $primerEstado) {
            return response()->json([
                'success' => false,
                'message' => 'No hay estados de pedido configurados.',
            ], 422);
        }

        EstadoPedidoDetalle::create([
            'pedido_id' => $pedido->pedido_id,
            'estado_id' => $primerEstado->estado_id,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'estado' => 'P',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido #'.$pedido->pedido_id.' creado.',
            'pedido_id' => $pedido->pedido_id,
            'cliente_id' => $cliente->cliente_id,
            'url' => route('pedidos.edit', $pedido),
        ]);
    }

    /**
     * Facturas pendientes + formas de pago para cobrar desde WhatsApp.
     */
    public function cobroMeta(Request $request): JsonResponse
    {
        $clienteId = $request->filled('cliente_id') ? (int) $request->cliente_id : null;
        $telefono = $this->normalizeTel($request->get('telefono'));

        $cliente = null;
        if ($clienteId) {
            $cliente = Cliente::query()->where('cliente_id', $clienteId)->first();
        }
        if (! $cliente && $telefono) {
            $cliente = $this->whatsapp->findClienteByPhone($telefono);
        }

        if (! $cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Vinculá o buscá un cliente para registrar el pago.',
                'cliente' => null,
                'facturas' => [],
                'formas_pago' => Cobro::formasPago(),
                'hoy' => now()->toDateString(),
            ], 422);
        }

        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();
        $facturas = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get()
            ->map(fn (FacturaInterna $f) => [
                'id' => $f->id,
                'total' => (float) $f->total,
                'saldo_pendiente' => (float) $f->saldo_pendiente,
                'fecha_emision' => optional($f->fecha_emision)?->toDateString(),
                'fecha_vencimiento' => optional($f->fecha_vencimiento)?->toDateString(),
                'periodo_desde' => optional($f->periodo_desde)?->toDateString(),
                'periodo_hasta' => optional($f->periodo_hasta)?->toDateString(),
                'estado' => $f->estado,
            ])
            ->values();

        $totalPendiente = round($facturas->sum('saldo_pendiente'), 2);

        return response()->json([
            'success' => true,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'telefono' => $cliente->telefono,
                'url' => route('clientes.detalle', $cliente->cliente_id),
            ],
            'facturas' => $facturas,
            'total_pendiente' => $totalPendiente,
            'formas_pago' => Cobro::formasPago(),
            'hoy' => now()->toDateString(),
        ]);
    }

    public function storeCobroRapido(Request $request, FacturacionService $facturacion): JsonResponse
    {
        $ids = $request->input('factura_interna_ids', []);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];

        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'fecha_pago' => ['required', 'date'],
            'forma_pago' => ['required', 'string', 'in:efectivo,transferencia,tarjeta,cheque,otro'],
            'referencia' => ['nullable', 'string', 'max:100'],
            'concepto' => ['nullable', 'string', 'max:500'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $usuarioId = $request->user()?->usuario_id;
        $monto = (float) $validated['monto'];

        if ($ids === []) {
            return response()->json([
                'success' => false,
                'message' => 'Seleccioná al menos una factura pendiente.',
            ], 422);
        }

        $facturas = FacturaInterna::whereIn('id', $ids)
            ->where('cliente_id', $validated['cliente_id'])
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->get()
            ->filter(fn (FacturaInterna $f) => $f->saldo_pendiente > 0)
            ->values();

        if ($facturas->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Ninguna factura seleccionada tiene saldo pendiente.',
            ], 422);
        }

        if ($facturas->count() === 1) {
            $factura = $facturas->first();
            $saldoAntes = (float) $factura->saldo_pendiente;
            $cobro = $facturacion->registrarCobro([
                'cliente_id' => $validated['cliente_id'],
                'factura_interna_id' => $factura->id,
                'monto' => $monto,
                'fecha_pago' => $validated['fecha_pago'],
                'forma_pago' => $validated['forma_pago'],
                'referencia' => $validated['referencia'] ?? null,
                'concepto' => $validated['concepto'] ?? ('Cobro desde WhatsApp'),
                'observaciones' => $validated['observaciones'] ?? null,
            ], $usuarioId);

            if ($monto > $saldoAntes) {
                $facturacion->sumarSaldoAFavorCliente(
                    (int) $validated['cliente_id'],
                    $monto - $saldoAntes,
                    $factura->id
                );
            }
        } else {
            $items = $facturacion->distribuirMontoEntreFacturasFifo($facturas, $monto);
            if ($items === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo distribuir el monto entre las facturas.',
                ], 422);
            }

            $cobro = $facturacion->registrarCobro([
                'cliente_id' => $validated['cliente_id'],
                'monto' => $monto,
                'fecha_pago' => $validated['fecha_pago'],
                'forma_pago' => $validated['forma_pago'],
                'referencia' => $validated['referencia'] ?? null,
                'concepto' => $validated['concepto'] ?? ('Cobro desde WhatsApp'),
                'observaciones' => $validated['observaciones'] ?? null,
                'factura_interna_items' => $items,
            ], $usuarioId);
        }

        $cobro->load(['cliente', 'facturaInternas']);

        return response()->json([
            'success' => true,
            'message' => 'Cobro registrado. Recibo: '.$cobro->numero_recibo,
            'cobro_id' => $cobro->id,
            'numero_recibo' => $cobro->numero_recibo,
            'url' => route('cobros.show', $cobro),
            'cliente_url' => route('clientes.detalle', $validated['cliente_id']),
        ], 201);
    }

    public function conversacionesJson(Request $request): JsonResponse
    {
        $buscar = trim((string) $request->get('buscar', ''));
        $asuntoId = $request->filled('asunto_id') ? (int) $request->get('asunto_id') : null;
        $limit = max(50, min(500, (int) $request->get('limit', 250)));
        $offset = max(0, (int) $request->get('offset', 0));

        $page = $this->buildConversaciones($buscar, $asuntoId, $limit, $offset);

        return response()->json([
            'conversaciones' => $page['items']->values(),
            'total' => $page['total'],
            'has_more' => $page['has_more'],
            'limit' => $limit,
            'offset' => $offset,
            'ahora' => now()->toIso8601String(),
        ]);
    }

    public function asuntosJson(): JsonResponse
    {
        $asuntos = WhatsappAsunto::query()
            ->activos()
            ->get(['id', 'nombre', 'color', 'orden'])
            ->map(fn (WhatsappAsunto $a) => [
                'id' => $a->id,
                'nombre' => $a->nombre,
                'color' => $a->color,
            ]);

        return response()->json(['asuntos' => $asuntos]);
    }

    public function asignarAsunto(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->input('telefono'));
        if (! $tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono requerido'], 422);
        }

        $asuntoId = $request->input('whatsapp_asunto_id');
        if ($asuntoId === '' || $asuntoId === null) {
            $asuntoId = null;
        } else {
            $asuntoId = (int) $asuntoId;
            if (! WhatsappAsunto::query()->whereKey($asuntoId)->where('activo', true)->exists()) {
                return response()->json(['ok' => false, 'error' => 'Asunto inválido'], 422);
            }
        }

        $cliente = $this->whatsapp->findClienteByPhone($tel);
        $contacto = WhatsappContacto::query()->firstOrNew(['telefono' => $tel]);
        $contacto->whatsapp_asunto_id = $asuntoId;
        if ($cliente) {
            $contacto->cliente_id = $cliente->cliente_id;
        }
        $contacto->ultimo_visto_at = $contacto->ultimo_visto_at ?: now();
        $contacto->save();
        $contacto->load('asunto:id,nombre,color');

        return response()->json([
            'ok' => true,
            'asunto' => $contacto->asunto
                ? [
                    'id' => $contacto->asunto->id,
                    'nombre' => $contacto->asunto->nombre,
                    'color' => $contacto->asunto->color,
                ]
                : null,
        ]);
    }

    /**
     * Buscar clientes para vincular desde el chat (no requiere clientes.ver).
     */
    public function buscarClientes(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $clientes = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido', 'solo_pedido'])
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('apellido', 'like', "%{$q}%")
                    ->orWhere('cedula', 'like', "%{$q}%")
                    ->orWhere('telefono', 'like', "%{$q}%");
                if (ctype_digit($q) && strlen($q) <= 10) {
                    $query->orWhere('cliente_id', (int) $q);
                }
            })
            ->orderBy('nombre')
            ->limit(15)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        return response()->json($clientes);
    }

    /**
     * Guardar nombre del contacto WA y vincular (o desvincular) a un cliente ISP.
     */
    public function guardarContacto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'telefono' => ['required', 'string', 'max:40'],
            'nombre' => ['nullable', 'string', 'max:200'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'quitar_cliente' => ['sometimes', 'boolean'],
        ]);

        $tel = $this->normalizeTel($validated['telefono']);
        if (! $tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono inválido'], 422);
        }

        try {
            $result = $this->whatsapp->guardarContactoManual(
                $tel,
                $validated['nombre'] ?? null,
                isset($validated['cliente_id']) ? (int) $validated['cliente_id'] : null,
                $request->boolean('quitar_cliente'),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'telefono' => $tel,
            'nombre' => $result['nombre'],
            'cliente_id' => $result['cliente_id'],
            'cliente_nombre' => $result['cliente_nombre'],
        ]);
    }

    public function hiloJson(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->get('tel'));
        if (! $tel) {
            return response()->json(['error' => 'Teléfono requerido'], 422);
        }

        $afterId = (int) $request->get('after_id', 0);
        $updatedAfterRaw = trim((string) $request->get('updated_after', ''));
        $updatedAfter = null;
        if ($updatedAfterRaw !== '') {
            try {
                $updatedAfter = \Carbon\Carbon::parse($updatedAfterRaw);
            } catch (\Throwable) {
                $updatedAfter = null;
            }
        }

        $incremental = $afterId > 0 || $updatedAfter !== null;

        $query = WhatsappMensaje::query()->where('telefono', $tel);

        if ($incremental) {
            $query->where(function ($q) use ($afterId, $updatedAfter) {
                if ($afterId > 0) {
                    $q->where('id', '>', $afterId);
                }
                if ($updatedAfter) {
                    $method = $afterId > 0 ? 'orWhere' : 'where';
                    $q->{$method}(function ($inner) use ($updatedAfter, $afterId) {
                        $inner->where('updated_at', '>', $updatedAfter);
                        if ($afterId > 0) {
                            // Evitar duplicar los recién creados (ya vienen por after_id).
                            $inner->where('id', '<=', $afterId);
                        }
                    });
                }
            });
        }

        $hilo = $query
            ->orderBy('id')
            ->limit(300)
            ->get()
            ->map(fn (WhatsappMensaje $m) => $this->serializeMensaje($m));

        $contacto = WhatsappContacto::query()
            ->with(['cliente:cliente_id,nombre,apellido', 'asunto:id,nombre,color'])
            ->where('telefono', $tel)
            ->first();

        $ultimaEntrada = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'entrada')
            ->latest('id')
            ->first();

        $fueraVentana = ! $ultimaEntrada
            || ($ultimaEntrada->created_at && $ultimaEntrada->created_at->lt(now()->subHours(24)));

        $fallidos = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'salida')
            ->where('estado', 'fallido')
            ->count();

        $sinLeer = WhatsappMensaje::query()
            ->where('telefono', $tel)
            ->where('direccion', 'entrada')
            ->where('estado', '!=', WhatsappMensaje::ESTADO_LEIDO)
            ->count();

        $total = WhatsappMensaje::query()->where('telefono', $tel)->count();

        $maxId = (int) (WhatsappMensaje::query()->where('telefono', $tel)->max('id') ?: 0);
        $serverNow = now()->toIso8601String();
        $clasif = $this->clasificarTelefonos([$tel])->get($tel, [
            'tipo' => null,
            'label' => null,
            'color' => null,
        ]);

        return response()->json([
            'telefono' => $tel,
            'nombre' => $contacto?->nombre,
            'cliente_id' => $contacto?->cliente_id,
            'cliente_nombre' => $contacto?->cliente
                ? trim(($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? ''))
                : null,
            'asunto' => $contacto?->asunto
                ? [
                    'id' => $contacto->asunto->id,
                    'nombre' => $contacto->asunto->nombre,
                    'color' => $contacto->asunto->color,
                ]
                : null,
            'clasificacion' => $clasif['tipo'],
            'clasificacion_label' => $clasif['label'],
            'clasificacion_color' => $clasif['color'],
            'fuera_ventana' => $fueraVentana,
            'total' => $total,
            'fallidos' => $fallidos,
            'sin_leer' => $sinLeer,
            'mensajes' => $hilo->values(),
            'ultimo_id' => max($afterId, $maxId),
            'server_now' => $serverNow,
            'incremental' => $incremental,
        ]);
    }

    public function marcarLeidos(Request $request): JsonResponse
    {
        $tel = $this->normalizeTel($request->input('telefono') ?: $request->input('tel'));
        if (! $tel) {
            return response()->json(['ok' => false, 'error' => 'Teléfono requerido'], 422);
        }

        $result = $this->whatsapp->marcarConversacionLeida($tel);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function media(WhatsappMensaje $mensaje)
    {
        $path = $this->whatsapp->rutaMediaLocal($mensaje);
        if (! $path) {
            $mensaje = $this->whatsapp->adjuntarMediaLocal($mensaje);
            $path = $this->whatsapp->rutaMediaLocal($mensaje);
        }
        if (! $path) {
            return response('Media no disponible', 404, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }

        $absolute = \Illuminate\Support\Facades\Storage::disk('local')->path($path);
        if (! is_file($absolute) || ! is_readable($absolute)) {
            return response('Archivo multimedia no encontrado en disco.', 404, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }

        $mime = $this->whatsapp->mimeMediaLocal($mensaje, $absolute);
        if ($mensaje->tipo === 'image' && ! str_starts_with($mime, 'image/')) {
            return response('Media no disponible', 404, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ]);
        }
        $ext = pathinfo($absolute, PATHINFO_EXTENSION) ?: match (true) {
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'pdf') => 'pdf',
            str_contains($mime, 'mp4') => 'mp4',
            str_contains($mime, 'ogg') => 'ogg',
            default => 'bin',
        };
        $filename = 'wa-'.$mensaje->id.'.'.$ext;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return response()->file($absolute, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function contactos(Request $request): View
    {
        $q = WhatsappContacto::query()
            ->with('cliente:cliente_id,nombre,apellido,cedula')
            ->orderByDesc('ultimo_visto_at');

        if ($buscar = trim((string) $request->get('buscar', ''))) {
            $q->where(function ($inner) use ($buscar) {
                $inner->where('telefono', 'like', '%'.$buscar.'%')
                    ->orWhere('nombre', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $inner->orWhere('cliente_id', (int) $buscar);
                }
            });
        }

        return view('whatsapp.contactos', [
            'contactos' => $q->paginate(30)->withQueryString(),
        ]);
    }

    public function enviarForm(Request $request): View
    {
        $plantillas = $this->whatsapp->listTemplates();
        $aprobadas = array_values(array_filter(
            $plantillas,
            static fn (array $t) => strtoupper($t['status'] ?? '') === 'APPROVED'
        ));
        $pendientes = array_values(array_filter(
            $plantillas,
            static fn (array $t) => strtoupper($t['status'] ?? '') !== 'APPROVED'
        ));

        return view('whatsapp.enviar', [
            'plantillasMeta' => $plantillas,
            'plantillasAprobadas' => $aprobadas,
            'plantillasPendientes' => $pendientes,
            'defaultLang' => (string) config('whatsapp.default_template_language', 'es'),
            'configured' => $this->whatsapp->isConfigured(),
            'telefonoPrefill' => (string) $request->get('telefono', ''),
            'plantillaPrefill' => (string) $request->get('plantilla', ''),
        ]);
    }

    public function enviar(Request $request)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        if (! $this->whatsapp->isConfigured()) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => 'WhatsApp no está configurado.'], 422)
                : back()->withInput()->with('error', 'WhatsApp no está configurado.');
        }

        $validated = $request->validate([
            'telefono' => ['required', 'string', 'max:40'],
            'modo' => ['required', 'in:texto,plantilla'],
            'texto' => ['nullable', 'string', 'max:4000'],
            'plantilla' => ['nullable', 'string', 'max:120'],
            'lang' => ['nullable', 'string', 'max:10'],
            'params' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $mensaje = $this->dispatchEnvio($validated);
        } catch (\InvalidArgumentException $e) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $e->getMessage()], 422)
                : back()->withInput()->with('error', $e->getMessage());
        }

        if ($mensaje->estado === WhatsappMensaje::ESTADO_FALLIDO) {
            $msg = 'Falló el envío: '.($mensaje->error_message ?: 'error Meta');

            return $wantsJson
                ? response()->json([
                    'ok' => false,
                    'error' => $msg,
                    'mensaje' => $this->serializeMensaje($mensaje),
                ], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $msg);
        }

        return $wantsJson
            ? response()->json(['ok' => true, 'mensaje' => $this->serializeMensaje($mensaje)])
            : redirect()
                ->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])
                ->with('success', 'Mensaje #'.$mensaje->id.' enviado (estado: '.$mensaje->estado.').');
    }

    /**
     * Enviar imagen / PDF / video / audio (multipart) dentro de ventana 24 h.
     */
    public function enviarAdjunto(Request $request)
    {
        if (! $this->whatsapp->isConfigured()) {
            return response()->json(['ok' => false, 'error' => 'WhatsApp no está configurado.'], 422);
        }

        $validated = $request->validate([
            'telefono' => ['required', 'string', 'max:40'],
            'caption' => ['nullable', 'string', 'max:1024'],
            'archivo' => [
                'required',
                'file',
                'max:20480', // 20 MB
                'mimes:jpg,jpeg,png,pdf,mp4,3gp,aac,mp3,amr,ogg,opus,doc,docx,xls,xlsx,txt',
            ],
        ]);

        $mensaje = $this->whatsapp->sendUploadedMedia(
            $validated['telefono'],
            $request->file('archivo'),
            $validated['caption'] ?? null,
            ['contexto_tipo' => 'manual_panel']
        );

        if ($mensaje->estado === WhatsappMensaje::ESTADO_FALLIDO) {
            return response()->json([
                'ok' => false,
                'error' => 'Falló el envío: '.($mensaje->error_message ?: 'error Meta'),
                'mensaje' => $this->serializeMensaje($mensaje),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => $this->serializeMensaje($mensaje),
        ]);
    }

    public function reintentar(Request $request, WhatsappMensaje $mensaje)
    {
        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $nuevo = $this->whatsapp->reintentar($mensaje);
        } catch (\InvalidArgumentException $e) {
            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $e->getMessage()], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $msg = 'No se pudo reintentar: '.$e->getMessage();

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg], 500)
                : redirect()->route('whatsapp.mensajes', ['tel' => $mensaje->telefono])->with('error', $msg);
        }

        if ($nuevo->estado === WhatsappMensaje::ESTADO_FALLIDO) {
            $msg = 'Reintento #'.$nuevo->id.' falló: '.($nuevo->error_message ?: 'error Meta');

            return $wantsJson
                ? response()->json(['ok' => false, 'error' => $msg, 'mensaje' => $this->serializeMensaje($nuevo)], 422)
                : redirect()->route('whatsapp.mensajes', ['tel' => $nuevo->telefono])->with('error', $msg);
        }

        return $wantsJson
            ? response()->json(['ok' => true, 'mensaje' => $this->serializeMensaje($nuevo)])
            : redirect()
                ->route('whatsapp.mensajes', ['tel' => $nuevo->telefono])
                ->with('success', 'Reenviado (#'.$mensaje->id.' → #'.$nuevo->id.'), estado: '.$nuevo->estado.'.');
    }

    /**
     * @param  array{telefono:string,modo:string,texto?:string|null,plantilla?:string|null,lang?:string|null,params?:string|null}  $validated
     */
    private function dispatchEnvio(array $validated): WhatsappMensaje
    {
        if ($validated['modo'] === 'texto') {
            if (! filled($validated['texto'] ?? null)) {
                throw new \InvalidArgumentException('Escribí el mensaje de texto.');
            }

            return $this->whatsapp->sendText(
                $validated['telefono'],
                (string) $validated['texto'],
                ['contexto_tipo' => 'manual_panel']
            );
        }

        if (! filled($validated['plantilla'] ?? null)) {
            throw new \InvalidArgumentException('Indicá el nombre de la plantilla.');
        }

        $tplName = (string) $validated['plantilla'];
        $tplLangPreferido = $validated['lang'] ?: (string) config('whatsapp.default_template_language', 'es');
        $tplLang = $this->whatsapp->resolverIdiomaPlantilla($tplName, $tplLangPreferido) ?: $tplLangPreferido;
        $aprobada = collect($this->whatsapp->listApprovedTemplates())->first(function (array $t) use ($tplName, $tplLang) {
            return ($t['name'] ?? '') === $tplName
                && (
                    empty($t['language'])
                    || strtolower((string) $t['language']) === strtolower($tplLang)
                    || str_starts_with(strtolower((string) $t['language']), strtolower($tplLang))
                    || str_starts_with(strtolower($tplLang), strtolower((string) $t['language']))
                );
        });

        if (! $aprobada) {
            throw new \InvalidArgumentException(
                "La plantilla «{$tplName}» ({$tplLang}) no está APPROVED en Meta. Mientras esté PENDING no se puede enviar fuera de ventana 24h."
            );
        }

        $paramsRaw = trim((string) ($validated['params'] ?? ''));
        $params = $paramsRaw === ''
            ? []
            : array_map(
                static fn (string $p) => ['type' => 'text', 'text' => trim($p)],
                preg_split('/\r\n|\r|\n/', $paramsRaw) ?: []
            );
        $params = array_values(array_filter($params, static fn ($p) => ($p['text'] ?? '') !== ''));

        return $this->whatsapp->sendTemplate(
            $validated['telefono'],
            $tplName,
            (string) ($aprobada['language'] ?? $tplLang),
            $params,
            ['contexto_tipo' => 'manual_panel']
        );
    }

    private function normalizeTel(mixed $tel): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $tel) ?: null;
        if (! $digits) {
            return null;
        }

        return $this->whatsapp->normalizePhone($digits) ?? $digits;
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, total: int, has_more: bool}
     */
    private function buildConversaciones(string $buscar = '', ?int $asuntoId = null, int $limit = 100, int $offset = 0): array
    {
        $conversacionesQuery = WhatsappMensaje::query()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->selectRaw("telefono, MAX(id) as ultimo_id, COUNT(*) as total, SUM(CASE WHEN direccion = 'salida' AND estado = 'fallido' THEN 1 ELSE 0 END) as fallidos, SUM(CASE WHEN direccion = 'entrada' AND estado != 'leido' THEN 1 ELSE 0 END) as sin_leer")
            ->groupBy('telefono');

        if ($buscar !== '') {
            $conversacionesQuery->where(function ($inner) use ($buscar) {
                $inner->where('telefono', 'like', '%'.$buscar.'%')
                    ->orWhere('cuerpo', 'like', '%'.$buscar.'%')
                    ->orWhere('contacto_nombre', 'like', '%'.$buscar.'%');
                if (ctype_digit($buscar)) {
                    $inner->orWhere('cliente_id', (int) $buscar);
                }
            });
        }

        if ($asuntoId !== null) {
            if ($asuntoId === 0) {
                // Sin asunto
                $telsSin = WhatsappContacto::query()
                    ->whereNull('whatsapp_asunto_id')
                    ->pluck('telefono')
                    ->all();
                $telsConMensajeSinContacto = WhatsappMensaje::query()
                    ->whereNotIn('telefono', WhatsappContacto::query()->select('telefono'))
                    ->distinct()
                    ->pluck('telefono')
                    ->all();
                $conversacionesQuery->whereIn('telefono', array_values(array_unique(array_merge($telsSin, $telsConMensajeSinContacto))));
            } else {
                $tels = WhatsappContacto::query()
                    ->where('whatsapp_asunto_id', $asuntoId)
                    ->pluck('telefono')
                    ->all();
                $conversacionesQuery->whereIn('telefono', $tels === [] ? ['__none__'] : $tels);
            }
        }

        // limit+1 para saber si hay más, sin COUNT caro sobre GROUP BY
        $agg = (clone $conversacionesQuery)
            ->orderByDesc('ultimo_id')
            ->offset($offset)
            ->limit($limit + 1)
            ->get();

        $hasMore = $agg->count() > $limit;
        if ($hasMore) {
            $agg = $agg->take($limit)->values();
        }

        $ultimosIds = $agg->pluck('ultimo_id')->all();
        $ultimosMsgs = $ultimosIds === []
            ? collect()
            : WhatsappMensaje::query()->whereIn('id', $ultimosIds)->get()->keyBy('id');

        $telefonos = $agg->pluck('telefono')->all();
        $contactos = $telefonos === []
            ? collect()
            : WhatsappContacto::query()
                ->with(['cliente:cliente_id,nombre,apellido', 'asunto:id,nombre,color'])
                ->whereIn('telefono', $telefonos)
                ->get()
                ->keyBy('telefono');

        // cliente_id desde contacto o último mensaje (sin N+1 findClienteByPhone)
        $clienteIdPorTel = [];
        foreach ($agg as $row) {
            $cid = $contactos->get($row->telefono)?->cliente_id
                ?: $ultimosMsgs->get($row->ultimo_id)?->cliente_id;
            if ($cid) {
                $clienteIdPorTel[$row->telefono] = (int) $cid;
            }
        }

        $clasificaciones = $this->clasificarTelefonosRapido($telefonos, $clienteIdPorTel);

        $items = $agg->map(function ($row) use ($ultimosMsgs, $contactos, $clasificaciones) {
            $ultimo = $ultimosMsgs->get($row->ultimo_id);
            $contacto = $contactos->get($row->telefono);
            $nombre = $contacto?->nombre ?: $ultimo?->contacto_nombre;
            $clasif = $clasificaciones->get($row->telefono, [
                'tipo' => null,
                'label' => null,
                'color' => null,
            ]);

            return [
                'telefono' => $row->telefono,
                'total' => (int) $row->total,
                'fallidos' => (int) ($row->fallidos ?? 0),
                'sin_leer' => (int) ($row->sin_leer ?? 0),
                'nombre' => $nombre,
                'cliente_id' => $contacto?->cliente_id ?: $ultimo?->cliente_id,
                'cliente_nombre' => $contacto?->cliente
                    ? trim(($contacto->cliente->nombre ?? '').' '.($contacto->cliente->apellido ?? ''))
                    : null,
                'asunto' => $contacto?->asunto
                    ? [
                        'id' => $contacto->asunto->id,
                        'nombre' => $contacto->asunto->nombre,
                        'color' => $contacto->asunto->color,
                    ]
                    : null,
                'clasificacion' => $clasif['tipo'],
                'clasificacion_label' => $clasif['label'],
                'clasificacion_color' => $clasif['color'],
                'ultimo_id' => $ultimo?->id,
                'ultimo_cuerpo' => $ultimo
                    ? $this->whatsapp->cuerpoVisibleMensaje($ultimo)
                    : null,
                'ultimo_direccion' => $ultimo?->direccion,
                'ultimo_estado' => $ultimo?->estado,
                'ultimo_at' => $ultimo?->created_at?->toIso8601String(),
                'ultimo_at_label' => $ultimo?->created_at?->format('d/m H:i'),
            ];
        });

        $loaded = $offset + $items->count();

        return [
            'items' => $items,
            'total' => $hasMore ? $loaded + 1 : $loaded, // mínimo conocido
            'has_more' => $hasMore,
        ];
    }

    /**
     * Clasificación rápida para lista (sin findClienteByPhone N+1).
     *
     * @param  list<string>  $telefonos
     * @param  array<string, int>  $clienteIdPorTel
     * @return Collection<string, array{tipo:?string,label:?string,color:?string}>
     */
    private function clasificarTelefonosRapido(array $telefonos, array $clienteIdPorTel): Collection
    {
        $out = collect();
        foreach ($telefonos as $tel) {
            $out[$tel] = ['tipo' => null, 'label' => null, 'color' => null];
        }
        if ($telefonos === []) {
            return $out;
        }

        $staffSet = $this->telefonosStaffNormalizados();
        $clienteIds = array_values(array_unique(array_filter($clienteIdPorTel)));
        $conPedidoPendiente = $clienteIds === []
            ? []
            : Pedido::query()
                ->whereIn('cliente_id', $clienteIds)
                ->where('estado_instalado', false)
                ->pluck('cliente_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();
        $pedidoSet = array_fill_keys($conPedidoPendiente, true);

        foreach ($telefonos as $tel) {
            $norm = $this->normalizeTel($tel) ?: $tel;
            if (isset($staffSet[$norm]) || isset($staffSet[$tel])) {
                $out[$tel] = ['tipo' => 'staff', 'label' => 'Staff', 'color' => '#3b82f6'];
                continue;
            }
            $clienteId = $clienteIdPorTel[$tel] ?? null;
            if ($clienteId && isset($pedidoSet[$clienteId])) {
                $out[$tel] = ['tipo' => 'pedido', 'label' => 'Pedido pendiente', 'color' => '#f59e0b'];
                continue;
            }
            if ($clienteId) {
                $out[$tel] = ['tipo' => 'cliente', 'label' => 'Cliente', 'color' => '#10b981'];
            }
        }

        return $out;
    }

    /**
     * Clasifica teléfonos: staff > pedido (pendiente) > cliente.
     * Usado en hilo (pocos números); lista usa clasificarTelefonosRapido.
     *
     * @param  list<string|null>  $telefonos
     * @return Collection<string, array{tipo:?string,label:?string,color:?string}>
     */
    private function clasificarTelefonos(array $telefonos): Collection
    {
        $telefonosRaw = array_values(array_unique(array_filter($telefonos)));
        $clienteIdPorTel = [];
        foreach ($telefonosRaw as $tel) {
            $cliente = $this->whatsapp->findClienteByPhone($tel);
            if ($cliente) {
                $clienteIdPorTel[$tel] = (int) $cliente->cliente_id;
            }
        }
        $contactos = $telefonosRaw === []
            ? collect()
            : WhatsappContacto::query()
                ->whereIn('telefono', $telefonosRaw)
                ->whereNotNull('cliente_id')
                ->pluck('cliente_id', 'telefono');
        foreach ($contactos as $tel => $clienteId) {
            $clienteIdPorTel[$tel] = (int) $clienteId;
        }

        return $this->clasificarTelefonosRapido($telefonosRaw, $clienteIdPorTel);
    }

    /**
     * @return array<string, true> telefono normalizado => true
     */
    private function telefonosStaffNormalizados(): array
    {
        $set = [];

        $users = User::query()
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->get(['usuario_id', 'telefono']);

        foreach ($users as $user) {
            $n = $this->normalizeTel($user->telefono);
            if ($n) {
                $set[$n] = true;
            }
        }

        $map = (string) config('whatsapp.staff_phones', '');
        if ($map !== '') {
            foreach (explode(',', $map) as $pair) {
                $parts = array_map('trim', explode(':', $pair, 2));
                $phone = count($parts) === 2 ? $parts[1] : $parts[0];
                $n = $this->normalizeTel($phone);
                if ($n) {
                    $set[$n] = true;
                }
            }
        }

        return $set;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMensaje(WhatsappMensaje $m): array
    {
        $fallo = $m->esFallido() ? $m->detalleFallo() : null;
        $esMedia = in_array($m->tipo, ['audio', 'image', 'video', 'document', 'sticker'], true);
        $mediaReady = filled(data_get($m->payload, '_local.path'))
            && \Illuminate\Support\Facades\Storage::disk('local')->exists((string) data_get($m->payload, '_local.path'));
        $tieneMediaId = $esMedia && filled(data_get($m->payload, $m->tipo.'.id'));
        $ubicacion = $this->datosUbicacion($m);

        return [
            'id' => $m->id,
            'direccion' => $m->direccion,
            'telefono' => $m->telefono,
            'contacto_nombre' => $m->contacto_nombre,
            'tipo' => $m->tipo,
            'cuerpo' => $this->whatsapp->cuerpoVisibleMensaje($m),
            'template_name' => $m->template_name,
            'template_language' => $m->template_language,
            'estado' => $m->estado,
            'error_code' => $m->error_code,
            'error_message' => $m->error_message,
            'contexto_tipo' => $m->contexto_tipo,
            'wamid' => $m->wamid,
            'created_at' => $m->created_at?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
            'hora' => $m->created_at?->format('H:i'),
            'dia' => $m->created_at?->format('Y-m-d'),
            'dia_label' => $m->created_at?->translatedFormat('d M Y'),
            'fallo' => $fallo,
            // Relativa al host actual (el chat carga el binario por axios/blob con sesión).
            'media_url' => ($mediaReady || $tieneMediaId)
                ? route('whatsapp.media', $m, absolute: false).'?v='.(data_get($m->payload, '_local.size') ?: $m->updated_at?->timestamp ?: $m->id)
                : null,
            'media_mime' => data_get($m->payload, '_local.mime'),
            'media_voice' => (bool) data_get($m->payload, '_local.voice', data_get($m->payload, 'audio.voice', false)),
            'media_ready' => $mediaReady,
            'maps_url' => $ubicacion['url'] ?? null,
            'maps_lat' => $ubicacion['lat'] ?? null,
            'maps_lng' => $ubicacion['lng'] ?? null,
            'maps_nombre' => $ubicacion['nombre'] ?? null,
            'maps_direccion' => $ubicacion['direccion'] ?? null,
        ];
    }

    /**
     * @return array{lat:?float,lng:?float,nombre:?string,direccion:?string,url:?string}
     */
    private function datosUbicacion(WhatsappMensaje $m): array
    {
        $empty = ['lat' => null, 'lng' => null, 'nombre' => null, 'direccion' => null, 'url' => null];

        $lat = data_get($m->payload, 'location.latitude');
        $lng = data_get($m->payload, 'location.longitude');
        $nombre = trim((string) data_get($m->payload, 'location.name', '')) ?: null;
        $direccion = trim((string) data_get($m->payload, 'location.address', '')) ?: null;
        $urlMeta = trim((string) data_get($m->payload, 'location.url', '')) ?: null;

        // Mensajes viejos: cuerpo "lat,lng" o "lat, lng"
        if (($lat === null || $lng === null) && is_string($m->cuerpo) && preg_match(
            '/(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)/',
            $m->cuerpo,
            $match
        )) {
            $lat = $match[1];
            $lng = $match[2];
        }

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return $empty;
        }

        $latF = (float) $lat;
        $lngF = (float) $lng;
        if ($latF < -90 || $latF > 90 || $lngF < -180 || $lngF > 180) {
            return $empty;
        }

        $url = $urlMeta;
        if (! $url || ! str_starts_with($url, 'http')) {
            $url = 'https://www.google.com/maps?q='.rawurlencode($latF.','.$lngF);
        }

        return [
            'lat' => $latF,
            'lng' => $lngF,
            'nombre' => $nombre,
            'direccion' => $direccion,
            'url' => $url,
        ];
    }
}
