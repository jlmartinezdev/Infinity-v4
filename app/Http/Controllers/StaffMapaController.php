<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Servicio;
use App\Services\Staff\StaffUbicacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class StaffMapaController extends Controller
{
    public function __construct(
        private readonly StaffUbicacionService $ubicaciones
    ) {}

    public function index(): View
    {
        return view('staff.mapa-tecnicos', [
            'googleMapsApiKey' => config('services.google.maps_key'),
            'urlUbicaciones' => route('staff.mapa-tecnicos.ubicaciones'),
            'urlClientes' => route('staff.mapa-tecnicos.clientes'),
            'urlPedidos' => route('staff.mapa-tecnicos.pedidos'),
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
