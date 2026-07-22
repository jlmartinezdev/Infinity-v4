<?php

namespace App\Http\Controllers;

use App\Helpers\MapsUrlHelper;
use App\Models\Cliente;
use App\Models\CedulaPadron;
use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\Nodo;
use App\Models\PoolIpAsignada;
use App\Models\Servicio;
use App\Models\Ticket;
use App\Services\ClientePortalUserService;
use App\Services\MikroTikService;
use App\Services\Monitoreo\ServicioPingMonitoreoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar clientes.
     */
    public function index(Request $request)
    {
        // Cargamos el listado completo para que Vue haga el filtrado incremental en cliente.
        $clientes = Cliente::query()
            ->whereIn('estado', ['activo', 'inactivo', 'suspendido'])
            ->with(['servicios.plan', 'servicios.pool'])
            ->orderBy('cliente_id', 'desc')
            ->get();

        return view('clientes.index', compact('clientes'));
    }

    /**
     * Mapa de clientes con al menos un servicio activo y ubicación GPS válida.
     */
    public function mapaActivos(Request $request, ServicioPingMonitoreoService $pingMonitoreo)
    {
        $nodoId = $request->filled('nodo_id') ? (int) $request->input('nodo_id') : null;
        if ($nodoId !== null && $nodoId <= 0) {
            $nodoId = null;
        }

        $pingEstadoFiltro = (string) $request->input('ping_estado', '');
        $pingEstadosValidos = ['online', 'offline', 'mixed', 'unknown'];
        if (! in_array($pingEstadoFiltro, $pingEstadosValidos, true)) {
            $pingEstadoFiltro = '';
        }

        $clientesQuery = Cliente::query()
            ->whereHas('servicios', function ($q) use ($nodoId) {
                $q->where('estado', Servicio::ESTADO_ACTIVO);
                if ($nodoId !== null) {
                    $q->enNodo($nodoId);
                }
            })
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('url_ubicacion')->where('url_ubicacion', '!=', '');
                })->orWhereHas('pedidos', function ($p) {
                    $p->where(function ($p2) {
                        $p2->where(function ($p3) {
                            $p3->whereNotNull('maps_gps')->where('maps_gps', '!=', '');
                        })->orWhere(function ($p3) {
                            $p3->whereNotNull('lat')->whereNotNull('lon');
                        });
                    });
                });
            })
            ->with(['pedidos' => function ($q) {
                $q->where(function ($p) {
                    $p->where(function ($p2) {
                        $p2->whereNotNull('maps_gps')->where('maps_gps', '!=', '');
                    })->orWhere(function ($p2) {
                        $p2->whereNotNull('lat')->whereNotNull('lon');
                    });
                })
                    ->orderByDesc('pedido_id')
                    ->limit(1)
                    ->select(['pedido_id', 'cliente_id', 'maps_gps', 'lat', 'lon']);
            }])
            ->select(['cliente_id', 'nombre', 'apellido', 'url_ubicacion'])
            ->orderBy('nombre');

        $clientes = $clientesQuery->get();

        $infoServicioQuery = DB::table('servicios as s')
            ->leftJoin('planes as p', 'p.plan_id', '=', 's.plan_id')
            ->leftJoin('tipos_tecnologias as tt', 'tt.tecnologia_id', '=', 'p.tecnologia_id')
            ->whereIn('s.cliente_id', $clientes->pluck('cliente_id'))
            ->where('s.estado', Servicio::ESTADO_ACTIVO)
            ->whereNotNull('p.nombre');

        if ($nodoId !== null) {
            $servicioIdsNodo = Servicio::query()
                ->whereIn('cliente_id', $clientes->pluck('cliente_id'))
                ->where('estado', Servicio::ESTADO_ACTIVO)
                ->enNodo($nodoId)
                ->pluck('servicio_id');
            $infoServicioQuery->whereIn('s.servicio_id', $servicioIdsNodo);
        }

        $infoServicioPorCliente = $infoServicioQuery
            ->selectRaw("
                s.cliente_id,
                GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ', ') as planes,
                GROUP_CONCAT(DISTINCT tt.descripcion ORDER BY tt.descripcion SEPARATOR ', ') as tecnologias
            ")
            ->groupBy('s.cliente_id')
            ->get()
            ->keyBy('cliente_id');

        $puntos = [];
        $sinCoordenadas = 0;
        $clienteIdsEnMapa = [];
        foreach ($clientes as $cliente) {
            $coords = $this->coordsMapaCliente($cliente);
            if ($coords['lat'] === null || $coords['lon'] === null) {
                $sinCoordenadas++;

                continue;
            }
            $clienteIdsEnMapa[] = $cliente->cliente_id;
            $puntos[] = [
                'cliente_id' => $cliente->cliente_id,
                'lat' => $coords['lat'],
                'lon' => $coords['lon'],
                'nombre' => trim(implode(' ', array_filter([$cliente->nombre, $cliente->apellido]))),
                'plan' => $infoServicioPorCliente[$cliente->cliente_id]->planes ?? null,
                'tecnologia' => $infoServicioPorCliente[$cliente->cliente_id]->tecnologias ?? null,
                'url_ubicacion' => trim((string) ($cliente->url_ubicacion ?? '')),
            ];
        }

        $estadosPing = $pingMonitoreo->estadosPorClientes($clienteIdsEnMapa, $nodoId);
        foreach ($puntos as $i => $punto) {
            $ping = $estadosPing[$punto['cliente_id']] ?? null;
            $puntos[$i]['ping_estado'] = $ping['estado'] ?? 'unknown';
            $puntos[$i]['ping_en_linea'] = $ping['en_linea'] ?? 0;
            $puntos[$i]['ping_total'] = $ping['total'] ?? 0;
            $puntos[$i]['ping_latencia_ms'] = $ping['latencia_ms'] ?? null;
            $puntos[$i]['ping_verificado_at'] = $ping['verificado_at'] ?? null;
        }

        $resumenPing = $pingMonitoreo->resumenDesdeEstados($estadosPing);
        $totalEnMapa = count($puntos);
        $enMapaVisibles = $pingEstadoFiltro !== ''
            ? count(array_filter(
                $puntos,
                fn (array $p) => ($p['ping_estado'] ?? 'unknown') === $pingEstadoFiltro
            ))
            : $totalEnMapa;
        $nodos = Nodo::query()->orderBy('descripcion')->get(['nodo_id', 'descripcion']);
        $nodoSeleccionado = $nodoId !== null
            ? $nodos->firstWhere('nodo_id', $nodoId)
            : null;

        return view('clientes.mapa-activos', [
            'puntos' => $puntos,
            'statsMapa' => [
                'total_candidatos' => $clientes->count(),
                'en_mapa' => $enMapaVisibles,
                'en_mapa_total' => $totalEnMapa,
                'sin_coordenadas' => $sinCoordenadas,
                'ping_online' => $resumenPing['online'],
                'ping_offline' => $resumenPing['offline'],
                'ping_mixed' => $resumenPing['mixed'],
                'ping_unknown' => $resumenPing['unknown'],
            ],
            'nodos' => $nodos,
            'nodoIdSeleccionado' => $nodoId,
            'nodoSeleccionado' => $nodoSeleccionado,
            'pingEstadoFiltro' => $pingEstadoFiltro !== '' ? $pingEstadoFiltro : null,
            'pingEstadoFiltroLabels' => [
                'online' => 'Online',
                'offline' => 'Sin respuesta',
                'mixed' => 'Parcial',
                'unknown' => 'Sin ping',
            ],
            'googleMapsApiKey' => config('services.google.maps_key'),
            'urlPingEstados' => route('clientes.mapa-activos.ping-estados'),
            'urlEjecutarPing' => route('clientes.mapa-activos.ejecutar-ping'),
            'pingRefrescoSegundos' => (int) config('monitoreo.mapa_refresco_segundos', 60),
        ]);
    }

    /**
     * Ejecuta ping manual (todos los servicios o solo los de un nodo).
     */
    public function mapaActivosEjecutarPing(Request $request, ServicioPingMonitoreoService $pingMonitoreo)
    {
        if (! config('monitoreo.habilitado', true)) {
            $message = 'Monitoreo ping deshabilitado (MONITOREO_PING_HABILITADO=false).';

            if ($request->wantsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 422);
            }

            return back()->with('warning', $message);
        }

        $validated = $request->validate([
            'nodo_id' => ['nullable', 'integer', 'exists:nodos,nodo_id'],
        ]);

        $nodoId = isset($validated['nodo_id']) ? (int) $validated['nodo_id'] : null;
        $stats = $pingMonitoreo->ejecutarRonda(null, $nodoId);

        $nodoLabel = $nodoId
            ? (Nodo::query()->where('nodo_id', $nodoId)->value('descripcion') ?? "nodo #{$nodoId}")
            : 'todos los nodos';

        $message = sprintf(
            'Ping ejecutado (%s): %d procesados, %d en línea, %d sin respuesta.',
            $nodoLabel,
            $stats['procesados'],
            $stats['en_linea'],
            $stats['sin_respuesta']
        );

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'stats' => $stats,
                'nodo_id' => $nodoId,
            ]);
        }

        $redirectParams = array_filter([
            'nodo_id' => $nodoId,
            'ping_estado' => $request->input('ping_estado'),
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('clientes.mapa-activos', $redirectParams)
            ->with('success', $message);
    }

    /**
     * JSON con estados de ping para refrescar el mapa sin recargar la página.
     */
    public function mapaActivosPingEstados(Request $request, ServicioPingMonitoreoService $pingMonitoreo)
    {
        $ids = $request->input('cliente_ids', []);
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids)))) : [];

        $nodoId = $request->filled('nodo_id') ? (int) $request->input('nodo_id') : null;
        if ($nodoId !== null && $nodoId <= 0) {
            $nodoId = null;
        }

        if ($ids === []) {
            return response()->json(['estados' => [], 'resumen' => ['online' => 0, 'offline' => 0, 'mixed' => 0, 'unknown' => 0]]);
        }

        $estados = $pingMonitoreo->estadosPorClientes($ids, $nodoId);

        return response()->json([
            'estados' => $estados,
            'resumen' => $pingMonitoreo->resumenDesdeEstados($estados),
            'actualizado_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{lat: float|null, lon: float|null}
     */
    private function coordsMapaCliente(Cliente $cliente): array
    {
        $url = trim((string) ($cliente->url_ubicacion ?? ''));
        if ($url !== '') {
            $coords = MapsUrlHelper::extractLatLon($url);
            if ($coords['lat'] !== null && $coords['lon'] !== null) {
                return $coords;
            }
        }

        $pedido = $cliente->relationLoaded('pedidos') ? $cliente->pedidos->first() : null;
        if ($pedido !== null) {
            $lat = $pedido->lat !== null ? (float) $pedido->lat : null;
            $lon = $pedido->lon !== null ? (float) $pedido->lon : null;
            if ($lat !== null && $lon !== null && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                return ['lat' => $lat, 'lon' => $lon];
            }

            $mapsGps = trim((string) ($pedido->maps_gps ?? ''));
            if ($mapsGps !== '') {
                return MapsUrlHelper::extractLatLon($mapsGps);
            }
        }

        return ['lat' => null, 'lon' => null];
    }

    /**
     * Texto de plan(es) para el mapa: primero servicios activos, si no hay, cualquier servicio con plan.
     */
    private function etiquetaPlanesCliente(Cliente $cliente): ?string
    {
        $activos = $cliente->servicios->filter(fn (Servicio $s) => $s->estado === Servicio::ESTADO_ACTIVO);
        $nombres = $activos->map(fn (Servicio $s) => $s->plan?->nombre)->filter()->unique()->values();
        if ($nombres->isNotEmpty()) {
            return $nombres->implode(', ');
        }
        $cualquiera = $cliente->servicios->map(fn (Servicio $s) => $s->plan?->nombre)->filter()->unique()->values();

        return $cualquiera->isNotEmpty() ? $cualquiera->implode(', ') : null;
    }

    /**
     * Vista de detalle general: datos del cliente, servicios, facturación, historial de cobros y de tickets.
     */
    public function detalle(Cliente $cliente)
    {
        $cliente->load(['servicios.plan', 'servicios.pool']);

        $saldoPendienteExpr = FacturaInterna::sqlSaldoPendienteExpr();

        $totalPendientePago = (float) (FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoPendienteExpr.' > 0.009')
            ->selectRaw('SUM('.$saldoPendienteExpr.') as total')
            ->value('total') ?? 0);

        $totalSaldoFavor = (float) $cliente->servicios->sum(
            fn (Servicio $s) => (float) ($s->saldo_a_favor ?? 0)
        );

        $facturasInternas = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $cobros = Cobro::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->with(['usuario', 'facturaInternas'])
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $tickets = Ticket::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->with(['ticketAsunto', 'usuario', 'asignado'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(60)
            ->get();

        $esAdministrador = (bool) auth()->user()?->esAdministrador();

        return view('clientes.detalle', compact(
            'cliente',
            'cobros',
            'tickets',
            'totalPendientePago',
            'totalSaldoFavor',
            'facturasInternas',
            'esAdministrador',
        ));
    }

    /**
     * Ajuste manual del saldo a favor por servicio (solo administradores).
     */
    public function actualizarSaldoAFavor(Request $request, Cliente $cliente)
    {
        if (! $request->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden ajustar el saldo a favor.');
        }

        $cliente->load('servicios');
        $servicioIds = $cliente->servicios->pluck('servicio_id')->map(fn ($id) => (int) $id)->all();

        if ($servicioIds === []) {
            return redirect()
                ->route('clientes.detalle', $cliente)
                ->with('error', 'El cliente no tiene servicios para asignar saldo a favor.');
        }

        $validated = $request->validate([
            'saldos' => ['required', 'array'],
            'saldos.*' => ['required', 'numeric', 'min:0'],
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $saldosEnviados = collect($validated['saldos'])->mapWithKeys(fn ($monto, $id) => [(int) $id => round((float) $monto, 2)]);

        $idsInvalidos = $saldosEnviados->keys()->diff($servicioIds);
        if ($idsInvalidos->isNotEmpty()) {
            return redirect()
                ->route('clientes.detalle', $cliente)
                ->withInput()
                ->with('error', 'Uno o más servicios no pertenecen a este cliente.');
        }

        if ($saldosEnviados->keys()->sort()->values()->all() !== collect($servicioIds)->sort()->values()->all()) {
            return redirect()
                ->route('clientes.detalle', $cliente)
                ->withInput()
                ->with('error', 'Debe indicar el saldo a favor de todos los servicios del cliente.');
        }

        DB::transaction(function () use ($cliente, $saldosEnviados) {
            foreach ($saldosEnviados as $servicioId => $monto) {
                Servicio::query()
                    ->where('servicio_id', $servicioId)
                    ->where('cliente_id', $cliente->cliente_id)
                    ->update(['saldo_a_favor' => $monto]);
            }
        });

        $mensaje = 'Saldo a favor actualizado correctamente.';
        if (! empty($validated['motivo'])) {
            $mensaje .= ' Motivo: '.$validated['motivo'];
        }

        return redirect()->route('clientes.detalle', $cliente)->with('success', $mensaje);
    }

    /**
     * Panel de acciones rápidas para el cliente (enlaces a tickets, facturación, servicios, edición).
     */
    public function acciones(Cliente $cliente)
    {
        $cliente->load(['servicios.plan', 'servicios.pool']);

        return view('clientes.acciones', compact('cliente'));
    }

    /**
     * Verificar si una cédula ya está registrada como cliente (API para el formulario).
     */
    public function verificarCedula(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string'],
        ]);

        $cliente = Cliente::where('cedula', $request->cedula)->first();

        if (!$cliente) {
            return response()->json(['existe' => false]);
        }

        $activado = false;
        if ($cliente->estado === 'solo_pedido') {
            $cliente->update(['estado' => 'activo']);
            $activado = true;
        }

        return response()->json([
            'existe' => true,
            'activado' => $activado,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
            ],
        ]);
    }

    /**
     * Buscar en tabla temp por nombre del cliente (para actualizar datos desde temp).
     */
    public function buscarTemp(Request $request)
    {
        $request->validate(['nombre' => ['required', 'string', 'max:200']]);
        $nombre = trim($request->nombre);
        if (strlen($nombre) < 2) {
            return response()->json(['encontrados' => []]);
        }
        try {
            $registros = DB::table('temp')
                ->where('nombre', 'like', '%' . $nombre . '%')
                ->limit(10)
                ->get(['celular', 'cedula', 'direccion', 'nombre', 'latitud', 'longitud']);
        } catch (\Throwable $e) {
            return response()->json(['encontrados' => [], 'error' => 'Tabla temp no disponible']);
        }
        return response()->json(['encontrados' => $registros]);
    }

    /**
     * Consultar RUC en ruc.com.py, marcar cliente como consultado y
     * actualizar cédula con el RUC encontrado. Si está ACTIVO, también nombre/apellido.
     */
    public function consultarRuc(Request $request, Cliente $cliente)
    {
        if ($cliente->ruc_consultado) {
            return response()->json([
                'encontrado' => false,
                'actualizado' => false,
                'ya_consultado' => true,
                'resultados' => [],
                'message' => 'Este cliente ya fue consultado previamente.',
                'cliente' => $this->clienteRucPayload($cliente),
            ]);
        }

        $termino = $this->normalizarTerminoConsultaRuc((string) $cliente->cedula);
        if ($termino === '' || strlen(preg_replace('/\D+/', '', $termino) ?? '') < 5) {
            return response()->json([
                'encontrado' => false,
                'actualizado' => false,
                'resultados' => [],
                'message' => 'El cliente no tiene un documento válido para consultar (mínimo 5 dígitos).',
            ], 422);
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'Infinity-v4/1.0',
                ])
                ->get('https://ruc.com.py/api/consulta-ruc', [
                    'termino' => $termino,
                ]);

            if (! $response->successful()) {
                return response()->json([
                    'encontrado' => false,
                    'actualizado' => false,
                    'resultados' => [],
                    'message' => 'No se pudo consultar el servicio de RUC (HTTP '.$response->status().').',
                ], 502);
            }

            $payload = $response->json();
            $rows = $this->extraerResultadosConsultaRuc($payload);
            $resultados = array_map(static fn (array $row) => [
                'ruc' => isset($row['ruc']) ? trim((string) $row['ruc']) : null,
                'razon_social' => isset($row['razon_social']) ? trim((string) $row['razon_social']) : null,
                'estado' => isset($row['estado']) ? trim((string) $row['estado']) : null,
            ], $rows);

            // Preferir ACTIVO; si no hay, usar el primer RUC válido encontrado.
            $elegido = collect($resultados)->first(
                static fn (array $row) => strtoupper((string) ($row['estado'] ?? '')) === 'ACTIVO'
                    && filled($row['ruc'] ?? null)
            ) ?? collect($resultados)->first(
                static fn (array $row) => filled($row['ruc'] ?? null)
            );

            $actualizado = false;
            $rucActualizado = false;
            $nombreActualizado = false;
            $bloqueadoPorDuplicado = false;
            $updates = ['ruc_consultado' => true];
            $estadoElegido = $elegido ? strtoupper((string) ($elegido['estado'] ?? '')) : '';

            if ($elegido) {
                $ruc = trim((string) $elegido['ruc']);
                $existeOtro = Cliente::query()
                    ->where('cliente_id', '!=', $cliente->cliente_id)
                    ->where(function ($q) use ($ruc) {
                        $q->where('cedula', $ruc)
                            ->orWhere('cedula', preg_replace('/\D+/', '', $ruc));
                    })
                    ->exists();

                if ($existeOtro) {
                    $bloqueadoPorDuplicado = true;
                } elseif ($ruc !== '' && $ruc !== (string) $cliente->cedula) {
                    $updates['cedula'] = $ruc;
                    $rucActualizado = true;
                    $actualizado = true;
                }

                // Nombre/apellido solo si está ACTIVO
                if ($estadoElegido === 'ACTIVO') {
                    [$nombre, $apellido] = $this->partirRazonSocial((string) ($elegido['razon_social'] ?? ''));
                    if ($nombre !== '') {
                        $updates['nombre'] = $nombre;
                        $updates['apellido'] = $apellido !== '' ? $apellido : null;
                        $nombreActualizado = true;
                        $actualizado = true;
                    }
                }
            }

            $cliente->fill($updates);
            $cliente->save();

            $message = match (true) {
                $bloqueadoPorDuplicado => 'Se encontró RUC, pero otro cliente ya usa ese documento. No se actualizó la cédula.',
                $rucActualizado && $nombreActualizado => 'RUC activo: se actualizaron documento y nombre del cliente.',
                $rucActualizado && $estadoElegido === 'ACTIVO' => 'RUC activo: se actualizó el documento del cliente.',
                $rucActualizado => 'Se actualizó el RUC del cliente (estado: '.($elegido['estado'] ?? 'sin estado').').',
                count($resultados) > 0 => 'Consulta realizada. No fue necesario actualizar el documento.',
                default => 'Consulta realizada. No se encontró RUC para este documento.',
            };

            return response()->json([
                'encontrado' => count($resultados) > 0,
                'actualizado' => $actualizado,
                'termino' => $termino,
                'resultados' => $resultados,
                'rateLimit' => is_array($payload['rateLimit'] ?? null) ? $payload['rateLimit'] : null,
                'message' => $message,
                'cliente' => $this->clienteRucPayload($cliente->fresh()),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'encontrado' => false,
                'actualizado' => false,
                'resultados' => [],
                'message' => 'Error al consultar RUC: '.$e->getMessage(),
            ], 502);
        }
    }

    /**
     * Normaliza el término de búsqueda: usa base sin DV.
     * Evita buscar "44169019" (DV pegado), que la API no reconoce.
     */
    private function normalizarTerminoConsultaRuc(string $cedula): string
    {
        $cedula = trim($cedula);
        if (preg_match('/^(\d{5,10})-(\d)$/', $cedula, $m)) {
            return $m[1];
        }

        return preg_replace('/\D+/', '', $cedula) ?? '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extraerResultadosConsultaRuc(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $rows = $payload['data'] ?? $payload;
        if (! is_array($rows)) {
            return [];
        }

        // Formato nuevo: { data: [...], rateLimit: {...} }
        if (array_is_list($rows)) {
            return array_values(array_filter($rows, 'is_array'));
        }

        // Compatibilidad con respuesta plana de un solo registro
        if (isset($rows['ruc']) || isset($rows['razon_social'])) {
            return [$rows];
        }

        return [];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function partirRazonSocial(string $razonSocial): array
    {
        $razonSocial = trim(preg_replace('/\s+/', ' ', $razonSocial) ?? '');
        if ($razonSocial === '') {
            return ['', ''];
        }

        // Personas jurídicas: dejar razón social completa en nombre
        if (preg_match('/\b(S\.?\s*A\.?|S\.?\s*R\.?\s*L\.?|E\.?\s*A\.?\s*S\.?|SOCIEDAD|COOPERATIVA)\b/iu', $razonSocial)) {
            return [mb_substr($razonSocial, 0, 100), ''];
        }

        $partes = explode(' ', $razonSocial);
        $total = count($partes);

        if ($total === 1) {
            return [mb_substr($partes[0], 0, 100), ''];
        }

        if ($total === 2) {
            return [
                mb_substr($partes[1], 0, 100),
                mb_substr($partes[0], 0, 100),
            ];
        }

        // Formato habitual SET: APELLIDOS NOMBRES (2 apellidos + resto nombres)
        $apellido = mb_substr(implode(' ', array_slice($partes, 0, 2)), 0, 100);
        $nombre = mb_substr(implode(' ', array_slice($partes, 2)), 0, 100);

        return [$nombre, $apellido];
    }

    /**
     * @return array{cliente_id: int, cedula: string|null, nombre: string|null, apellido: string|null, ruc_consultado: bool}
     */
    private function clienteRucPayload(Cliente $cliente): array
    {
        return [
            'cliente_id' => $cliente->cliente_id,
            'cedula' => $cliente->cedula,
            'nombre' => $cliente->nombre,
            'apellido' => $cliente->apellido,
            'ruc_consultado' => (bool) $cliente->ruc_consultado,
        ];
    }

    /**
     * Actualizar cliente con datos de temp (cedula, celular→telefono, direccion, url_ubicacion desde lat/lon).
     */
    public function actualizarDesdeTemp(Request $request, Cliente $cliente)
    {
        $lat = $this->normalizarCoordenada($request->input('latitud'));
        $lon = $this->normalizarCoordenada($request->input('longitud'));
        $request->merge([
            'latitud' => ($lat === '' || $lat === null) ? null : (is_numeric($lat) ? (float) $lat : null),
            'longitud' => ($lon === '' || $lon === null) ? null : (is_numeric($lon) ? (float) $lon : null),
        ]);

        $validated = $request->validate([
            'cedula' => ['nullable', 'string', 'max:20'],
            'celular' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'latitud' => ['nullable', 'numeric'],
            'longitud' => ['nullable', 'numeric'],
        ]);

        $updates = [];
        if (! empty(trim((string) ($validated['cedula'] ?? '')))) {
            $updates['cedula'] = trim($validated['cedula']);
        }
        if (! empty(trim((string) ($validated['celular'] ?? '')))) {
            $updates['telefono'] = trim($validated['celular']);
        }
        if (! empty(trim((string) ($validated['direccion'] ?? '')))) {
            $updates['direccion'] = trim($validated['direccion']);
        }
        $lat = $validated['latitud'] ?? null;
        $lon = $validated['longitud'] ?? null;
        if (is_numeric($lat) && is_numeric($lon) && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
            $updates['url_ubicacion'] = 'https://www.google.com/maps?q=' . $lat . ',' . $lon;
        }

        if (! empty($updates)) {
            $cliente->update($updates);
        }

        return response()->json(['success' => true, 'cliente' => $cliente->fresh()]);
    }

    /**
     * Normaliza coordenada (ej: -26.531.725 → -26.531725).
     * Elimina el punto en posición 4 desde el final (separador de miles incorrecto).
     */
    private function normalizarCoordenada(mixed $valor): ?string
    {
        if ($valor === '' || $valor === null) {
            return null;
        }
        $s = trim((string) $valor);
        if ($s === '') {
            return null;
        }
        $len = strlen($s);
        if ($len >= 4 && $s[$len - 4] === '.') {
            $s = substr($s, 0, $len - 4) . substr($s, $len - 3);
        }
        return $s;
    }

    /**
     * Buscar clientes por nombre, apellido o cédula (JSON para autocompletado).
     */
    public function buscar(Request $request)
    {
        $q = $request->get('q', '');
        $q = trim($q);
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
     * Crear cliente por cédula buscando en padrón (API para editar pedido).
     * Si la cédula ya está cargada como cliente, devuelve ese cliente.
     * Si no existe, consulta padrón y crea el cliente (estado solo_pedido).
     */
    public function crearDesdePadron(Request $request)
    {
        $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
        ]);

        $cedulaNum = $request->cedula;

        $cliente = Cliente::where('cedula', $cedulaNum)->first();
        if ($cliente) {
            return response()->json([
                'existe' => true,
                'cliente' => [
                    'cliente_id' => $cliente->cliente_id,
                    'cedula' => $cliente->cedula,
                    'nombre' => $cliente->nombre,
                    'apellido' => $cliente->apellido,
                    'telefono' => $cliente->telefono,
                ],
            ]);
        }

        try {
            $padron = CedulaPadron::buscarPorCedula($cedulaNum);
        } catch (\Exception $e) {
            return response()->json([
                'encontrado' => false,
                'error' => 'Error al consultar el padrón: ' . $e->getMessage(),
            ], 500);
        }

        if (!$padron) {
            return response()->json([
                'encontrado' => false,
                'mensaje' => 'No se encontró en el padrón',
            ], 404);
        }

        $nombre = trim($padron->NOMBRE ?? '');
        $apellido = trim($padron->APELLIDO ?? '');
        $direccion = trim(implode(' ', array_filter([$padron->DIREC ?? '', $padron->DOMIC ?? ''])));

        $cliente = Cliente::create([
            'cedula' => $padron->NRODOC ?? $cedulaNum,
            'nombre' => $nombre ?: 'Sin nombre',
            'apellido' => $apellido ?: null,
            'direccion' => $direccion ?: null,
            'estado' => 'solo_pedido',
        ]);

        return response()->json([
            'creado' => true,
            'cliente' => [
                'cliente_id' => $cliente->cliente_id,
                'cedula' => $cliente->cedula,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'telefono' => $cliente->telefono,
            ],
        ]);
    }

    /**
     * Formulario crear cliente.
     */
    public function create()
    {
        return view('clientes.create');
    }

    /**
     * Guardar nuevo cliente.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20', Rule::unique('clientes', 'cedula')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'url_ubicacion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'in:activo,inactivo,suspendido'],
        ]);

        $cliente = Cliente::create($validated);

        try {
            app(ClientePortalUserService::class)->syncParaCliente($cliente, true);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente creado correctamente.');
    }

    /**
     * Formulario editar cliente.
     */
    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar cliente.
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20', Rule::unique('clientes', 'cedula')->ignore($cliente->cliente_id, 'cliente_id')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string'],
            'url_ubicacion' => ['nullable', 'string', 'max:500'],
            'estado' => ['required', 'string', 'in:activo,inactivo,suspendido'],
        ]);

        $cedulaAnterior = (string) $cliente->cedula;
        $cliente->update($validated);

        try {
            $resetPass = ClientePortalUserService::normalizarDocumento($cedulaAnterior)
                !== ClientePortalUserService::normalizarDocumento($validated['cedula']);
            app(ClientePortalUserService::class)->syncParaCliente($cliente->fresh(), $resetPass);
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado correctamente.');
    }

    /**
     * Eliminar cliente y todos los registros relacionados en cascada.
     */
    public function destroy(Cliente $cliente, MikroTikService $mikrotik)
    {
        $cliente->load(['servicios.pool.router']);
        $avisosMikrotik = [];
        foreach ($cliente->servicios as $servicio) {
            $r = $mikrotik->quitarPppoeAlBorrarServicio($servicio, 'clientes.destroy');
            if ($r['aviso']) {
                $avisosMikrotik[] = $r['aviso'];
            }
        }

        DB::transaction(function () use ($cliente) {
            $clienteId = $cliente->cliente_id;

            // 1. Cobros
            $cliente->cobros()->delete();

            // 2. Facturas internas (los detalles se eliminan por cascade en la BD)
            $cliente->facturaInternas()->delete();

            // 3. Servicios (liberar IPs del pool primero; MikroTik PPPoE ya limpiado antes de la transacción)
            foreach ($cliente->servicios as $servicio) {
                if ($servicio->ip && $servicio->pool_id) {
                    PoolIpAsignada::where('pool_id', $servicio->pool_id)
                        ->where('ip', $servicio->ip)
                        ->update(['estado' => 'disponible']);
                }
            }
            $cliente->servicios()->delete();

            // 4. Pedidos (eliminar estado_pedido_detalles primero)
            foreach ($cliente->pedidos as $pedido) {
                $pedido->estadoPedidoDetalles()->delete();
            }
            $cliente->pedidos()->delete();

            // 5. Agenda
            $cliente->agendas()->delete();

            // 6. Tickets
            $cliente->tickets()->delete();

            // 7. Facturas electrónicas (y sus detalles)
            foreach ($cliente->facturas as $factura) {
                $factura->detalles()->delete();
            }
            $cliente->facturas()->delete();

            // 8. Cliente
            $cliente->delete();
        });

        $mensaje = 'Cliente y registros relacionados eliminados correctamente.';
        if ($avisosMikrotik !== []) {
            $mensaje .= ' '.implode(' ', $avisosMikrotik).' Quedó registrado para reintento automático en operaciones MikroTik pendientes.';

            return redirect()->route('clientes.index')->with('warning', $mensaje);
        }

        return redirect()->route('clientes.index')->with('success', $mensaje);
    }
}
