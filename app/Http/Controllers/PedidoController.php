<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Helpers\TelefonoParaguayHelper;
use App\Models\CedulaPadron;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\EstadoPedidoDetalle;
use App\Models\MikrotikOperacionPendiente;
use App\Models\Nodo;
use App\Models\Pedido;
use App\Models\Plan;
use App\Models\PoolIpAsignada;
use App\Models\RouterIpPool;
use App\Models\Servicio;
use App\Models\TipoTecnologia;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use App\Services\PedidoNodoOpcionesService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PedidoController extends Controller
{
    /**
     * Listar pedidos.
     */
    public function index(Request $request)
    {
        $query = Pedido::with(['cliente', 'plan', 'estadoPedidoDetalles.estadoPedido', 'estadoPedidoDetalles.usuario', 'estadoPedidoDetalles.plan', 'esperaAmpliacionRedUsuario'])
            ->withCount('agendas')
            ->orderBy('fecha_pedido', 'desc');

        // Filtros (estado_id, cliente_id, mostrar_instalados) se aplican en Vue (client-side)
        $pedidos = $query->get();
        $pedidos->transform(function ($pedido) {
            $ultimoConTecnologia = $pedido->estadoPedidoDetalles
                ->whereNotNull('tecnologia_id')
                ->sortByDesc('created_at')
                ->first();
            $pedido->tecnologia_id_seleccionado = $ultimoConTecnologia?->tecnologia_id;

            return $pedido;
        });
        $estados = EstadoPedido::orderBy('descripcion')->get();
        $clientes = Cliente::orderBy('nombre')->get();
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $tiposTecnologia = TipoTecnologia::orderBy('descripcion')->get();
        $estado = EstadoPedido::orderBy('descripcion')->first();
        if (! $estado) {
            $estado = EstadoPedido::create(['descripcion' => 'Pendiente']);
        }

        return response()
            ->view('pedidos.index', compact('pedidos', 'estados', 'clientes', 'planes', 'nodos', 'tiposTecnologia', 'estado'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Vista de mapas de pedidos (puntos por lat/lon con Google Maps).
     * Solo pedidos con instalación pendiente: no instalados, sin detalle descartado (D), con lat/lon.
     */
    public function mapasPedidos()
    {
        $pedidos = Pedido::with([
            'cliente',
            'plan',
            'estadoPedidoDetalles.estadoPedido',
            'estadoPedidoDetalles.usuario',
            'estadoPedidoDetalles.nodo',
            'estadoPedidoDetalles.tipoTecnologia',
            'estadoPedidoDetalles.plan',
        ])
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->where('estado_instalado', false)
            ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'))
            ->orderBy('fecha_pedido', 'desc')
            ->get();

        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        $nodos = Nodo::orderBy('descripcion')->get();
        $tiposTecnologia = TipoTecnologia::orderBy('descripcion')->get();
        $puedeAprobar = Auth::user()?->tienePermiso('pedidos.editar') ?? false;

        return view('clientes.mapas-pedidos', [
            'pedidosMapa' => $pedidos->map(fn (Pedido $p) => $this->pedidoParaMapa($p))->values(),
            'googleMapsApiKey' => config('services.google.maps_key'),
            'nodos' => $nodos,
            'planes' => $planes,
            'tiposTecnologia' => $tiposTecnologia,
            'puedeAprobar' => $puedeAprobar,
            'urlClientes' => route('clientes.mapas-pedidos.clientes'),
        ]);
    }

    /**
     * Formulario crear pedido.
     */
    public function create(Request $request)
    {
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();

        // Obtener el primer estado disponible o crear uno por defecto
        $estado = EstadoPedido::orderBy('descripcion')->first();
        if (! $estado) {
            $estado = EstadoPedido::create(['descripcion' => 'Pendiente']);
        }

        $initialValues = null;
        $clientePrefill = null;
        if ($request->filled('cliente_id')) {
            $clientePrefill = Cliente::find($request->integer('cliente_id'));
        } elseif ($request->filled('cedula')) {
            $clientePrefill = Cliente::buscarPorCedula((string) $request->input('cedula'));
        }
        $clienteFijo = false;
        $cancelUrl = route('pedidos.index');
        if ($clientePrefill) {
            $clienteFijo = $request->filled('cliente_id');
            $initialValues = array_merge($clientePrefill->payloadParaPedido(), [
                'ubicacion' => $clientePrefill->direccion,
                'maps_gps' => $clientePrefill->url_ubicacion,
            ]);
            if ($clienteFijo) {
                $cancelUrl = route('clientes.detalle', $clientePrefill);
            }
        }

        return view('pedidos.create', compact('planes', 'estado', 'initialValues', 'clienteFijo', 'cancelUrl'));
    }

    /**
     * Siguiente valor sugerido como cédula temporal (último cliente_id + 1).
     */
    public function cedulaTemporal()
    {
        $maxId = (int) Cliente::query()->max('cliente_id');

        return response()->json([
            'cedula' => (string) ($maxId + 1),
        ]);
    }

    /**
     * Verificar a qué cliente está asociado un teléfono (normalizado, mismo criterio que al guardar).
     */
    public function verificarTelefonoPedido(Request $request)
    {
        $request->validate([
            'telefono' => ['required', 'string', 'max:50'],
            'exclude_cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
        ]);

        $exclude = $request->filled('exclude_cliente_id') ? (int) $request->input('exclude_cliente_id') : null;
        [$conflicto, $mismo] = TelefonoParaguayHelper::buscarPorTelefonoNormalizado(
            $request->input('telefono'),
            $exclude
        );

        $payloadCliente = static function (Cliente $c): array {
            return [
                'cliente_id' => $c->cliente_id,
                'cedula' => $c->cedula,
                'nombre' => $c->nombre,
                'apellido' => $c->apellido ?? '',
            ];
        };

        if ($conflicto) {
            return response()->json([
                'encontrado' => true,
                'es_cliente_actual' => false,
                'cliente' => $payloadCliente($conflicto),
            ]);
        }

        if ($mismo) {
            return response()->json([
                'encontrado' => true,
                'es_cliente_actual' => true,
                'cliente' => $payloadCliente($mismo),
            ]);
        }

        return response()->json(['encontrado' => false]);
    }

    /**
     * Buscar cliente por cédula (API).
     */
    public function buscarCliente(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string'],
        ]);

        $cliente = Cliente::buscarPorCedula($request->cedula);

        if (! $cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json($cliente->payloadParaPedido());
    }

    /**
     * Consultar padrón por número de cédula (API).
     */
    public function consultarPadron(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string'],
        ]);

        try {
            $cedula = CedulaPadron::buscarPorCedula($request->cedula);

            if (! $cedula) {
                return response()->json([
                    'encontrado' => false,
                    'mensaje' => 'No se encontró en el padrón',
                ], 404);
            }

            return response()->json([
                'encontrado' => true,
                'cedula' => $cedula->NRODOC,
                'nombre' => trim($cedula->NOMBRE ?? ''),
                'apellido' => trim($cedula->APELLIDO ?? ''),
                'fecha_nacimiento' => $cedula->FECHANAC ? date('Y-m-d', strtotime($cedula->FECHANAC)) : null,
                'direccion' => trim($cedula->DIREC ?? ''),
                'domicilio' => trim($cedula->DOMIC ?? ''),
                'sexo' => $cedula->SEXO ?? null,
                'tipo_doc' => $cedula->TIPODOC ?? null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'encontrado' => false,
                'error' => 'Error al consultar el padrón: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guardar nuevo pedido.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'cliente_fijo' => ['sometimes', 'boolean'],
            'estado_id' => ['nullable', 'integer', 'exists:estados_pedidos,estado_id'],
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

        if (empty($validated['ubicacion']) && empty($validated['maps_gps'])) {
            return back()->withInput()->withErrors(['maps_gps' => 'Debe indicar al menos la ubicación o el enlace de Google Maps.']);
        }

        $clienteFijo = $request->boolean('cliente_fijo');
        $cliente = null;
        if (! empty($validated['cliente_id'])) {
            $cliente = Cliente::query()->find((int) $validated['cliente_id']);
        }

        $telefonoNorm = TelefonoParaguayHelper::normalize($validated['telefono'] ?? null);
        if ($telefonoNorm !== null && $telefonoNorm !== '') {
            $excluirClienteId = $cliente?->cliente_id ?? Cliente::buscarPorCedula($validated['cedula'])?->cliente_id;
            if (TelefonoParaguayHelper::telefonoUsadoPorOtroClienteConPedido($telefonoNorm, $excluirClienteId)) {
                throw ValidationException::withMessages([
                    'telefono' => 'Este número de teléfono ya está registrado en otro pedido (cliente distinto).',
                ]);
            }
        }

        if ($cliente) {
            $updates = [];
            if (! $cliente->tieneServiciosVigentes()) {
                if (! empty($validated['nombre'])) {
                    $updates['nombre'] = $validated['nombre'];
                }
                if (! empty($validated['apellido'])) {
                    $updates['apellido'] = $validated['apellido'];
                }
                if (! empty($validated['telefono'])) {
                    $updates['telefono'] = $validated['telefono'];
                }
            } elseif (empty($cliente->telefono) && ! empty($validated['telefono'])) {
                $updates['telefono'] = $validated['telefono'];
            }
            if ($updates !== []) {
                $cliente->update($updates);
            }
        } else {
            $cliente = Cliente::resolverParaPedido($validated);
        }

        // Extraer lat/lon de la URL de Maps si no vienen en el request
        $lat = $validated['lat'] ?? null;
        $lon = $validated['lon'] ?? null;
        if (($lat === null || $lon === null) && ! empty($validated['maps_gps'])) {
            $extracted = MapsUrlHelper::extractLatLonFromMapsUrl($validated['maps_gps']);
            $lat = $lat ?? $extracted['lat'];
            $lon = $lon ?? $extracted['lon'];
        }

        // Crear pedido (ubicación se rellena desde maps_gps si no se envía ubicacion)
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
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        // Usar el primer estado de estados_pedidos para estado_pedido_detalles
        $primerEstado = EstadoPedido::orderBy('estado_id')->first();
        if (! $primerEstado) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No existe ningún estado de pedido. Cree uno primero.',
                    'errors' => ['estado_id' => ['No existe ningún estado de pedido.']],
                ], 422);
            }

            return back()->withInput()->withErrors(['estado_id' => 'No existe ningún estado de pedido. Cree uno primero.']);
        }

        EstadoPedidoDetalle::create([
            'pedido_id' => $pedido->pedido_id,
            'estado_id' => $primerEstado->estado_id,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'estado' => 'P', // Pendiente
        ]);

        $mensaje = $cliente->tieneServiciosVigentes()
            ? 'Pedido de instalación creado para este cliente. Al instalar se agregará un servicio adicional.'
            : 'Pedido creado correctamente.';
        $redirect = $clienteFijo
            ? route('clientes.detalle', ['cliente' => $cliente->cliente_id, 'tab' => 'servicio'])
            : route('pedidos.index');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'pedido_id' => $pedido->pedido_id,
                'redirect' => $redirect,
            ]);
        }

        return redirect()->to($redirect)->with('success', $mensaje);
    }

    /**
     * Formulario editar pedido.
     */
    public function edit(Pedido $pedido)
    {
        // Incluir activo y solo_pedido (clientes creados desde pedidos)
        $clientes = Cliente::whereIn('estado', ['activo', 'solo_pedido'])->orderBy('nombre')->get();
        $estados = EstadoPedido::orderBy('descripcion')->get();
        $estadoActual = $pedido->estadoActual();

        return view('pedidos.edit', compact('pedido', 'clientes', 'estados', 'estadoActual'));
    }

    /**
     * Actualizar pedido.
     */
    public function update(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'fecha_pedido' => ['required', 'date'],
            'ubicacion' => ['nullable', 'string', 'max:500'],
            'maps_gps' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'celular' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'prioridad_instalacion' => ['nullable', 'integer', 'in:1,2,3'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $lat = $validated['lat'] ?? null;
        $lon = $validated['lon'] ?? null;
        if (($lat === null || $lon === null) && ! empty($validated['maps_gps'])) {
            $extracted = MapsUrlHelper::extractLatLonFromMapsUrl($validated['maps_gps']);
            $lat = $lat ?? $extracted['lat'];
            $lon = $lon ?? $extracted['lon'];
        }
        $validated['lat'] = $lat;
        $validated['lon'] = $lon;

        if (array_key_exists('celular', $validated)) {
            $celNorm = TelefonoParaguayHelper::normalize($validated['celular'] ?? null);
            if ($celNorm !== null && $celNorm !== '') {
                $excluirId = (int) $validated['cliente_id'];
                if (TelefonoParaguayHelper::telefonoUsadoPorOtroClienteConPedido($celNorm, $excluirId)) {
                    throw ValidationException::withMessages([
                        'celular' => 'Este número de teléfono ya está registrado en otro pedido (cliente distinto).',
                    ]);
                }
            }
        }

        $pedido->update($validated);

        // Actualizar celular del cliente si se envió
        if (array_key_exists('celular', $validated)) {
            $cliente = Cliente::find($validated['cliente_id']);
            if ($cliente) {
                $cliente->update(['telefono' => $validated['celular'] ?? '']);
            }
        }

        // Si se cambió el estado, crear un nuevo registro en estado_pedido_detalles
        if ($request->filled('estado_id') && $request->estado_id != $pedido->estadoActual()?->estado_id) {
            // Marcar el estado anterior como pendiente si existe
            $estadoAnterior = $pedido->estadoActual();
            if ($estadoAnterior) {
                // Usar where para actualizar porque la clave primaria es compuesta
                EstadoPedidoDetalle::where('pedido_id', $estadoAnterior->pedido_id)
                    ->where('estado_id', $estadoAnterior->estado_id)
                    ->update(['estado' => 'P']); // Pendiente
            }

            // Crear nuevo estado
            EstadoPedidoDetalle::create([
                'pedido_id' => $pedido->pedido_id,
                'estado_id' => $request->estado_id,
                'usuario_id' => Auth::id(),
                'fecha' => now(),
                'estado' => 'P', // Pendiente
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Pedido actualizado correctamente.');
    }

    /**
     * Eliminar pedido y sus detalles de estado relacionados.
     * Si el cliente tiene estado "solo_pedido" y ya no tiene más pedidos, se elimina también el cliente.
     */
    public function destroy(Pedido $pedido)
    {
        $clienteId = $pedido->cliente_id;

        $pedido->agendas()->delete();
        $pedido->estadoPedidoDetalles()->delete();
        $pedido->delete();

        $cliente = Cliente::find($clienteId);
        if ($cliente && $cliente->estado === 'solo_pedido' && Pedido::where('cliente_id', $clienteId)->count() === 0) {
            $cliente->delete();
        }

        return redirect()->route('pedidos.index')->with('success', 'Pedido eliminado correctamente.');
    }

    /**
     * Agregar estado a un pedido.
     */
    public function agregarEstado(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados_pedidos,estado_id'],
        ]);

        // No modificar estados aprobados. Solo se puede agregar un nuevo estado si hay un estado aprobado.
        // Los estados aprobados permanecen aprobados y no se modifican.

        // Verificar si ya existe un registro con esta combinación de pedido_id y estado_id
        $existeDetalle = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->first();

        if ($existeDetalle) {
            // Si existe, actualizar el existente
            EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
                ->where('estado_id', $validated['estado_id'])
                ->update([
                    'usuario_id' => Auth::id(),
                    'fecha' => now(),
                    'estado' => 'P', // Pendiente
                ]);
        } else {
            // Si no existe, crear nuevo estado
            EstadoPedidoDetalle::create([
                'pedido_id' => $pedido->pedido_id,
                'estado_id' => $validated['estado_id'],
                'usuario_id' => Auth::id(),
                'fecha' => now(),
                'estado' => 'P', // Pendiente
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Estado agregado correctamente.',
                'redirect' => route('pedidos.index'),
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado agregado correctamente.');
    }

    /**
     * Opciones al aprobar un estado con nodo: tecnología (auto si el nodo tiene una sola) y pools activos.
     */
    public function opcionesNodoAprobacion(int $nodo_id, PedidoNodoOpcionesService $nodoOpciones)
    {
        return response()->json($nodoOpciones->opcionesParaNodo($nodo_id));
    }

    /**
     * Aprobar un estado de pedido.
     */
    public function aprobarEstado(Request $request, Pedido $pedido, PedidoNodoOpcionesService $nodoOpciones)
    {
        $validated = $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados_pedidos,estado_id'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'nodo_id' => ['nullable', 'integer', 'exists:nodos,nodo_id'],
            'tecnologia_id' => ['nullable', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
            'pool_id' => ['nullable', 'integer', 'exists:router_ip_pools,pool_id'],
        ]);

        // Verificar que el estado pertenece al pedido
        $detalle = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->firstOrFail();

        // Verificar que el estado no esté ya aprobado
        if ($detalle->estado === 'A') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este estado ya está aprobado y no se puede modificar.',
                ], 400);
            }

            return redirect()->route('pedidos.index')->with('error', 'Este estado ya está aprobado y no se puede modificar.');
        }

        // Marcar solo los estados pendientes como pendiente (los aprobados se mantienen aprobados)
        EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado', 'P')
            ->update(['estado' => 'P']); // Ya están pendientes, pero esto asegura consistencia

        // Aprobar el estado seleccionado (notas + parámetros estructurados)
        $updateData = [
            'estado' => 'A',
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'notas' => $validated['notas'] ?? $detalle->notas,
        ];
        if (array_key_exists('nodo_id', $validated)) {
            $updateData['nodo_id'] = $validated['nodo_id'];
        }
        if (array_key_exists('tecnologia_id', $validated)) {
            $updateData['tecnologia_id'] = $validated['tecnologia_id'];
        }
        if (array_key_exists('plan_id', $validated)) {
            $updateData['plan_id'] = $validated['plan_id'];
        }

        if (! empty($validated['nodo_id'])) {
            try {
                $resuelto = $nodoOpciones->resolverSeleccionFinal(
                    (int) $validated['nodo_id'],
                    isset($validated['tecnologia_id']) ? (int) $validated['tecnologia_id'] : null,
                    isset($validated['pool_id']) ? (int) $validated['pool_id'] : null,
                );
                $updateData['nodo_id'] = (int) $validated['nodo_id'];
                if ($resuelto['tecnologia_id']) {
                    $updateData['tecnologia_id'] = $resuelto['tecnologia_id'];
                }
                if ($resuelto['pool_id']) {
                    $updateData['pool_id'] = $resuelto['pool_id'];
                }
            } catch (\Illuminate\Validation\ValidationException $e) {
                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => collect($e->errors())->flatten()->first() ?? 'Datos de nodo inválidos.',
                        'errors' => $e->errors(),
                    ], 422);
                }

                return redirect()->route('pedidos.index')
                    ->withErrors($e->errors())
                    ->with('error', collect($e->errors())->flatten()->first());
            }
        } elseif (array_key_exists('pool_id', $validated)) {
            $updateData['pool_id'] = $validated['pool_id'];
        }

        // Si no se enviaron nodo_id, tecnologia_id o plan_id, copiar solo de detalles del mismo pedido_id
        $pedidoId = (int) $pedido->pedido_id;
        if (! isset($updateData['nodo_id'])) {
            $ultimoConNodo = EstadoPedidoDetalle::where('pedido_id', $pedidoId)
                ->where('estado_id', '!=', $validated['estado_id'])
                ->whereNotNull('nodo_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            if ($ultimoConNodo) {
                $updateData['nodo_id'] = $ultimoConNodo->nodo_id;
            }
        }
        if (! isset($updateData['tecnologia_id'])) {
            $ultimoConTecnologia = EstadoPedidoDetalle::where('pedido_id', $pedidoId)
                ->where('estado_id', '!=', $validated['estado_id'])
                ->whereNotNull('tecnologia_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            if ($ultimoConTecnologia) {
                $updateData['tecnologia_id'] = $ultimoConTecnologia->tecnologia_id;
            }
        }
        if (! isset($updateData['plan_id'])) {
            $ultimoConPlan = EstadoPedidoDetalle::where('pedido_id', $pedidoId)
                ->where('estado_id', '!=', $validated['estado_id'])
                ->whereNotNull('plan_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            if ($ultimoConPlan) {
                $updateData['plan_id'] = $ultimoConPlan->plan_id;
            }
        }
        if (! isset($updateData['pool_id'])) {
            $ultimoConPool = EstadoPedidoDetalle::where('pedido_id', $pedidoId)
                ->where('estado_id', '!=', $validated['estado_id'])
                ->whereNotNull('pool_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            if ($ultimoConPool) {
                $updateData['pool_id'] = $ultimoConPool->pool_id;
            }
        }
        EstadoPedidoDetalle::where('pedido_id', $pedidoId)
            ->where('estado_id', $validated['estado_id'])
            ->update($updateData);

        // Si se guardó plan_id (acción seleccionar_plan), actualizar prioridad_instalacion del pedido con la prioridad del plan
        if (! empty($updateData['plan_id'])) {
            $plan = Plan::find($updateData['plan_id']);
            if ($plan !== null && $plan->prioridad !== null) {
                $pedido->update(['prioridad_instalacion' => (int) $plan->prioridad]);
            }
        }

        // Agregar siguiente estado si existe (orden estado_id ascendente)
        $siguienteEstado = EstadoPedido::where('estado_id', '>', $validated['estado_id'])
            ->orderBy('estado_id')
            ->first();
        if ($siguienteEstado) {
            $yaExiste = EstadoPedidoDetalle::where('pedido_id', $pedidoId)
                ->where('estado_id', $siguienteEstado->estado_id)
                ->exists();
            if (! $yaExiste) {
                EstadoPedidoDetalle::create([
                    'pedido_id' => $pedidoId,
                    'estado_id' => $siguienteEstado->estado_id,
                    'usuario_id' => Auth::id(),
                    'fecha' => now(),
                    'estado' => 'P', // Pendiente
                ]);
            }
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Estado aprobado correctamente.',
                'redirect' => route('pedidos.index'),
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado aprobado correctamente.');
    }

    /**
     * Reabrir un estado de pedido resuelto (A o D) para volver a pendiente (P).
     */
    public function reabrirEstado(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados_pedidos,estado_id'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $detalle = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->firstOrFail();

        if (! in_array($detalle->estado, ['A', 'D'], true)) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden reabrir estados aprobados o descartados.',
                ], 400);
            }

            return redirect()->route('pedidos.index')->with('error', 'Solo se pueden reabrir estados aprobados o descartados.');
        }

        EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->update([
                'estado' => 'P',
                'notas' => $validated['notas'] ?? $detalle->notas,
            ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Estado reabierto. Puede aprobar o descartar nuevamente.',
                'redirect' => route('pedidos.index'),
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado reabierto correctamente.');
    }

    /**
     * Marcar o quitar el pedido en espera de ampliación de red.
     */
    public function esperaAmpliacionRed(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'activo' => ['required', 'boolean'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $activo = (bool) $validated['activo'];

        if ($activo && $pedido->estado_instalado) {
            $msg = 'No se puede marcar en espera: el pedido ya está instalado.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')->with('error', $msg);
        }

        $update = $activo
            ? [
                'espera_ampliacion_red' => true,
                'espera_ampliacion_red_at' => now(),
                'espera_ampliacion_red_notas' => isset($validated['notas']) ? trim((string) $validated['notas']) : null,
                'espera_ampliacion_red_usuario_id' => Auth::id(),
            ]
            : [
                'espera_ampliacion_red' => false,
                'espera_ampliacion_red_at' => null,
                'espera_ampliacion_red_notas' => null,
                'espera_ampliacion_red_usuario_id' => null,
            ];

        $pedido->update($update);
        $pedido->load('esperaAmpliacionRedUsuario');

        $mensaje = $activo
            ? 'Pedido marcado en espera de ampliación de red.'
            : 'Se quitó la espera de ampliación de red del pedido.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'pedido' => [
                    'pedido_id' => (int) $pedido->pedido_id,
                    'espera_ampliacion_red' => (bool) $pedido->espera_ampliacion_red,
                    'espera_ampliacion_red_at' => $pedido->espera_ampliacion_red_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                    'espera_ampliacion_red_notas' => $pedido->espera_ampliacion_red_notas,
                    'espera_ampliacion_red_usuario' => $pedido->esperaAmpliacionRedUsuario?->name
                        ?? $pedido->esperaAmpliacionRedUsuario?->email
                        ?? '',
                ],
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', $mensaje);
    }

    /**
     * Descartar un estado de pedido.
     */
    public function descartarEstado(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados_pedidos,estado_id'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        // Verificar que el estado pertenece al pedido
        $detalle = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->firstOrFail();

        // Verificar que el estado no esté aprobado (los aprobados no se pueden descartar)
        if ($detalle->estado === 'A') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede descartar un estado que ya está aprobado.',
                ], 400);
            }

            return redirect()->route('pedidos.index')->with('error', 'No se puede descartar un estado que ya está aprobado.');
        }

        // Descartar el estado seleccionado
        EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', $validated['estado_id'])
            ->update([
                'estado' => 'D', // Descartado
                'usuario_id' => Auth::id(), // Usuario que descarta
                'fecha' => now(),
                'notas' => $validated['notas'] ?? null,
            ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Estado descartado correctamente.',
                'redirect' => route('pedidos.index'),
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado descartado correctamente.');
    }

    /**
     * Si el pedido quedó en un cliente solo_pedido duplicado, pasa la instalación al cliente que ya tiene servicio.
     */
    private function clienteDestinoInstalacion(Pedido $pedido): ?Cliente
    {
        $cliente = $pedido->cliente;
        if (! $cliente) {
            return null;
        }

        if ($cliente->tieneServiciosVigentes()) {
            return $cliente;
        }

        $existente = Cliente::buscarPorCedula($cliente->cedula);
        if (! $existente || (int) $existente->cliente_id === (int) $cliente->cliente_id) {
            return $cliente;
        }
        if (! $existente->tieneServiciosVigentes()) {
            return $cliente;
        }

        $pedido->update(['cliente_id' => $existente->cliente_id]);
        $pedido->setRelation('cliente', $existente);

        return $existente;
    }

    /**
     * Crear usuario PPPoE desde pedido: cliente activo + registro en servicios.
     * Se muestra el botón solo cuando estado_id = 3 y estado = A (aprobado).
     * Sincroniza el usuario con MikroTik tras crear el servicio.
     */
    public function crearUsuarioPppoe(Request $request, Pedido $pedido, MikroTikService $mikrotik)
    {
        $wantsJson = $request->wantsJson() || $request->ajax();

        $pedido->load(['cliente.servicios', 'plan', 'estadoPedidoDetalles']);

        // Verificar que el estado 3 esté aprobado
        $detalleEstado3 = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', 3)
            ->where('estado', 'A')
            ->first();

        if (! $detalleEstado3) {
            $msg = 'El pedido debe tener el estado 3 aprobado para crear usuario PPPoE.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        $cliente = $this->clienteDestinoInstalacion($pedido);
        if (! $cliente) {
            $msg = 'El pedido no tiene cliente asociado.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')->with('error', $msg);
        }

        $esServicioAdicional = $cliente->tieneServiciosVigentes();

        // 1. Pasar cliente a activo y actualizar dirección solo si está vacía
        $clienteData = ['estado' => 'activo'];
        if ($pedido->ubicacion && empty($cliente->direccion)) {
            $clienteData['direccion'] = $pedido->ubicacion;
        }
        $cliente->update($clienteData);

        // 2. Obtener nodo_id del detalle (estado 3 o el último con nodo_id)
        $nodoId = $detalleEstado3->nodo_id ?? null;
        if ($nodoId === null) {
            $ultimoConNodo = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
                ->whereNotNull('nodo_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            $nodoId = $ultimoConNodo?->nodo_id;
        }

        if ($nodoId === null) {
            $msg = 'No hay nodo asociado al pedido. Aprobá un estado con nodo seleccionado.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        // 3. Pool: el guardado al aprobar factibilidad, o el único activo del nodo
        $poolIdGuardado = $detalleEstado3->pool_id ?? null;
        if ($poolIdGuardado === null) {
            $ultimoConPool = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
                ->whereNotNull('pool_id')
                ->orderByDesc('fecha')
                ->orderByDesc('created_at')
                ->first();
            $poolIdGuardado = $ultimoConPool?->pool_id;
        }

        $pool = null;
        if ($poolIdGuardado) {
            $pool = RouterIpPool::query()
                ->where('pool_id', $poolIdGuardado)
                ->where('activo', true)
                ->whereHas('router', fn ($q) => $q->where('nodo_id', $nodoId))
                ->first();
        }
        if (! $pool) {
            $pool = RouterIpPool::query()
                ->where('activo', true)
                ->whereHas('router', fn ($q) => $q->where('nodo_id', $nodoId))
                ->orderBy('pool_id')
                ->first();
        }
        if (! $pool) {
            $msg = 'No hay pool de IP activo para el nodo del pedido. Aprobá factibilidad seleccionando un pool.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        // 4. Plan del pedido (del último detalle con plan_id o pedido.plan_id)
        $planId = $detalleEstado3->plan_id ?? $pedido->plan_id;
        if (! $planId) {
            $msg = 'No hay plan asociado al pedido.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        // 5. Crear servicio con datos del pedido (servicio_id es auto-increment)
        $clienteId = (int) $cliente->cliente_id;
        $aliasAdicional = $esServicioAdicional
            ? (trim((string) ($pedido->descripcion ?? '')) ?: null)
            : null;
        if ($aliasAdicional !== null && mb_strlen($aliasAdicional) > 80) {
            $aliasAdicional = null;
        }

        $usuarioPppoe = Servicio::usuarioPppoeDesdeClienteYAlias($cliente, $aliasAdicional, null, $clienteId);
        $passwordPppoe = Servicio::generarPasswordPppoe(
            Servicio::where('cliente_id', $clienteId)->pluck('password_pppoe')->all()
        );

        // Opcional: asignar primera IP disponible del pool (excluir .255),
        // validando además que no esté en uso por otro servicio.
        $ipAsignada = PoolIpAsignada::where('pool_id', $pool->pool_id)
            ->where('estado', 'disponible')
            ->whereRaw("ip NOT LIKE '%.255'")
            ->orderBy('ip')
            ->get()
            ->first(function (PoolIpAsignada $ipPool) use ($pool) {
                return ! Servicio::where('pool_id', $pool->pool_id)
                    ->where('ip', $ipPool->ip)
                    ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
                    ->exists();
            });

        $servicioData = [
            'cliente_id' => $clienteId,
            'pool_id' => $pool->pool_id,
            'plan_id' => $planId,
            'pedido_id' => $pedido->pedido_id,
            'alias' => $aliasAdicional,
            'ip' => $ipAsignada?->ip,
            'usuario_pppoe' => $usuarioPppoe,
            'password_pppoe' => $passwordPppoe,
            'fecha_instalacion' => now()->toDateString(),
            'estado' => 'P',
        ];

        $servicioCreado = null;
        try {
            DB::transaction(function () use ($servicioData, $ipAsignada, $pedido, $pool, &$servicioCreado) {
                if ($ipAsignada && Servicio::where('pool_id', $pool->pool_id)
                    ->where('ip', $ipAsignada->ip)
                    ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
                    ->exists()) {
                    throw new \RuntimeException('La IP seleccionada ya está asignada a otro servicio. Intentá nuevamente.');
                }

                $servicioCreado = Servicio::create($servicioData);
                if ($ipAsignada) {
                    PoolIpAsignada::where('pool_id', $ipAsignada->pool_id)
                        ->where('ip', $ipAsignada->ip)
                        ->update(['estado' => 'asignada']);
                }
                $pedido->update(['usuario_pppoe_creado' => true]);
            });
        } catch (\RuntimeException $e) {
            if ($wantsJson) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->route('pedidos.index')->with('error', $e->getMessage());
        }

        if (! $servicioCreado) {
            $msg = 'No se pudo crear el servicio PPPoE.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')->with('error', $msg);
        }

        $servicioCreado->load(['pool.router', 'plan.perfilPppoe', 'cliente']);
        $syncResult = $mikrotik->syncPppoeServicio($servicioCreado);

        $mensaje = $esServicioAdicional
            ? 'Servicio adicional creado en el mismo cliente. Usuario PPPoE listo.'
            : 'Usuario PPPoE creado.';
        if ($syncResult['success']) {
            $mensaje .= ' Sincronizado con MikroTik.';
        } else {
            $mensaje .= ' Sincronización con MikroTik falló: '.($syncResult['error'] ?? 'error desconocido').'. Podés sincronizar manualmente desde el servicio.';
            MikrotikOperacionPendiente::registrarSiFallo(
                MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                ['servicio_id' => $servicioCreado->servicio_id],
                $syncResult['error'] ?? 'Error',
                'pedidos.crear-usuario-pppoe'
            );
        }

        $redirectUrl = $esServicioAdicional
            ? route('clientes.detalle', ['cliente' => $clienteId, 'tab' => 'servicio'])
            : route('servicios.edit', $servicioCreado->servicio_id);

        if ($wantsJson) {
            return response()->json([
                'message' => $mensaje,
                'redirect' => $redirectUrl,
                'sync_ok' => (bool) $syncResult['success'],
            ]);
        }

        return redirect()->to($redirectUrl)
            ->with($syncResult['success'] ? 'success' : 'error', $mensaje);
    }

    /**
     * Finalizar pedido: todos los estados aprobados y usuario PPPoE creado.
     * Actualiza pedido.estado_instalado = true y servicios del pedido (estado = A). No modifica fecha_instalacion.
     */
    public function finalizar(Pedido $pedido, FacturacionService $facturacionService)
    {
        $pedido->load('estadoPedidoDetalles.estadoPedido', 'cliente');

        if ($pedido->estado_instalado) {
            return response()->json([
                'message' => 'El pedido ya fue finalizado.',
            ], 400);
        }

        $primerosEstados = EstadoPedido::orderBy('estado_id')->take(3)->pluck('estado_id');
        $detallesByEstado = $pedido->estadoPedidoDetalles->keyBy('estado_id');
        $todosAprobados = $primerosEstados->every(function ($estadoId) use ($detallesByEstado) {
            $det = $detallesByEstado->get($estadoId);

            return $det && $det->estado === 'A';
        });

        if (! $todosAprobados) {
            return response()->json([
                'message' => 'No se puede finalizar: todos los estados deben estar aprobados.',
            ], 400);
        }

        if (! $pedido->usuario_pppoe_creado) {
            return response()->json([
                'message' => 'No se puede finalizar: debe crear el usuario PPPoE primero.',
            ], 400);
        }

        $facturaInternaId = null;
        $servicioIdsPedido = Servicio::where('pedido_id', $pedido->pedido_id)->pluck('servicio_id')->all();
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();
        $omitirFacturaPorCalendario = ! FacturacionService::puedeEmitirFacturaPorInstalacion()
            && $pedido->cliente_id
            && $servicioIdsPedido !== [];
        $omitirFacturaPorAcuerdo = false;

        try {
            DB::transaction(function () use ($pedido, $facturacionService, &$facturaInternaId, &$omitirFacturaPorAcuerdo, $servicioIdsPedido, $inicioMes, $finMes) {
                $pedido->update(['estado_instalado' => true]);
                Servicio::where('pedido_id', $pedido->pedido_id)->update([
                    'estado' => 'A',
                ]);
                if ($pedido->cliente_id && ! empty(trim((string) $pedido->maps_gps))) {
                    $yaTeniaUbicacion = filled(trim((string) ($pedido->cliente?->url_ubicacion ?? '')));
                    $yaTeniaOtrosServicios = Servicio::where('cliente_id', $pedido->cliente_id)
                        ->where(function ($q) use ($pedido) {
                            $q->whereNull('pedido_id')->orWhere('pedido_id', '!=', $pedido->pedido_id);
                        })
                        ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
                        ->exists();
                    if (! $yaTeniaOtrosServicios || ! $yaTeniaUbicacion) {
                        $pedido->cliente->update(['url_ubicacion' => trim($pedido->maps_gps)]);
                    }
                }

                // Política de facturación: no generar factura interna al finalizar pedido entre el día 1 y 6;
                // solo desde el día 7 hasta fin de mes. Si hay acuerdo vigente, se omite la factura sin abortar.
                $puedeFacturarPedidoInstalado = FacturacionService::puedeEmitirFacturaPorInstalacion();

                if ($pedido->cliente_id && $servicioIdsPedido !== [] && $puedeFacturarPedidoInstalado) {
                    $idsFacturables = Servicio::whereIn('servicio_id', $servicioIdsPedido)
                        ->get()
                        ->reject(fn (Servicio $s) => $s->acuerdoAplicaEnPeriodo($inicioMes, $finMes))
                        ->pluck('servicio_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    if ($idsFacturables === []) {
                        $omitirFacturaPorAcuerdo = true;

                        return;
                    }
                    $resultado = $facturacionService->generarFacturaInternaDesdeServicios(
                        $idsFacturables,
                        $inicioMes,
                        $finMes,
                        Auth::id(),
                        sprintf('Pedido #%s — factura prorrateada por instalación.', $pedido->pedido_id)
                    );
                    $facturaInternaId = $resultado['primera']?->id;
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'No se pudo generar la factura interna: '.$e->getMessage(),
            ], 422);
        }

        $msgFactura = '';
        if ($facturaInternaId) {
            $msgFactura = ' Se generó la factura interna prorrateada del mes.';
        } elseif ($omitirFacturaPorAcuerdo) {
            $msgFactura = ' No se generó factura interna: el servicio tiene acuerdo de no facturación en este período.';
        } elseif ($omitirFacturaPorCalendario) {
            $msgFactura = ' No se generó factura interna: solo se emite desde el día 7 hasta fin de mes.';
        }

        $payload = [
            'message' => 'Pedido finalizado. Instalación marcada como completada.'.$msgFactura,
            'redirect' => route('pedidos.index'),
        ];
        if ($facturaInternaId !== null) {
            $payload['factura_interna_id'] = $facturaInternaId;
            $payload['factura_interna_url'] = route('factura-internas.show', $facturaInternaId);
        }

        return response()->json($payload);
    }

    /**
     * Clientes con estados de pedido aprobados (A) o desaprobados/descartados (D) en la fecha indicada (hoy por defecto).
     */
    public function resolucionesHoy(Request $request)
    {
        $fecha = $request->filled('fecha')
            ? Carbon::parse($request->fecha)->toDateString()
            : now()->timezone(config('app.timezone'))->toDateString();

        $detalles = EstadoPedidoDetalle::query()
            ->whereIn('estado', ['A', 'D'])
            ->whereDate('fecha', $fecha)
            ->with([
                'pedido.cliente',
                'pedido.plan',
                'estadoPedido',
                'usuario',
                'nodo',
                'plan',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('pedido_id')
            ->get();

        $mapRow = function (EstadoPedidoDetalle $d) {
            $pedido = $d->pedido;
            $cliente = $pedido?->cliente;
            $nombre = $cliente ? trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')) : '';

            return [
                'pedido_id' => (int) $d->pedido_id,
                'estado_id' => (int) $d->estado_id,
                'tipo' => $d->estado === 'A' ? 'aprobado' : 'desaprobado',
                'cliente_id' => $cliente ? (int) $cliente->cliente_id : null,
                'cliente_nombre' => $nombre,
                'cliente_cedula' => $cliente?->cedula ?? '',
                'cliente_telefono' => $cliente?->telefono ?? '',
                'estado_pedido' => $d->estadoPedido?->descripcion ?? '',
                'plan_nombre' => $pedido?->plan?->nombre ?? $d->plan?->nombre ?? '',
                'nodo' => $d->nodo?->descripcion ?? '',
                'fecha' => $d->fecha?->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
                'usuario' => $d->usuario?->name ?? $d->usuario?->email ?? '',
                'notas' => $d->notas ?? '',
            ];
        };

        $aprobados = $detalles->where('estado', 'A')->values()->map($mapRow)->values()->all();
        $desaprobados = $detalles->where('estado', 'D')->values()->map($mapRow)->values()->all();

        $clientesAprobados = collect($aprobados)->unique('cliente_id')->count();
        $clientesDesaprobados = collect($desaprobados)->unique('cliente_id')->count();

        return response()->json([
            'fecha' => $fecha,
            'fecha_label' => Carbon::parse($fecha)->format('d/m/Y'),
            'aprobados' => $aprobados,
            'desaprobados' => $desaprobados,
            'stats' => [
                'total_aprobados' => count($aprobados),
                'total_desaprobados' => count($desaprobados),
                'clientes_aprobados' => $clientesAprobados,
                'clientes_desaprobados' => $clientesDesaprobados,
            ],
        ]);
    }

    /**
     * Exportar todos los pedidos a Excel (CSV UTF-8 con separador ;), con datos del pedido, cliente, plan y workflow de estados.
     */
    public function exportarExcel(): StreamedResponse
    {
        $pedidos = Pedido::query()
            ->with([
                'cliente',
                'plan',
                'estadoPedidoDetalles.estadoPedido',
                'estadoPedidoDetalles.usuario',
                'estadoPedidoDetalles.nodo',
                'estadoPedidoDetalles.tipoTecnologia',
                'estadoPedidoDetalles.plan',
            ])
            ->withCount('agendas')
            ->orderBy('fecha_pedido', 'desc')
            ->get();

        $filename = 'pedidos-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($pedidos) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, [
                'ID pedido',
                'Fecha pedido',
                'Cliente ID',
                'Cédula',
                'Nombre',
                'Apellido',
                'Teléfono',
                'Email',
                'Dirección',
                'URL ubicación cliente',
                'Plan',
                'Prioridad instalación',
                'Ubicación',
                'Maps GPS',
                'Latitud',
                'Longitud',
                'Descripción pedido',
                'Observaciones pedido',
                'Instalación completada',
                'Usuario PPPoE creado',
                'Cant. agendas',
                'Creado (pedido)',
                'Actualizado (pedido)',
                'Workflow estados (detalle)',
            ], ';');

            foreach ($pedidos as $p) {
                $c = $p->cliente;
                fputcsv($output, [
                    $p->pedido_id,
                    $p->fecha_pedido?->format('d/m/Y') ?? '',
                    $c?->cliente_id ?? '',
                    $c?->cedula ?? '',
                    $c?->nombre ?? '',
                    $c?->apellido ?? '',
                    $c?->telefono ?? '',
                    $c?->email ?? '',
                    $c?->direccion ?? '',
                    $c?->url_ubicacion ?? '',
                    $p->plan?->nombre ?? '',
                    Pedido::prioridadLabel((int) ($p->prioridad_instalacion ?? 2)),
                    $p->ubicacion ?? '',
                    $p->maps_gps ?? '',
                    $p->lat !== null ? (string) $p->lat : '',
                    $p->lon !== null ? (string) $p->lon : '',
                    $p->descripcion ?? '',
                    $p->observaciones ?? '',
                    ($p->estado_instalado ?? false) ? 'Sí' : 'No',
                    ($p->usuario_pppoe_creado ?? false) ? 'Sí' : 'No',
                    (int) ($p->agendas_count ?? 0),
                    $p->created_at?->format('d/m/Y H:i:s') ?? '',
                    $p->updated_at?->format('d/m/Y H:i:s') ?? '',
                    $this->workflowPedidoTexto($p),
                ], ';');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function workflowPedidoTexto(Pedido $pedido): string
    {
        $partes = [];
        $detalles = $pedido->estadoPedidoDetalles->sortBy(function ($d) {
            return ($d->created_at ?? $d->fecha)?->timestamp ?? 0;
        });

        foreach ($detalles as $d) {
            $nombreEstado = $d->estadoPedido?->descripcion ?? '';
            $fecha = $d->fecha?->format('d/m/Y H:i') ?? '';
            $resolucion = $this->etiquetaEstadoDetalle($d->estado);
            $usuario = $d->usuario?->name ?? '';
            $notas = preg_replace('/\s+/u', ' ', trim((string) ($d->notas ?? '')));
            $nodo = $d->nodo?->descripcion ?? '';
            $tec = $d->tipoTecnologia?->descripcion ?? '';
            $planDet = $d->plan?->nombre ?? '';

            $trozo = "[{$nombreEstado}] {$fecha} · {$resolucion}";
            if ($usuario !== '') {
                $trozo .= ' · '.$usuario;
            }
            if ($notas !== '') {
                $trozo .= ' · '.$notas;
            }
            if ($nodo !== '') {
                $trozo .= ' · Nodo: '.$nodo;
            }
            if ($tec !== '') {
                $trozo .= ' · Tec: '.$tec;
            }
            if ($planDet !== '') {
                $trozo .= ' · Plan det.: '.$planDet;
            }
            $partes[] = $trozo;
        }

        return implode(' | ', $partes);
    }

    private function etiquetaEstadoDetalle(?string $estado): string
    {
        return match ($estado) {
            'A' => 'Aprobado',
            'D' => 'Descartado',
            'P' => 'Pendiente',
            default => $estado ?? '',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapEstadoDetalleParaMapa(?EstadoPedidoDetalle $detalle): ?array
    {
        if (! $detalle) {
            return null;
        }

        return [
            'titulo' => $detalle->estadoPedido?->descripcion ?? '',
            'resolucion' => $this->etiquetaEstadoDetalle($detalle->estado),
            'estado' => $detalle->estado,
            'fecha' => $detalle->fecha?->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            'usuario' => $detalle->usuario?->name ?? $detalle->usuario?->email ?? null,
            'notas' => $detalle->notas,
            'nodo' => $detalle->nodo?->descripcion,
            'tecnologia' => $detalle->tipoTecnologia?->descripcion,
            'plan' => $detalle->plan?->nombre,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pedidoParaMapa(Pedido $pedido): array
    {
        $detalles = $pedido->estadoPedidoDetalles->keyBy('estado_id');
        $ultimoConTecnologia = $pedido->estadoPedidoDetalles
            ->whereNotNull('tecnologia_id')
            ->sortByDesc('created_at')
            ->first();
        $tecnologiaDesc = $ultimoConTecnologia?->tipoTecnologia?->descripcion ?? null;
        $estadosPendientes = $pedido->estadoPedidoDetalles
            ->where('estado', 'P')
            ->sortBy('estado_id')
            ->map(fn (EstadoPedidoDetalle $d) => [
                'estado_id' => $d->estado_id,
                'descripcion' => $d->estadoPedido?->descripcion,
                'parametro' => $d->estadoPedido?->parametro,
            ])
            ->values()
            ->all();

        $detalleConNodo = $pedido->estadoPedidoDetalles
            ->filter(fn (EstadoPedidoDetalle $d) => $d->nodo_id)
            ->sortByDesc(fn (EstadoPedidoDetalle $d) => optional($d->fecha)?->timestamp ?? 0)
            ->first();
        $nodo = $detalleConNodo?->nodo;
        $zona = trim((string) ($nodo?->ciudad ?: $nodo?->descripcion ?: ''));
        $planConfirmado = $detalles->get(2)?->plan?->nombre;
        $planSolicitado = $pedido->plan?->nombre;

        return [
            'pedido_id' => $pedido->pedido_id,
            'lat' => (float) $pedido->lat,
            'lon' => (float) $pedido->lon,
            'ubicacion' => $pedido->ubicacion,
            'maps_gps' => $pedido->maps_gps,
            'maps_url' => MapsUrlHelper::toGoogleMapsUrl(
                $pedido->maps_gps,
                $pedido->lat !== null ? (float) $pedido->lat : null,
                $pedido->lon !== null ? (float) $pedido->lon : null
            ),
            'fecha_pedido' => $pedido->fecha_pedido ? $pedido->fecha_pedido->toDateString() : null,
            'cliente' => $pedido->cliente ? trim($pedido->cliente->nombre.' '.$pedido->cliente->apellido) : null,
            'documento' => $pedido->cliente?->cedula,
            'zona' => $zona !== '' ? $zona : null,
            'nodo' => $nodo?->descripcion,
            'plan' => $planConfirmado ?: $planSolicitado,
            'plan_confirmado' => $planConfirmado,
            'plan_solicitado' => $planSolicitado,
            'tecnologia_descripcion' => $tecnologiaDesc,
            'tecnologia_id_seleccionado' => $ultimoConTecnologia?->tecnologia_id,
            'estados_pendientes' => $estadosPendientes,
            'analisis_factibilidad' => $this->mapEstadoDetalleParaMapa($detalles->get(1)),
            'confirmacion_plan' => $this->mapEstadoDetalleParaMapa($detalles->get(2)),
        ];
    }
}
