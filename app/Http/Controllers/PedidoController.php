<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\EstadoPedidoDetalle;
use App\Models\Plan;
use App\Models\Nodo;
use App\Models\TipoTecnologia;
use App\Models\CedulaPadron;
use App\Models\Servicio;
use App\Models\Router;
use App\Models\RouterIpPool;
use App\Models\PoolIpAsignada;
use App\Helpers\MapsUrlHelper;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PedidoController extends Controller
{
    /**
     * Listar pedidos.
     */
    public function index(Request $request)
    {
        $query = Pedido::with(['cliente', 'plan', 'estadoPedidoDetalles.estadoPedido', 'estadoPedidoDetalles.usuario'])
            ->withCount('agendas')
            ->orderBy('fecha_pedido', 'desc');

        // Filtros (estado_id, cliente_id, mostrar_instalados) se aplican en Vue (client-side)

        // Cargar todos los pedidos (límite 500) para filtrado y búsqueda instantánea en Vue
        $pedidos = $query->limit(500)->get();
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
        if (!$estado) {
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
        $pedidos = Pedido::with(['cliente', 'plan', 'estadoPedidoDetalles.tipoTecnologia'])
            ->whereNotNull('lat')
            ->whereNotNull('lon')
            ->where('estado_instalado', false)
            ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'))
            ->orderBy('fecha_pedido', 'desc')
            ->get();

        return view('clientes.mapas-pedidos', [
            'pedidos' => $pedidos,
            'googleMapsApiKey' => config('services.google.maps_key'),
        ]);
    }

    /**
     * Formulario crear pedido.
     */
    public function create()
    {
        $planes = Plan::where('estado', 'activo')->orderBy('nombre')->get();
        
        // Obtener el primer estado disponible o crear uno por defecto
        $estado = EstadoPedido::orderBy('descripcion')->first();
        if (!$estado) {
            $estado = EstadoPedido::create(['descripcion' => 'Pendiente']);
        }
        
        return view('pedidos.create', compact('planes', 'estado'));
    }

    /**
     * Buscar cliente por cédula (API).
     */
    public function buscarCliente(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string'],
        ]);

        $cliente = Cliente::where('cedula', $request->cedula)->first();

        if (!$cliente) {
            return response()->json(['error' => 'Cliente no encontrado'], 404);
        }

        return response()->json([
            'cliente_id' => $cliente->cliente_id,
            'cedula' => $cliente->cedula,
            'nombre' => $cliente->nombre,
            'apellido' => $cliente->apellido,
            'telefono' => $cliente->telefono,
            'email' => $cliente->email,
        ]);
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

            if (!$cedula) {
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
                'error' => 'Error al consultar el padrón: ' . $e->getMessage(),
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

        // Buscar o crear cliente
        $cliente = Cliente::where('cedula', $validated['cedula'])->first();
        
        if (!$cliente) {
            $cliente = Cliente::create([
                'cedula' => $validated['cedula'],
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'estado' => 'solo_pedido', // No se muestra en la lista de clientes (solo en pedidos)
            ]);
        } else {
            // Actualizar datos del cliente si existen
            $cliente->update([
                'nombre' => $validated['nombre'],
                'apellido' => $validated['apellido'] ?? $cliente->apellido,
                'telefono' => $validated['telefono'] ?? $cliente->telefono,
            ]);
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
        if (!$primerEstado) {
            return back()->withInput()->withErrors(['estado_id' => 'No existe ningún estado de pedido. Cree uno primero.']);
        }

        EstadoPedidoDetalle::create([
            'pedido_id' => $pedido->pedido_id,
            'estado_id' => $primerEstado->estado_id,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'estado' => 'P', // Pendiente
        ]);

        return redirect()->route('pedidos.index')->with('success', 'Pedido creado correctamente.');
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
            'celular' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string'],
            'prioridad_instalacion' => ['nullable', 'integer', 'in:1,2,3'],
            'observaciones' => ['nullable', 'string'],
        ]);

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
                'redirect' => route('pedidos.index')
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado agregado correctamente.');
    }

    /**
     * Aprobar un estado de pedido.
     */
    public function aprobarEstado(Request $request, Pedido $pedido)
    {
        $validated = $request->validate([
            'estado_id' => ['required', 'integer', 'exists:estados_pedidos,estado_id'],
            'notas' => ['nullable', 'string', 'max:1000'],
            'nodo_id' => ['nullable', 'integer', 'exists:nodos,nodo_id'],
            'tecnologia_id' => ['nullable', 'integer', 'exists:tipos_tecnologias,tecnologia_id'],
            'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
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
        // Si no se enviaron nodo_id, tecnologia_id o plan_id, copiar solo de detalles del mismo pedido_id
        $pedidoId = (int) $pedido->pedido_id;
        if (!isset($updateData['nodo_id'])) {
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
        if (!isset($updateData['tecnologia_id'])) {
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
        if (!isset($updateData['plan_id'])) {
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
        EstadoPedidoDetalle::where('pedido_id', $pedidoId)
            ->where('estado_id', $validated['estado_id'])
            ->update($updateData);

        // Si se guardó plan_id (acción seleccionar_plan), actualizar prioridad_instalacion del pedido con la prioridad del plan
        if (!empty($updateData['plan_id'])) {
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
            if (!$yaExiste) {
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
                'redirect' => route('pedidos.index')
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado aprobado correctamente.');
    }

    /**
     * Reabrir un estado de pedido aprobado (cambiar de A a P para poder editarlo).
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

        if ($detalle->estado !== 'A') {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Solo se pueden reabrir estados aprobados.',
                ], 400);
            }
            return redirect()->route('pedidos.index')->with('error', 'Solo se pueden reabrir estados aprobados.');
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
                'redirect' => route('pedidos.index')
            ]);
        }

        return redirect()->route('pedidos.index')->with('success', 'Estado descartado correctamente.');
    }

    /**
     * Crear usuario PPPoE desde pedido: cliente activo + registro en servicios.
     * Se muestra el botón solo cuando estado_id = 3 y estado = A (aprobado).
     * Sincroniza el usuario con MikroTik tras crear el servicio.
     */
    public function crearUsuarioPppoe(Request $request, Pedido $pedido, MikroTikService $mikrotik)
    {
        $wantsJson = $request->wantsJson() || $request->ajax();

        $pedido->load(['cliente', 'plan', 'estadoPedidoDetalles']);

        // Verificar que el estado 3 esté aprobado
        $detalleEstado3 = EstadoPedidoDetalle::where('pedido_id', $pedido->pedido_id)
            ->where('estado_id', 3)
            ->where('estado', 'A')
            ->first();

        if (!$detalleEstado3) {
            $msg = 'El pedido debe tener el estado 3 aprobado para crear usuario PPPoE.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        $cliente = $pedido->cliente;
        if (!$cliente) {
            $msg = 'El pedido no tiene cliente asociado.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')->with('error', $msg);
        }

        // 1. Pasar cliente a activo y actualizar dirección si viene del pedido
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

        // 3. Obtener pool del nodo (primer router del nodo → primer pool activo)
        $router = Router::where('nodo_id', $nodoId)->first();
        if (!$router) {
            $msg = 'No hay router configurado para el nodo del pedido.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        $pool = RouterIpPool::where('router_id', $router->router_id)->where('activo', true)->first();
        if (!$pool) {
            $msg = 'No hay pool de IP activo para el router del nodo.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        // 4. Plan del pedido (del último detalle con plan_id o pedido.plan_id)
        $planId = $detalleEstado3->plan_id ?? $pedido->plan_id;
        if (!$planId) {
            $msg = 'No hay plan asociado al pedido.';
            if ($wantsJson) {
                return response()->json(['message' => $msg], 422);
            }

            return redirect()->route('pedidos.index')
                ->with('error', $msg);
        }

        // 5. Crear servicio con datos del pedido (servicio_id es auto-increment)
        $clienteId = (int) $cliente->cliente_id;

        // Usuario PPPoE: nombre y apellido separados por _ en mayúscula
        $nombre = trim($cliente->nombre ?? '');
        $apellido = trim($cliente->apellido ?? '');
        $partes = array_filter([$nombre, $apellido]);
        $usuarioPppoe = str_replace(' ', '_', Str::upper(Str::ascii(implode('_', $partes))));
        $usuarioPppoe = preg_replace('/[^A-Z0-9._-]/', '', $usuarioPppoe);
        if (strlen($usuarioPppoe) < 2) {
            $usuarioPppoe = 'CLIENTE' . $clienteId;
        }

        // Contraseña PPPoE: aleatoria alfanumérica, 8 caracteres (máx 20 en BD)
        $passwordPppoe = Str::random(8);

        // Opcional: asignar primera IP disponible del pool (excluir .255)
        $ipAsignada = PoolIpAsignada::where('pool_id', $pool->pool_id)
            ->where('estado', 'disponible')
            ->whereRaw("ip NOT LIKE '%.255'")
            ->orderBy('ip')
            ->first();

        $servicioData = [
            'cliente_id' => $clienteId,
            'pool_id' => $pool->pool_id,
            'plan_id' => $planId,
            'pedido_id' => $pedido->pedido_id,
            'ip' => $ipAsignada?->ip,
            'usuario_pppoe' => $usuarioPppoe,
            'password_pppoe' => $passwordPppoe,
            'fecha_instalacion' => now()->toDateString(),
            'estado' => 'P',
        ];

        $servicioCreado = null;
        DB::transaction(function () use ($servicioData, $ipAsignada, $pedido, &$servicioCreado) {
            $servicioCreado = Servicio::create($servicioData);
            if ($ipAsignada) {
                PoolIpAsignada::where('pool_id', $ipAsignada->pool_id)
                    ->where('ip', $ipAsignada->ip)
                    ->update(['estado' => 'asignada']);
            }
            $pedido->update(['usuario_pppoe_creado' => true]);
        });

        $servicioCreado->load(['pool.router', 'plan.perfilPppoe', 'cliente']);
        $syncResult = $mikrotik->syncPppoeServicio($servicioCreado);

        $mensaje = 'Usuario PPPoE creado.';
        if ($syncResult['success']) {
            $mensaje .= ' Sincronizado con MikroTik.';
        } else {
            $mensaje .= ' Sincronización con MikroTik falló: ' . ($syncResult['error'] ?? 'error desconocido') . '. Podés sincronizar manualmente desde el servicio.';
        }

        if ($wantsJson) {
            return response()->json([
                'message' => $mensaje,
                'redirect' => route('servicios.edit', $servicioCreado->servicio_id),
                'sync_ok' => (bool) $syncResult['success'],
            ]);
        }

        return redirect()->route('servicios.edit', $servicioCreado->servicio_id)
            ->with($syncResult['success'] ? 'success' : 'error', $mensaje);
    }

    /**
     * Finalizar pedido: todos los estados aprobados y usuario PPPoE creado.
     * Actualiza pedido.estado_instalado = true y servicios del pedido (estado = A). No modifica fecha_instalacion.
     */
    public function finalizar(Pedido $pedido, FacturacionService $facturacionService)
    {
        $pedido->load('estadoPedidoDetalles.estadoPedido');

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

        try {
            DB::transaction(function () use ($pedido, $facturacionService, &$facturaInternaId) {
                $pedido->update(['estado_instalado' => true]);
                Servicio::where('pedido_id', $pedido->pedido_id)->update([
                    'estado' => 'A',
                ]);
                if ($pedido->cliente_id && ! empty(trim((string) $pedido->maps_gps))) {
                    $pedido->cliente->update(['url_ubicacion' => trim($pedido->maps_gps)]);
                }

                $servicioIds = Servicio::where('pedido_id', $pedido->pedido_id)->pluck('servicio_id')->all();
                if ($pedido->cliente_id && $servicioIds !== []) {
                    $resultado = $facturacionService->generarFacturaInternaDesdeServicios(
                        $servicioIds,
                        Carbon::now()->startOfMonth(),
                        Carbon::now()->endOfMonth(),
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

        $payload = [
            'message' => 'Pedido finalizado. Instalación marcada como completada.'.($facturaInternaId ? ' Se generó la factura interna prorrateada del mes.' : ''),
            'redirect' => route('pedidos.index'),
        ];
        if ($facturaInternaId !== null) {
            $payload['factura_interna_id'] = $facturaInternaId;
            $payload['factura_interna_url'] = route('factura-internas.show', $facturaInternaId);
        }

        return response()->json($payload);
    }
}
