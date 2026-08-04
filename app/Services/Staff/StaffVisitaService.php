<?php

namespace App\Services\Staff;

use App\Helpers\MapsUrlHelper;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StaffVisitaService
{
    /**
     * Visitas = tickets abiertos relevantes para el técnico.
     *
     * @return Collection<int, Ticket>
     */
    public function listarPara(User $user): Collection
    {
        return $this->baseQuery($user)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();
    }

    public function encontrarPara(User $user, int $id): ?Ticket
    {
        return $this->baseQuery($user)->whereKey($id)->first();
    }

    /**
     * Busca visita accesible aunque esté cerrada (para actualizar).
     */
    public function encontrarAccesible(User $user, int $id): ?Ticket
    {
        return $this->accesoQuery($user)->whereKey($id)->first();
    }

    /**
     * @param  array{estado?: string|null, nota_tecnico?: string|null, detalle_tecnico?: string|null}  $data
     */
    public function actualizar(Ticket $ticket, User $user, array $data): Ticket
    {
        if (array_key_exists('estado', $data) && $data['estado'] !== null && $data['estado'] !== '') {
            $estado = Ticket::normalizarEstado((string) $data['estado']);
            if ($estado === null || ! in_array($estado, Ticket::estadosStaffDisponibles(), true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => ['Estado no válido. Use: '.implode(', ', Ticket::estadosStaffDisponibles())],
                ]);
            }
            $ticket->estado = $estado;
            if (in_array($estado, ['resuelto', 'no_realizado', 'cerrado'], true)) {
                $ticket->fecha_cierre = $ticket->fecha_cierre ?? now();
            } else {
                $ticket->fecha_cierre = null;
            }
        }

        if (array_key_exists('nota_tecnico', $data) && $data['nota_tecnico'] !== null) {
            $ticket->nota_tecnico = trim((string) $data['nota_tecnico']) ?: null;
        }
        if (array_key_exists('detalle_tecnico', $data) && $data['detalle_tecnico'] !== null) {
            $ticket->detalle_tecnico = trim((string) $data['detalle_tecnico']) ?: null;
        }

        $ticket->actualizado_por_id = $user->usuario_id;
        if (! $ticket->asignado_id) {
            $ticket->asignado_id = $user->usuario_id;
        }
        $ticket->save();

        return $ticket->fresh() ?? $ticket;
    }

    /**
     * @return array<string, mixed>
     */
    public function toVisitaItem(Ticket $ticket): array
    {
        $ticket->loadMissing([
            'cliente:cliente_id,nombre,apellido,telefono,direccion,url_ubicacion',
            'cliente.pedidos' => fn ($q) => $q->latest('pedido_id')->limit(1)
                ->select(['pedido_id', 'cliente_id', 'maps_gps', 'lat', 'lon']),
            'cliente.servicios' => fn ($q) => $q->where('estado', Servicio::ESTADO_ACTIVO)
                ->select(['servicio_id', 'cliente_id', 'pool_id', 'ip', 'estado']),
            'cliente.servicios.pool.router.nodo:nodo_id,descripcion,ciudad',
            'cliente.servicios.cajaNapPuertoActivo.cajaNap.nodo:nodo_id,descripcion,ciudad',
            'ticketAsunto:id,nombre',
            'usuario:usuario_id,name',
            'asignado:usuario_id,name',
            'pedido:pedido_id,cliente_id,maps_gps,lat,lon',
        ]);

        $cliente = $ticket->cliente;
        $nombreCliente = trim(implode(' ', array_filter([
            $cliente?->nombre,
            $cliente?->apellido,
        ])));

        $asunto = trim((string) ($ticket->ticketAsunto?->nombre ?? ''));
        $problema = trim((string) ($ticket->descripcion ?? ''));
        if ($problema === '') {
            $problema = $asunto;
        }

        $coords = $this->coordsCliente($ticket, $cliente);
        $zona = $this->zonaCliente($cliente);
        $ipCliente = $this->ipCliente($cliente);

        $estadoKey = (string) ($ticket->estado ?? 'pendiente');
        $prioridadKey = (string) ($ticket->prioridad ?? 'media');
        $reportadoKey = (string) ($ticket->reportado_desde ?? '');

        $diagnostico = $ticket->datos_diagnostico;
        $diagnosticoStr = null;
        if (is_array($diagnostico) && $diagnostico !== []) {
            $diagnosticoStr = json_encode($diagnostico, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_string($diagnostico) && $diagnostico !== '') {
            $diagnosticoStr = $diagnostico;
        }

        $fechaAsignacion = $ticket->asignado_id
            ? ($ticket->updated_at ?? $ticket->created_at)
            : $ticket->created_at;

        $asignadoId = $ticket->asignado_id !== null ? (int) $ticket->asignado_id : null;
        $asignadoNombre = trim((string) ($ticket->asignado?->name ?? '')) ?: null;

        return [
            'id' => (int) $ticket->id,
            'cliente' => $nombreCliente !== '' ? $nombreCliente : 'Sin cliente',
            'asunto' => $asunto !== '' ? $asunto : $problema,
            'problema' => $problema,
            'direccion' => trim((string) ($cliente?->direccion ?? '')),
            'zona' => $zona,
            'lat' => $coords['lat'],
            'lng' => $coords['lng'],
            'telefono' => trim((string) ($cliente?->telefono ?? '')),
            'estado' => Ticket::estados()[$estadoKey] ?? ucfirst($estadoKey),
            'prioridad' => Ticket::prioridades()[$prioridadKey] ?? ucfirst($prioridadKey),
            'tipo' => 'reporte',
            'urgencia' => $prioridadKey === 'alta',
            'ip_cliente' => $ipCliente,
            'reportado_desde' => Ticket::reportadoDesdeOpciones()[$reportadoKey]
                ?? ($reportadoKey !== '' ? ucfirst($reportadoKey) : null),
            'creado_por' => trim((string) ($ticket->usuario?->name ?? '')) ?: null,
            'asignado_a' => $asignadoId,
            'tecnico_id' => $asignadoId,
            'usuario_asignado_id' => $asignadoId,
            'asignado_nombre' => $asignadoNombre,
            'fecha_asignacion' => $fechaAsignacion?->format('Y-m-d\TH:i:s'),
            'datos_diagnostico' => $diagnosticoStr,
            'nota_tecnico' => ($n = trim((string) ($ticket->nota_tecnico ?? ''))) !== '' ? $n : null,
            'detalle_tecnico' => ($d = trim((string) ($ticket->detalle_tecnico ?? ''))) !== '' ? $d : null,
            'estados_disponibles' => Ticket::estadosStaffDisponibles(),
            'ultima_actualizacion' => ($ticket->updated_at ?? $ticket->created_at)?->format('Y-m-d\TH:i:s'),
        ];
    }

    /**
     * @return Builder<Ticket>
     */
    private function baseQuery(User $user): Builder
    {
        return $this->accesoQuery($user)
            ->whereNotIn('estado', Ticket::estadosStaffCerrados());
    }

    /**
     * @return Builder<Ticket>
     */
    private function accesoQuery(User $user): Builder
    {
        $user->loadMissing('rol');

        $query = Ticket::query()
            ->with([
                'cliente:cliente_id,nombre,apellido,telefono,direccion,url_ubicacion',
                'cliente.pedidos' => fn ($q) => $q->latest('pedido_id')->limit(1)
                    ->select(['pedido_id', 'cliente_id', 'maps_gps', 'lat', 'lon']),
                'cliente.servicios' => fn ($q) => $q->where('estado', Servicio::ESTADO_ACTIVO)
                    ->select(['servicio_id', 'cliente_id', 'pool_id', 'ip', 'estado']),
                'cliente.servicios.pool.router.nodo:nodo_id,descripcion,ciudad',
                'cliente.servicios.cajaNapPuertoActivo.cajaNap.nodo:nodo_id,descripcion,ciudad',
                'ticketAsunto:id,nombre',
                'usuario:usuario_id,name',
                'asignado:usuario_id,name',
                'pedido:pedido_id,cliente_id,maps_gps,lat,lon',
            ]);

        // Admin / gerente / cajero: todas. Técnico: solo asignadas a él.
        if ($user->puedeVerTodasVisitasStaff()) {
            return $query;
        }

        return $query->where('asignado_id', $user->usuario_id);
    }

    /**
     * Coordenadas del domicilio/cliente (sin resolver URLs cortas en lote).
     *
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordsCliente(Ticket $ticket, ?Cliente $cliente): array
    {
        if ($cliente) {
            $url = trim((string) ($cliente->url_ubicacion ?? ''));
            if ($url !== '') {
                $coords = MapsUrlHelper::extractLatLonFromMapsUrl($url, false);
                if ($coords['lat'] !== null && $coords['lon'] !== null) {
                    return ['lat' => (float) $coords['lat'], 'lng' => (float) $coords['lon']];
                }
            }
        }

        $pedido = $ticket->pedido;
        if (! $pedido && $cliente && $cliente->relationLoaded('pedidos')) {
            $pedido = $cliente->pedidos->first();
        }

        if ($pedido instanceof Pedido) {
            $lat = $pedido->lat !== null ? (float) $pedido->lat : null;
            $lon = $pedido->lon !== null ? (float) $pedido->lon : null;
            if ($lat !== null && $lon !== null && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                return ['lat' => $lat, 'lng' => $lon];
            }

            $mapsGps = trim((string) ($pedido->maps_gps ?? ''));
            if ($mapsGps !== '') {
                $coords = MapsUrlHelper::extractLatLonFromMapsUrl($mapsGps, false);
                if ($coords['lat'] !== null && $coords['lon'] !== null) {
                    return ['lat' => (float) $coords['lat'], 'lng' => (float) $coords['lon']];
                }
            }
        }

        $diag = $ticket->datos_diagnostico;
        if (is_array($diag)
            && isset($diag['latitude'], $diag['longitude'])
            && is_numeric($diag['latitude'])
            && is_numeric($diag['longitude'])
        ) {
            return [
                'lat' => (float) $diag['latitude'],
                'lng' => (float) $diag['longitude'],
            ];
        }

        return ['lat' => null, 'lng' => null];
    }

    private function zonaCliente(?Cliente $cliente): ?string
    {
        if (! $cliente || ! $cliente->relationLoaded('servicios')) {
            return null;
        }

        foreach ($cliente->servicios as $servicio) {
            $nodo = $servicio->pool?->router?->nodo
                ?? $servicio->cajaNapPuertoActivo?->cajaNap?->nodo;
            if (! $nodo) {
                continue;
            }
            $ciudad = trim((string) ($nodo->ciudad ?? ''));
            if ($ciudad !== '') {
                return $ciudad;
            }
            $desc = trim((string) ($nodo->descripcion ?? ''));
            if ($desc !== '') {
                return $desc;
            }
        }

        return null;
    }

    private function ipCliente(?Cliente $cliente): ?string
    {
        if (! $cliente || ! $cliente->relationLoaded('servicios')) {
            return null;
        }

        foreach ($cliente->servicios as $servicio) {
            $ip = trim((string) ($servicio->ip ?? ''));
            if ($ip !== '') {
                return $ip;
            }
        }

        return null;
    }
}
