<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Services\Staff\StaffRecorridoService;
use App\Services\Staff\StaffUbicacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class StaffMapaController extends Controller
{
    public function __construct(
        private readonly StaffUbicacionService $ubicaciones,
        private readonly StaffRecorridoService $recorridos,
    ) {}

    public function index(): View
    {
        return view('staff.mapa-tecnicos', [
            'googleMapsApiKey' => config('services.google.maps_key'),
            'urlUbicaciones' => route('staff.mapa-tecnicos.ubicaciones'),
            'urlRecorridos' => route('staff.mapa-tecnicos.recorridos'),
            'urlAjusteRecorrido' => route('staff.mapa-tecnicos.recorridos.ajuste'),
            'urlClientes' => route('staff.mapa-tecnicos.clientes'),
            'urlPedidos' => route('staff.mapa-tecnicos.pedidos'),
            'urlTickets' => route('staff.mapa-tecnicos.tickets'),
            'pollSegundos' => 15,
        ]);
    }

    public function ubicaciones(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->ubicaciones->listarFlotaPayload(),
            ]);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.ubicaciones', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo cargar la flota de técnicos.',
            ], 500);
        }
    }

    public function recorridos(Request $request): JsonResponse
    {
        $raw = trim((string) $request->query('fecha', ''));
        if ($raw !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return response()->json([
                'success' => false,
                'message' => 'Fecha inválida. Usá AAAA-MM-DD.',
            ], 422);
        }

        try {
            if ($raw === '') {
                $fecha = now()->startOfDay();
            } else {
                $fecha = Carbon::createFromFormat('Y-m-d', $raw);
                if (! $fecha instanceof Carbon || $fecha->format('Y-m-d') !== $raw) {
                    throw new \InvalidArgumentException('Fecha inválida.');
                }
                $fecha = $fecha->startOfDay();
            }
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Fecha inválida. Usá AAAA-MM-DD.',
            ], 422);
        }

        if ($fecha->toDateString() > now()->toDateString()) {
            return response()->json([
                'success' => false,
                'message' => 'La fecha no puede ser futura.',
            ], 422);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->recorridos->payloadFecha($fecha),
            ]);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.recorridos', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudo cargar el recorrido.',
            ], 500);
        }
    }

    public function guardarAjuste(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tecnico_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'traza' => ['required', 'array', 'min:2', 'max:800'],
            'traza.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'traza.*.lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $this->recorridos->guardarAjuste((int) $data['tecnico_id'], $data['fecha'], $data['traza']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.ajuste', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'No se pudo guardar la ruta.'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function borrarAjuste(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tecnico_id' => ['required', 'integer'],
            'fecha' => ['required', 'date_format:Y-m-d'],
        ]);

        try {
            $this->recorridos->borrarAjuste((int) $data['tecnico_id'], $data['fecha']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.ajuste.borrar', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'No se pudo restaurar la ruta.'], 500);
        }

        return response()->json(['success' => true]);
    }

    public function clientes(): JsonResponse
    {
        try {
            return $this->clientesPayload();
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.clientes', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar los clientes en el mapa.',
            ], 500);
        }
    }

    private function clientesPayload(): JsonResponse
    {
        $clientes = Cliente::query()
            ->whereHas('servicios', fn ($q) => $q->where('estado', Servicio::ESTADO_ACTIVO))
            ->with([
                'pedidos' => fn ($q) => $q->latest('pedido_id')->limit(1)
                    ->select(['pedido_id', 'cliente_id', 'maps_gps', 'lat', 'lon']),
                'servicios' => fn ($q) => $q->where('estado', Servicio::ESTADO_ACTIVO)
                    ->with([
                        'plan:plan_id,nombre',
                        'pool.router.nodo:nodo_id,descripcion,ciudad',
                        'cajaNapPuertoActivo.cajaNap:caja_nap_id,nodo_id,descripcion',
                        'cajaNapPuertoActivo.cajaNap.nodo:nodo_id,descripcion,ciudad',
                    ])
                    ->orderBy('servicio_id'),
            ])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('url_ubicacion')->where('url_ubicacion', '!=', '');
                })->orWhereHas('pedidos', function ($p) {
                    $p->where(function ($p2) {
                        $p2->whereNotNull('lat')->whereNotNull('lon')
                            ->orWhere(fn ($p3) => $p3->whereNotNull('maps_gps')->where('maps_gps', '!=', ''));
                    });
                });
            })
            ->select(['cliente_id', 'nombre', 'apellido', 'url_ubicacion', 'direccion', 'cedula'])
            ->limit(2500)
            ->get();

        $puntos = [];
        foreach ($clientes as $cliente) {
            $coords = $this->coordsCliente($cliente);
            if ($coords['lat'] === null || $coords['lng'] === null) {
                continue;
            }
            $nombre = trim(implode(' ', array_filter([$cliente->nombre, $cliente->apellido])));
            $nodoInfo = $this->nodoReferenciaCliente($cliente);
            $puntos[] = [
                'id' => (int) $cliente->cliente_id,
                'nombre' => $nombre !== '' ? $nombre : 'Cliente #'.$cliente->cliente_id,
                'documento' => trim((string) ($cliente->cedula ?? '')),
                'lat' => $coords['lat'],
                'lng' => $coords['lng'],
                'direccion' => trim((string) ($cliente->direccion ?? '')),
                'nodo_id' => $nodoInfo['nodo_id'],
                'nodo' => $nodoInfo['nodo'],
                'zona' => $nodoInfo['zona'],
                'plan' => $nodoInfo['plan'],
                'url' => route('clientes.detalle', $cliente->cliente_id),
            ];
        }

        return response()->json(['success' => true, 'data' => $puntos]);
    }

    public function pedidos(): JsonResponse
    {
        try {
            $pedidos = Pedido::query()
                ->with(['cliente:cliente_id,nombre,apellido'])
                ->whereNotNull('lat')
                ->whereNotNull('lon')
                ->where('estado_instalado', false)
                ->whereDoesntHave('estadoPedidoDetalles', fn ($q) => $q->where('estado', 'D'))
                ->orderByDesc('fecha_pedido')
                ->limit(1500)
                ->get(['pedido_id', 'cliente_id', 'lat', 'lon', 'ubicacion', 'maps_gps']);

            $puntos = $pedidos->map(function (Pedido $p) {
                $nombre = trim(implode(' ', array_filter([
                    $p->cliente?->nombre,
                    $p->cliente?->apellido,
                ])));

                return [
                    'id' => (int) $p->pedido_id,
                    'nombre' => $nombre !== '' ? $nombre : 'Pedido #'.$p->pedido_id,
                    'lat' => (float) $p->lat,
                    'lng' => (float) $p->lon,
                    'direccion' => trim((string) ($p->ubicacion ?? '')),
                    'url' => route('pedidos.edit', $p->pedido_id),
                ];
            })->values()->all();

            return response()->json(['success' => true, 'data' => $puntos]);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.pedidos', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar los pedidos en el mapa.',
            ], 500);
        }
    }

    public function tickets(): JsonResponse
    {
        try {
            $tickets = Ticket::query()
                ->with([
                    'ticketAsunto:id,nombre',
                    'asignado:usuario_id,name',
                    'pedido:pedido_id,lat,lon,maps_gps,ubicacion',
                    'cliente:cliente_id,nombre,apellido,url_ubicacion,direccion',
                    'cliente.pedidos' => fn ($q) => $q->latest('pedido_id')->limit(1)
                        ->select(['pedido_id', 'cliente_id', 'maps_gps', 'lat', 'lon']),
                ])
                ->whereNotIn('estado', Ticket::estadosStaffCerrados())
                ->orderByDesc('id')
                ->limit(1500)
                ->get();

            $puntos = [];
            foreach ($tickets as $ticket) {
                $coords = $this->coordsTicket($ticket);
                if ($coords['lat'] === null || $coords['lng'] === null) {
                    continue;
                }
                $nombre = trim(implode(' ', array_filter([
                    $ticket->cliente?->nombre,
                    $ticket->cliente?->apellido,
                ])));
                $puntos[] = [
                    'id' => (int) $ticket->id,
                    'nombre' => $nombre !== '' ? $nombre : 'Ticket #'.$ticket->id,
                    'asunto' => $ticket->ticketAsunto?->nombre ?: 'Sin asunto',
                    'estado' => Ticket::estados()[$ticket->estado] ?? (string) $ticket->estado,
                    'asignado' => $ticket->asignado?->name,
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'direccion' => trim((string) ($ticket->cliente?->direccion ?? $ticket->pedido?->ubicacion ?? '')),
                    'url' => route('tickets.edit', $ticket->id),
                ];
            }

            return response()->json(['success' => true, 'data' => $puntos]);
        } catch (Throwable $e) {
            Log::error('mapa-tecnicos.tickets', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron cargar los tickets en el mapa.',
            ], 500);
        }
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordsTicket(Ticket $ticket): array
    {
        $pedido = $ticket->pedido;
        if ($pedido) {
            $lat = $pedido->lat !== null ? (float) $pedido->lat : null;
            $lon = $pedido->lon !== null ? (float) $pedido->lon : null;
            if ($lat !== null && $lon !== null) {
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

        if ($ticket->cliente) {
            return $this->coordsCliente($ticket->cliente);
        }

        return ['lat' => null, 'lng' => null];
    }

    /**
     * Nodo / plan de referencia desde servicios activos (router o caja NAP).
     *
     * @return array{nodo_id: int|null, nodo: string|null, zona: string|null, plan: string|null}
     */
    private function nodoReferenciaCliente(Cliente $cliente): array
    {
        $servicios = $cliente->relationLoaded('servicios')
            ? $cliente->servicios
            : collect();

        foreach ($servicios as $servicio) {
            $nodo = $servicio->pool?->router?->nodo
                ?? $servicio->cajaNapPuertoActivo?->cajaNap?->nodo;
            if (! $nodo) {
                continue;
            }
            $ciudad = trim((string) ($nodo->ciudad ?? ''));
            $desc = trim((string) ($nodo->descripcion ?? ''));

            return [
                'nodo_id' => (int) $nodo->nodo_id,
                'nodo' => $desc !== '' ? $desc : null,
                'zona' => $ciudad !== '' ? $ciudad : ($desc !== '' ? $desc : null),
                'plan' => $servicio->plan?->nombre,
            ];
        }

        $primerPlan = $servicios->first()?->plan?->nombre;

        return [
            'nodo_id' => null,
            'nodo' => null,
            'zona' => null,
            'plan' => $primerPlan,
        ];
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function coordsCliente(Cliente $cliente): array
    {
        $url = trim((string) ($cliente->url_ubicacion ?? ''));
        if ($url !== '') {
            $coords = MapsUrlHelper::extractLatLonFromMapsUrl($url, false);
            if ($coords['lat'] !== null && $coords['lon'] !== null) {
                return ['lat' => (float) $coords['lat'], 'lng' => (float) $coords['lon']];
            }
        }

        $pedido = $cliente->relationLoaded('pedidos') ? $cliente->pedidos->first() : null;
        if ($pedido) {
            $lat = $pedido->lat !== null ? (float) $pedido->lat : null;
            $lon = $pedido->lon !== null ? (float) $pedido->lon : null;
            if ($lat !== null && $lon !== null) {
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

        return ['lat' => null, 'lng' => null];
    }
}
