<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\MapsUrlHelper;
use App\Helpers\TelefonoParaguayHelper;
use App\Http\Controllers\PedidoController as WebPedidoController;
use App\Models\Cliente;
use App\Models\EstadoPedido;
use App\Models\EstadoPedidoDetalle;
use App\Models\Nodo;
use App\Models\Pedido;
use App\Models\Plan;
use App\Models\TipoTecnologia;
use App\Services\FacturacionService;
use App\Services\MikroTikService;
use App\Services\PedidoNodoOpcionesService;
use App\Services\Staff\StaffPedidoInstalacionService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffPedidoInstalacionController extends ApiController
{
    public function __construct(
        private readonly StaffPedidoInstalacionService $pedidos
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $filtros = $request->only([
            'estado', 'estado_id', 'plan_id', 'plan', 'desde', 'hasta', 'asignado_a', 'zona',
        ]);

        $items = $this->pedidos->listar($user, $filtros)
            ->map(fn (Pedido $p) => $this->pedidos->toItem($p, $user))
            ->values();

        // estado = clave de campo (en_camino, etc.). No confundir con estado_id del pipeline web.
        if (! empty($filtros['estado']) && ! is_numeric($filtros['estado'])) {
            $estado = mb_strtolower(trim(str_replace([' ', '-'], '_', (string) $filtros['estado'])));
            if (! in_array($estado, ['todos', ''], true)) {
                $items = $items->filter(fn ($i) => ($i['estado'] ?? '') === $estado)->values();
            }
        }
        if (! empty($filtros['zona'])) {
            $zona = mb_strtolower(trim((string) $filtros['zona']));
            $items = $items->filter(fn ($i) => mb_strtolower((string) ($i['zona'] ?? '')) === $zona)->values();
        }

        return $this->ok($items->all());
    }

    public function show(Request $request, int $id)
    {
        $pedido = $this->pedidos->encontrar($request->user(), $id);
        if (! $pedido) {
            return $this->fail('Pedido no encontrado.', 404);
        }

        return $this->ok($this->pedidos->toItem($pedido, $request->user()));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'fecha_pedido' => ['nullable', 'date'],
            'ubicacion' => ['nullable', 'string'],
            'maps_gps' => ['nullable', 'string'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'lon' => ['nullable', 'numeric', 'between:-180,180'],
            'plan_id' => ['nullable', 'integer', 'exists:planes,plan_id'],
            'prioridad_instalacion' => ['nullable', 'integer', 'in:1,2,3'],
            'observaciones' => ['nullable', 'string'],
            'descripcion' => ['nullable', 'string'],
            'notas' => ['nullable', 'string'],
        ]);

        $lon = $validated['lng'] ?? $validated['lon'] ?? null;
        $ubicacion = $validated['ubicacion'] ?? null;
        $mapsGps = $validated['maps_gps'] ?? null;
        if (empty($ubicacion) && empty($mapsGps) && ($validated['lat'] ?? null) !== null && $lon !== null) {
            $mapsGps = 'https://www.google.com/maps?q='.$validated['lat'].','.$lon;
        }

        if (empty($ubicacion) && empty($mapsGps)) {
            return $this->fail('Indicá ubicación, maps_gps o lat/lng.', 422);
        }

        $telefonoNorm = TelefonoParaguayHelper::normalize($validated['telefono'] ?? null);
        if ($telefonoNorm !== null && $telefonoNorm !== '') {
            $clienteMismaCedula = Cliente::where('cedula', $validated['cedula'])->first();
            $excluirClienteId = $clienteMismaCedula?->cliente_id;
            if (TelefonoParaguayHelper::telefonoUsadoPorOtroClienteConPedido($telefonoNorm, $excluirClienteId)) {
                return $this->fail('Este teléfono ya está en otro pedido (cliente distinto).', 422);
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
        if (($lat === null || $lon === null) && ! empty($mapsGps)) {
            $extracted = MapsUrlHelper::extractLatLonFromMapsUrl($mapsGps);
            $lat = $lat ?? $extracted['lat'];
            $lon = $lon ?? $extracted['lon'];
        }

        $pedido = Pedido::create([
            'cliente_id' => $cliente->cliente_id,
            'fecha_pedido' => $validated['fecha_pedido'] ?? now()->toDateString(),
            'ubicacion' => $ubicacion ?? $mapsGps ?? '',
            'maps_gps' => $mapsGps,
            'lat' => $lat,
            'lon' => $lon,
            'plan_id' => $validated['plan_id'] ?? null,
            'prioridad_instalacion' => $validated['prioridad_instalacion'] ?? 2,
            'observaciones' => $validated['observaciones'] ?? $validated['notas'] ?? null,
            'descripcion' => $validated['descripcion'] ?? null,
        ]);

        $primerEstado = EstadoPedido::orderBy('estado_id')->first();
        if (! $primerEstado) {
            return $this->fail('No existe ningún estado de pedido. Creá uno en el panel web.', 422);
        }

        EstadoPedidoDetalle::create([
            'pedido_id' => $pedido->pedido_id,
            'estado_id' => $primerEstado->estado_id,
            'usuario_id' => Auth::id(),
            'fecha' => now(),
            'estado' => 'P',
        ]);

        $pedido = $this->pedidos->encontrar($request->user(), (int) $pedido->pedido_id) ?? $pedido;

        return $this->ok($this->pedidos->toItem($pedido, $request->user()), 'Pedido creado', 201);
    }

    public function actualizar(Request $request, int $id)
    {
        $pedido = $this->pedidos->encontrar($request->user(), $id);
        if (! $pedido) {
            return $this->fail('Pedido no encontrado.', 404);
        }
        if ($pedido->estado_instalado) {
            return $this->fail('El pedido ya fue finalizado.', 400);
        }

        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'max:40'],
            'notas' => ['nullable', 'string'],
            'observaciones' => ['nullable', 'string'],
            'descripcion' => ['nullable', 'string'],
            'prioridad_instalacion' => ['nullable', 'integer', 'in:1,2,3'],
        ]);

        $obs = $validated['observaciones'] ?? $validated['notas'] ?? null;
        if ($obs !== null) {
            $pedido->observaciones = trim((string) $obs) ?: null;
        }
        if (array_key_exists('descripcion', $validated)) {
            $pedido->descripcion = trim((string) ($validated['descripcion'] ?? '')) ?: null;
        }
        if (isset($validated['prioridad_instalacion'])) {
            $pedido->prioridad_instalacion = (int) $validated['prioridad_instalacion'];
        }

        $estado = isset($validated['estado'])
            ? mb_strtolower(trim(str_replace([' ', '-'], '_', $validated['estado'])))
            : null;

        if ($estado === 'en_camino' || $estado === 'encamino') {
            $base = trim((string) ($pedido->observaciones ?? ''));
            if (! str_contains(mb_strtolower($base), '[en_camino]')) {
                $pedido->observaciones = trim($base."\n[en_camino]");
            }
        } elseif ($estado === 'finalizado') {
            return $this->finalizar($request, $id, app(FacturacionService::class));
        } elseif ($estado === 'cancelado' || $estado === 'no_realizado') {
            $pendiente = EstadoPedidoDetalle::query()
                ->where('pedido_id', $pedido->pedido_id)
                ->where('estado', 'P')
                ->orderByDesc('estado_id')
                ->first();
            if ($pendiente) {
                $pendiente->update([
                    'estado' => 'D',
                    'usuario_id' => Auth::id(),
                    'fecha' => now(),
                    'notas' => $obs ?? $pendiente->notas,
                ]);
            }
        }

        $pedido->save();
        $pedido = $this->pedidos->encontrar($request->user(), $id) ?? $pedido;

        return $this->ok($this->pedidos->toItem($pedido, $request->user()), 'ok');
    }

    public function finalizar(Request $request, int $id, FacturacionService $facturacion)
    {
        $pedido = $this->pedidos->encontrar($request->user(), $id);
        if (! $pedido) {
            return $this->fail('Pedido no encontrado.', 404);
        }

        $response = app(WebPedidoController::class)->finalizar($pedido, $facturacion);
        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            return $this->fail((string) ($payload['message'] ?? 'No se pudo finalizar.'), $status);
        }

        $fresh = $this->pedidos->encontrar($request->user(), $id) ?? $pedido->fresh();

        return $this->ok(
            $this->pedidos->toItem($fresh, $request->user()),
            (string) ($payload['message'] ?? 'Pedido finalizado')
        );
    }

    public function generarPppoe(Request $request, int $id, MikroTikService $mikrotik)
    {
        $pedido = $this->pedidos->encontrar($request->user(), $id);
        if (! $pedido) {
            return $this->fail('Pedido no encontrado.', 404);
        }

        $request->headers->set('Accept', 'application/json');
        $response = app(WebPedidoController::class)->crearUsuarioPppoe($request, $pedido, $mikrotik);
        $payload = $response->getData(true);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            return $this->fail((string) ($payload['message'] ?? 'No se pudo generar PPPoE.'), $status);
        }

        $fresh = $this->pedidos->encontrar($request->user(), $id) ?? $pedido->fresh();

        return $this->ok(
            $this->pedidos->toItem($fresh, $request->user()),
            (string) ($payload['message'] ?? 'Usuario PPPoE creado')
        );
    }

    public function aprobarEstado(Request $request, int $id, PedidoNodoOpcionesService $nodoOpciones)
    {
        return $this->accionHistorial($request, $id, function (Request $req, Pedido $pedido) use ($nodoOpciones) {
            return app(WebPedidoController::class)->aprobarEstado($req, $pedido, $nodoOpciones);
        });
    }

    public function descartarEstado(Request $request, int $id)
    {
        // Alias app: motivo → notas (web)
        if ($request->filled('motivo') && ! $request->filled('notas')) {
            $request->merge(['notas' => $request->input('motivo')]);
        }

        return $this->accionHistorial($request, $id, function (Request $req, Pedido $pedido) {
            return app(WebPedidoController::class)->descartarEstado($req, $pedido);
        });
    }

    public function reabrirEstado(Request $request, int $id)
    {
        return $this->accionHistorial($request, $id, function (Request $req, Pedido $pedido) {
            return app(WebPedidoController::class)->reabrirEstado($req, $pedido);
        });
    }

    /**
     * Catálogos para modal Aprobar (nodos / tecnologías / planes).
     * Con ?nodo_id=X también incluye tecnologías/pools compatibles del nodo.
     */
    public function opcionesAprobacion(Request $request, PedidoNodoOpcionesService $nodoOpciones)
    {
        $nodos = Nodo::query()
            ->orderBy('descripcion')
            ->get(['nodo_id', 'descripcion', 'ciudad'])
            ->map(fn (Nodo $n) => [
                'nodo_id' => (int) $n->nodo_id,
                'descripcion' => $n->descripcion,
                'ciudad' => $n->ciudad,
            ])
            ->values()
            ->all();

        $tecnologias = TipoTecnologia::query()
            ->orderBy('descripcion')
            ->get(['tecnologia_id', 'descripcion'])
            ->map(fn (TipoTecnologia $t) => [
                'tecnologia_id' => (int) $t->tecnologia_id,
                'descripcion' => $t->descripcion,
            ])
            ->values()
            ->all();

        $planes = Plan::query()
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get(['plan_id', 'nombre', 'prioridad'])
            ->map(fn (Plan $p) => [
                'plan_id' => (int) $p->plan_id,
                'nombre' => $p->nombre,
                'prioridad' => $p->prioridad !== null ? (int) $p->prioridad : null,
            ])
            ->values()
            ->all();

        $payload = [
            'nodos' => $nodos,
            'tecnologias' => $tecnologias,
            'planes' => $planes,
            'estado_id' => $request->filled('estado_id') ? (int) $request->input('estado_id') : null,
        ];

        if ($request->filled('nodo_id')) {
            try {
                $payload['nodo'] = $nodoOpciones->opcionesParaNodo((int) $request->input('nodo_id'));
            } catch (ModelNotFoundException) {
                return $this->fail('Nodo no encontrado.', 404);
            }
        }

        return $this->ok($payload);
    }

    public function opcionesNodo(int $nodoId, PedidoNodoOpcionesService $nodoOpciones)
    {
        try {
            return $this->ok($nodoOpciones->opcionesParaNodo($nodoId));
        } catch (ModelNotFoundException) {
            return $this->fail('Nodo no encontrado.', 404);
        }
    }

    /**
     * Opción A del contrato app: permisos tipados + usuario_id.
     */
    public function me(Request $request)
    {
        $user = $request->user()->loadMissing(['rol', 'cliente']);
        $maps = StaffConfigController::mapsPayload();

        return $this->ok([
            'usuario_id' => $user->usuario_id,
            'nombre' => $user->name,
            'email' => $user->email,
            'rol' => $user->rol?->descripcion,
            'es_administrador' => $user->esAdministrador(),
            'permisos' => StaffPedidoInstalacionService::permisosFlags($user),
            'permisos_lista' => is_array($user->permisos) ? $user->permisos : [],
            // Fallbacks Maps para app Staff (preferir GET /staff/config/maps)
            'maps_api_key' => $maps['maps_api_key'],
            'google_maps_api_key' => $maps['google_maps_api_key'],
            'config' => [
                'google_maps_api_key' => $maps['google_maps_api_key'],
                'maps_api_key' => $maps['maps_api_key'],
                'map_id' => $maps['map_id'],
            ],
        ]);
    }

    /**
     * @param  callable(Request, Pedido): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse  $accion
     */
    private function accionHistorial(Request $request, int $id, callable $accion): JsonResponse
    {
        $pedido = $this->pedidos->encontrar($request->user(), $id);
        if (! $pedido) {
            return $this->fail('Pedido no encontrado.', 404);
        }
        if ($pedido->estado_instalado) {
            return $this->fail('El pedido ya fue finalizado.', 400);
        }

        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        try {
            $response = $accion($request, $pedido);
        } catch (ModelNotFoundException) {
            return $this->fail('Estado no encontrado en el pedido.', 404);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'Datos inválidos.';

            return $this->fail((string) $msg, 422, $e->errors());
        }

        $payload = method_exists($response, 'getData') ? $response->getData(true) : null;
        $status = method_exists($response, 'getStatusCode') ? $response->getStatusCode() : 200;

        if (is_array($payload)) {
            if ($status >= 400 || (($payload['success'] ?? true) === false)) {
                return $this->fail((string) ($payload['message'] ?? 'No se pudo completar la acción.'), $status >= 400 ? $status : 400);
            }
            $message = (string) ($payload['message'] ?? 'OK');
        } else {
            $message = 'OK';
        }

        $fresh = $this->pedidos->encontrar($request->user(), $id) ?? $pedido->fresh();

        return $this->ok($this->pedidos->toItem($fresh, $request->user()), $message);
    }
}
