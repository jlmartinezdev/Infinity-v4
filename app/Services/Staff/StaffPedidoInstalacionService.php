<?php

namespace App\Services\Staff;

use App\Helpers\MapsUrlHelper;
use App\Models\EstadoPedidoDetalle;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StaffPedidoInstalacionService
{
    /**
     * @return Collection<int, Pedido>
     */
    public function listar(User $user, array $filtros = []): Collection
    {
        $estadoApp = isset($filtros['estado'])
            ? mb_strtolower(trim(str_replace([' ', '-'], '_', (string) $filtros['estado'])))
            : '';

        $estadoId = $this->parseEstadoId($filtros);

        $query = $this->baseQuery($user)
            ->with($this->eager());

        // Instalados / cancelados solo si se pide explícitamente por estado app
        if ($estadoApp === 'finalizado') {
            $query->where('estado_instalado', true);
        } elseif ($estadoApp === 'cancelado') {
            $query->where('estado_instalado', false)
                ->whereHas('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'));
        } else {
            $query->where('estado_instalado', false)
                ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'));
        }

        // Filtro combo web: estado_id = 1|2|3 (estado actual del pipeline)
        if ($estadoId !== null) {
            $query->where(function (Builder $q) use ($estadoId) {
                // Misma lógica que PedidosList.vue getEstadoActual:
                // preferir detalle pendiente (P) de mayor estado_id; si no hay P, el de mayor estado_id.
                $q->whereRaw(
                    '(SELECT epd.estado_id FROM estado_pedido_detalles epd
                      WHERE epd.pedido_id = pedidos.pedido_id
                      ORDER BY CASE WHEN epd.estado = \'P\' THEN 0 ELSE 1 END ASC,
                               epd.estado_id DESC
                      LIMIT 1) = ?',
                    [$estadoId]
                );
            });
        }

        if (! empty($filtros['plan_id'])) {
            $query->where('plan_id', (int) $filtros['plan_id']);
        } elseif (! empty($filtros['plan'])) {
            $plan = $filtros['plan'];
            if (is_numeric($plan)) {
                $query->where('plan_id', (int) $plan);
            } else {
                $query->whereHas('plan', fn ($q) => $q->where('nombre', 'like', '%'.$plan.'%'));
            }
        }

        if (! empty($filtros['desde'])) {
            $query->whereDate('fecha_pedido', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('fecha_pedido', '<=', $filtros['hasta']);
        }

        if (! empty($filtros['asignado_a'])) {
            $asignado = (int) $filtros['asignado_a'];
            $query->whereHas('agendas', fn ($q) => $q->where('usuario_id', $asignado));
        }

        return $query->orderByDesc('fecha_pedido')->orderByDesc('pedido_id')->limit(200)->get();
    }

    public function encontrar(User $user, int $id): ?Pedido
    {
        return $this->baseQuery($user)
            ->with($this->eager())
            ->whereKey($id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toItem(Pedido $pedido, User $user): array
    {
        $pedido->loadMissing($this->eager());

        $cliente = $pedido->cliente;
        $nombreCliente = trim(implode(' ', array_filter([$cliente?->nombre, $cliente?->apellido])));
        $coords = $this->coords($pedido);
        $zona = $this->zona($pedido);
        // PK real de agenda es `id` (no agenda_id)
        $agenda = $pedido->agendas->sortByDesc('id')->first();
        $asignadoId = $agenda?->usuario_id ? (int) $agenda->usuario_id : null;
        $asignadoNombre = $agenda?->usuario?->name;

        $actual = $this->estadoActualPipeline($pedido);
        $estadoId = $actual?->estado_id ? (int) $actual->estado_id : null;
        $estadoPipelineLabel = $actual?->estadoPedido?->descripcion
            ?? ($estadoId ? 'Estado '.$estadoId : null);
        $resolucionCodigo = (string) ($actual?->estado ?? 'P');
        $resolucion = match ($resolucionCodigo) {
            'A' => 'aprobado',
            'D' => 'descartado',
            default => 'pendiente',
        };
        $resolucionLabel = match ($resolucionCodigo) {
            'A' => 'Aprobado',
            'D' => 'Descartado',
            default => 'Pendiente',
        };

        // Selección vigente = misma lógica que PedidosList.vue getSeleccionPedido
        $seleccion = $this->seleccionVigente($pedido);

        $servicio = Servicio::query()
            ->where('pedido_id', $pedido->pedido_id)
            ->orderByDesc('servicio_id')
            ->first(['servicio_id', 'usuario_pppoe', 'password_pppoe', 'estado', 'ip']);

        $puedeVer = $user->tienePermiso('pedidos.ver') || $user->tienePermiso('clientes-pedidos.ver');
        $puedeEditar = $user->tienePermiso('pedidos.editar') || $user->tienePermiso('clientes-pedidos.editar');
        $puedeCrear = $user->tienePermiso('pedidos.crear') || $user->tienePermiso('clientes-pedidos.crear');
        $puedeFinalizar = $user->tienePermiso('pedidos.finalizar') || $puedeEditar;
        $puedePppoe = $puedeEditar;
        $accionesHistorial = $puedeEditar && ! $pedido->estado_instalado;

        $estadoKey = $this->estadoDerivado($pedido);
        $puedeGenerarPppoe = $puedePppoe
            && ! $pedido->usuario_pppoe_creado
            && ! $pedido->estado_instalado
            && EstadoPedidoDetalle::query()
                ->where('pedido_id', $pedido->pedido_id)
                ->where('estado_id', 3)
                ->where('estado', 'A')
                ->exists();

        $pppoeVisible = $puedePppoe && $servicio;
        $prioridad = (int) ($pedido->prioridad_instalacion ?? 2);

        return [
            'id' => (int) $pedido->pedido_id,
            'pedido_id' => (int) $pedido->pedido_id,
            'cliente' => $nombreCliente !== '' ? $nombreCliente : 'Sin cliente',
            'cliente_id' => $pedido->cliente_id ? (int) $pedido->cliente_id : null,
            'documento' => trim((string) ($cliente?->cedula ?? '')),
            'cedula' => trim((string) ($cliente?->cedula ?? '')),
            'telefono' => trim((string) ($cliente?->telefono ?? '')),
            'direccion' => trim((string) ($pedido->ubicacion ?: $cliente?->direccion ?: '')),
            'ubicacion' => trim((string) ($pedido->ubicacion ?? '')),
            'maps_gps' => trim((string) ($pedido->maps_gps ?? '')) ?: null,
            'zona' => $zona,
            // Igual cabecera web "Selección": plan solo si ya se confirmó en historial (null en CONFIRMAR DE PLAN pendiente).
            'plan' => $seleccion['plan'] ?? null,
            'plan_id' => $seleccion['plan_id'] ?? null,
            // Plan cargado al crear el pedido (NO es la selección confirmada).
            'plan_solicitado' => $pedido->plan?->nombre,
            'plan_solicitado_id' => $pedido->plan_id ? (int) $pedido->plan_id : null,
            // Alias de cabecera web "Selección"
            'nodo_id' => $seleccion['nodo_id'] ?? null,
            'nodo' => $seleccion['nodo'] ?? null,
            'tecnologia_id' => $seleccion['tecnologia_id'] ?? null,
            'tecnologia' => $seleccion['tecnologia'] ?? null,
            'seleccion' => $seleccion,
            // Pipeline Infinity (combo web Estado)
            'estado_id' => $estadoId,
            'estado_pipeline' => $estadoPipelineLabel,
            'estado_label' => $estadoPipelineLabel ?? $this->estadoLabel($estadoKey),
            'resolucion' => $resolucion,
            'resolucion_label' => $resolucionLabel,
            'resolucion_codigo' => $resolucionCodigo,
            // Estados de campo (app) — complementarios, no reemplazan el pipeline
            'estado' => $estadoKey,
            'estado_campo' => $estadoKey,
            'estado_campo_label' => $this->estadoLabel($estadoKey),
            'estados_disponibles' => ['pendiente', 'en_camino', 'en_proceso', 'finalizado', 'cancelado'],
            'prioridad' => match ($prioridad) {
                1 => 'alta',
                3 => 'baja',
                default => 'normal',
            },
            'prioridad_instalacion' => $prioridad,
            'fecha_pedido' => optional($pedido->fecha_pedido)?->toIso8601String()
                ?? (string) ($pedido->fecha_pedido ?? ''),
            'ventana_horaria' => null,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'asignado_a' => $asignadoId,
            'asignado_nombre' => $asignadoNombre,
            'pppoe_usuario' => $pppoeVisible ? ($servicio->usuario_pppoe ?? null) : null,
            'pppoe_password' => $pppoeVisible ? ($servicio->password_pppoe ?? null) : null,
            'pppoe_editable' => $pppoeVisible && $puedeEditar && ! $pedido->estado_instalado,
            'ip' => $pppoeVisible ? ($servicio->ip ?? null) : null,
            'notas' => trim((string) ($pedido->observaciones ?? '')),
            'descripcion' => trim((string) ($pedido->descripcion ?? '')),
            'usuario_pppoe_creado' => (bool) $pedido->usuario_pppoe_creado,
            'estado_instalado' => (bool) $pedido->estado_instalado,
            'historial' => $this->historial($pedido, $accionesHistorial),
            'puede_generar' => $puedeCrear,
            'puede_generar_pppoe' => $puedeGenerarPppoe,
            'puede_editar' => $accionesHistorial,
            'puede_finalizar' => $puedeFinalizar && ! $pedido->estado_instalado && (bool) $pedido->usuario_pppoe_creado,
            'puede_ver' => $puedeVer,
            'puede_pppoe_ver' => $puedePppoe,
        ];
    }

    /**
     * Permisos tipados para login / me (app staff).
     *
     * @return array<string, bool>
     */
    public static function permisosFlags(User $user): array
    {
        $ver = $user->tienePermiso('pedidos.ver') || $user->tienePermiso('clientes-pedidos.ver');
        $crear = $user->tienePermiso('pedidos.crear') || $user->tienePermiso('clientes-pedidos.crear');
        $editar = $user->tienePermiso('pedidos.editar') || $user->tienePermiso('clientes-pedidos.editar');
        $finalizar = $user->tienePermiso('pedidos.finalizar') || $editar;

        return [
            'pedidos_instalacion.ver' => $ver,
            'pedidos_instalacion.generar' => $crear,
            'pedidos_instalacion.editar' => $editar,
            'pedidos_instalacion.finalizar' => $finalizar,
            'pedidos_instalacion.pppoe_ver' => $editar,
            'pedidos_instalacion.pppoe_generar' => $editar,
            'pedidos_instalacion.pppoe_editar' => $editar,
            'pedidos.ver' => $ver,
            'clientes-pedidos.ver' => $ver,
            'tickets.ver' => $user->tienePermiso('tickets.ver'),
            'staff-mapa-tecnicos.ver' => $user->puedeVerFlotaStaff(),
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function eager(): array
    {
        return [
            'cliente:cliente_id,nombre,apellido,cedula,telefono,direccion,url_ubicacion',
            'plan:plan_id,nombre',
            'estadoPedidoDetalles.estadoPedido',
            'estadoPedidoDetalles.nodo:nodo_id,descripcion,ciudad',
            'estadoPedidoDetalles.tipoTecnologia:tecnologia_id,descripcion',
            'estadoPedidoDetalles.plan:plan_id,nombre',
            // PK de agenda = id
            'agendas' => fn ($q) => $q->latest('id')->limit(1)->with('usuario:usuario_id,name'),
        ];
    }

    /**
     * Selección vigente (cabecera web): detalle más reciente con nodo/tecnología/plan.
     *
     * @return array{nodo_id: int|null, nodo: string|null, tecnologia_id: int|null, tecnologia: string|null, plan_id: int|null, plan: string|null}|null
     */
    private function seleccionVigente(Pedido $pedido): ?array
    {
        $detalles = $pedido->relationLoaded('estadoPedidoDetalles')
            ? $pedido->estadoPedidoDetalles
            : collect();

        if ($detalles->isEmpty()) {
            return null;
        }

        $ordenados = $detalles->sortByDesc(function (EstadoPedidoDetalle $d) {
            return optional($d->fecha)?->timestamp
                ?? optional($d->created_at)?->timestamp
                ?? 0;
        })->values();

        $conDatos = $ordenados->first(fn (EstadoPedidoDetalle $d) => $d->nodo_id || $d->tecnologia_id || $d->plan_id
        );

        if (! $conDatos) {
            return null;
        }

        $conDatos->loadMissing([
            'nodo:nodo_id,descripcion,ciudad',
            'tipoTecnologia:tecnologia_id,descripcion',
            'plan:plan_id,nombre',
        ]);

        return [
            'nodo_id' => $conDatos->nodo_id ? (int) $conDatos->nodo_id : null,
            'nodo' => $conDatos->nodo?->descripcion,
            'tecnologia_id' => $conDatos->tecnologia_id ? (int) $conDatos->tecnologia_id : null,
            'tecnologia' => $conDatos->tipoTecnologia?->descripcion,
            'plan_id' => $conDatos->plan_id ? (int) $conDatos->plan_id : null,
            'plan' => $conDatos->plan?->nombre,
        ];
    }

    /**
     * @return Builder<Pedido>
     */
    private function baseQuery(User $user): Builder
    {
        $query = Pedido::query();

        // Admin / gerente / cajero: todos. Técnico: agenda propia o sin agenda (cola).
        if ($user->puedeVerTodasVisitasStaff() || $user->esAdministrador()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->whereHas('agendas', fn ($a) => $a->where('usuario_id', $user->usuario_id))
                ->orWhereDoesntHave('agendas');
        });
    }

    /**
     * Estado actual del pipeline (igual criterio que PedidosList.vue).
     */
    private function estadoActualPipeline(Pedido $pedido): ?EstadoPedidoDetalle
    {
        $detalles = $pedido->relationLoaded('estadoPedidoDetalles')
            ? $pedido->estadoPedidoDetalles
            : $pedido->estadoPedidoDetalles()->with('estadoPedido')->get();

        if ($detalles->isEmpty()) {
            return null;
        }

        $pendientes = $detalles->where('estado', 'P')->sortByDesc('estado_id');
        if ($pendientes->isNotEmpty()) {
            return $pendientes->first();
        }

        return $detalles->sortByDesc('estado_id')->first();
    }

    private function parseEstadoId(array $filtros): ?int
    {
        $raw = $filtros['estado_id'] ?? null;
        // Alias: ?estado=3 (numérico) = mismo filtro combo web
        if (($raw === null || $raw === '' || $raw === 'todos') && isset($filtros['estado']) && is_numeric($filtros['estado'])) {
            $raw = $filtros['estado'];
        }
        if ($raw === null || $raw === '' || $raw === 'todos') {
            return null;
        }
        if (! is_numeric($raw)) {
            return null;
        }

        return (int) $raw;
    }

    private function estadoDerivado(Pedido $pedido): string
    {
        if ($pedido->estado_instalado) {
            return 'finalizado';
        }

        $detalles = $pedido->relationLoaded('estadoPedidoDetalles')
            ? $pedido->estadoPedidoDetalles
            : $pedido->estadoPedidoDetalles()->get();

        if ($detalles->contains(fn ($d) => $d->estado === 'D')) {
            return 'cancelado';
        }

        if ($pedido->usuario_pppoe_creado || $detalles->where('estado', 'A')->count() >= 2) {
            return 'en_proceso';
        }

        $obs = mb_strtolower((string) ($pedido->observaciones ?? ''));
        if (str_contains($obs, '[en_camino]') || str_contains($obs, 'en camino')) {
            return 'en_camino';
        }

        return 'pendiente';
    }

    private function estadoLabel(string $key): string
    {
        return match ($key) {
            'en_camino' => 'En camino',
            'en_proceso' => 'En proceso',
            'finalizado' => 'Finalizado',
            'cancelado' => 'Cancelado',
            default => 'Pendiente',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function historial(Pedido $pedido, bool $puedeEditar = false): array
    {
        $detalles = $pedido->relationLoaded('estadoPedidoDetalles')
            ? $pedido->estadoPedidoDetalles
            : collect();

        return $detalles->sortBy('estado_id')->values()->map(function (EstadoPedidoDetalle $d) use ($puedeEditar) {
            $codigo = (string) ($d->estado ?? 'P');
            $d->loadMissing([
                'nodo:nodo_id,descripcion,ciudad',
                'tipoTecnologia:tecnologia_id,descripcion',
                'plan:plan_id,nombre',
            ]);

            return [
                'estado_id' => (int) $d->estado_id,
                'nombre' => $d->estadoPedido?->descripcion ?? ('Estado '.$d->estado_id),
                'resolucion' => match ($codigo) {
                    'A' => 'aprobado',
                    'D' => 'descartado',
                    default => 'pendiente',
                },
                'resolucion_label' => match ($codigo) {
                    'A' => 'Aprobado',
                    'D' => 'Descartado',
                    default => 'Pendiente',
                },
                'resolucion_codigo' => $codigo,
                'fecha' => optional($d->fecha)?->toIso8601String(),
                'notas' => $d->notas,
                'nodo_id' => $d->nodo_id ? (int) $d->nodo_id : null,
                'nodo' => $d->nodo?->descripcion,
                'tecnologia_id' => $d->tecnologia_id ? (int) $d->tecnologia_id : null,
                'tecnologia' => $d->tipoTecnologia?->descripcion,
                'plan_id' => $d->plan_id ? (int) $d->plan_id : null,
                'plan' => $d->plan?->nombre,
                'puede_aprobar' => $puedeEditar && $codigo === 'P',
                'puede_descartar' => $puedeEditar && $codigo === 'P',
                'puede_reabrir' => $puedeEditar && in_array($codigo, ['A', 'D'], true),
            ];
        })->all();
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function coords(Pedido $pedido): array
    {
        if ($pedido->lat !== null && $pedido->lon !== null) {
            return ['lat' => (float) $pedido->lat, 'lng' => (float) $pedido->lon];
        }

        $maps = trim((string) ($pedido->maps_gps ?? ''));
        if ($maps !== '') {
            $c = MapsUrlHelper::extractLatLonFromMapsUrl($maps, false);
            if ($c['lat'] !== null && $c['lon'] !== null) {
                return ['lat' => (float) $c['lat'], 'lng' => (float) $c['lon']];
            }
        }

        $url = trim((string) ($pedido->cliente?->url_ubicacion ?? ''));
        if ($url !== '') {
            $c = MapsUrlHelper::extractLatLonFromMapsUrl($url, false);
            if ($c['lat'] !== null && $c['lon'] !== null) {
                return ['lat' => (float) $c['lat'], 'lng' => (float) $c['lon']];
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    private function zona(Pedido $pedido): ?string
    {
        $detalle = $pedido->estadoPedidoDetalles
            ->sortByDesc(fn ($d) => $d->nodo_id ? 1 : 0)
            ->first(fn ($d) => $d->nodo_id);

        if ($detalle) {
            $detalle->loadMissing('nodo:nodo_id,descripcion,ciudad');
            $ciudad = trim((string) ($detalle->nodo?->ciudad ?? ''));
            if ($ciudad !== '') {
                return $ciudad;
            }
            $desc = trim((string) ($detalle->nodo?->descripcion ?? ''));
            if ($desc !== '') {
                return $desc;
            }
        }

        return null;
    }
}
