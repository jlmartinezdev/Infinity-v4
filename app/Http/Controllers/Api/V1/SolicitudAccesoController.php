<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cliente;
use App\Models\SolicitudAcceso;
use App\Services\SolicitudAccesoService;
use Illuminate\Http\Request;

class SolicitudAccesoController extends ApiController
{
    public function __construct(
        protected SolicitudAccesoService $service
    ) {}

    /**
     * POST /api/v1/portal/solicitud-alta (público)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cedula' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:200'],
            'whatsapp' => ['required', 'string', 'max:30'],
            'codigo_otp' => ['required', 'string', 'max:10'],
            'frente' => ['required', 'string'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $pendiente = SolicitudAcceso::abiertas()
            ->where('cedula', trim($validated['cedula']))
            ->exists();
        if ($pendiente) {
            return $this->fail('Ya existe una solicitud pendiente con este documento.', 422);
        }

        try {
            $solicitud = $this->service->crear($validated);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $esOtp = str_contains(mb_strtolower($msg), 'código de verificación')
                || str_contains(mb_strtolower($msg), 'codigo de verificacion');

            if ($esOtp) {
                \Illuminate\Support\Facades\Log::info('[solicitud-alta] OTP rechazado', [
                    'whatsapp' => $validated['whatsapp'] ?? null,
                    'codigo_otp' => $validated['codigo_otp'] ?? null,
                ]);
            }

            return $this->fail($msg, $esOtp ? 400 : 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Solicitud recibida correctamente.',
            'data' => [
                'id' => $solicitud->id,
                'estado' => $solicitud->estado,
                'telefono_verificado' => (bool) $solicitud->telefono_verificado,
            ],
        ], 200);
    }

    /**
     * GET /api/v1/staff/solicitudes
     *
     * Filtros: ?status=pendientes|aprobado|historial  (también acepta ?estado=…)
     */
    public function index(Request $request)
    {
        $filtro = strtolower(trim((string) (
            $request->query('status', $request->query('estado', 'pendientes'))
        )));

        $estadoDb = match ($filtro) {
            'pendientes', 'pendiente', '' => SolicitudAcceso::ESTADO_PENDIENTE,
            'pendiente_verificacion', 'verificacion', 'esperando_wa' => SolicitudAcceso::ESTADO_PENDIENTE_VERIFICACION,
            'aprobado', 'aprobada', 'aprobadas' => SolicitudAcceso::ESTADO_APROBADA,
            'rechazado', 'rechazada', 'rechazadas' => SolicitudAcceso::ESTADO_RECHAZADA,
            'historial', 'todos', 'all', 'completadas' => null,
            default => $filtro,
        };

        $query = SolicitudAcceso::query()->orderByDesc('id');
        if ($estadoDb !== null) {
            $query->where('estado', $estadoDb);
        }

        $items = $query->limit(200)->get()->map(fn (SolicitudAcceso $s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'documento' => $s->cedula,
            'fecha' => optional($s->created_at)?->format('Y-m-d H:i:s'),
            'estado' => $s->estado,
            'coincide_bd' => $this->service->clienteCoincidePorDocumento($s->cedula) !== null,
        ]);

        return $this->ok($items);
    }

    /**
     * GET /api/v1/staff/solicitudes/{id}
     *
     * Incluye bloque de pre-aprobación para confirmar si se actualiza celular/ubicación.
     */
    public function show(int $id)
    {
        $solicitud = SolicitudAcceso::findOrFail($id);
        $pre = $this->service->datosPreAprobacion($solicitud);

        return $this->ok(array_merge([
            'id' => $solicitud->id,
            'nombre' => $solicitud->nombre,
            'documento' => $solicitud->cedula,
            'telefono' => $solicitud->whatsapp,
            'direccion' => $solicitud->direccion,
            'latitud' => $solicitud->latitud,
            'longitud' => $solicitud->longitud,
            'frente' => $solicitud->frente_url,
            'estado' => $solicitud->estado,
        ], $pre));
    }

    /**
     * POST /api/v1/staff/solicitudes/{id}/aprobar
     *
     * Body opcional:
     * - cliente_id_vinculacion, documento_corregido, nombre_corregido
     * - actualizar_telefono (bool, default false) — solo con confirmación en app
     * - actualizar_ubicacion (bool, default false) — dirección + mapa
     */
    public function aprobar(Request $request, int $id)
    {
        $validated = $request->validate([
            'cliente_id_vinculacion' => ['nullable', 'integer', 'exists:clientes,cliente_id'],
            'documento_corregido' => ['nullable', 'string', 'max:20'],
            'nombre_corregido' => ['nullable', 'string', 'max:200'],
            'actualizar_telefono' => ['nullable', 'boolean'],
            'actualizar_ubicacion' => ['nullable', 'boolean'],
        ]);

        $solicitud = SolicitudAcceso::findOrFail($id);

        try {
            $result = $this->service->aprobar($solicitud, $request->user(), [
                ...$validated,
                'actualizar_telefono' => $request->boolean('actualizar_telefono'),
                'actualizar_ubicacion' => $request->boolean('actualizar_ubicacion'),
            ]);
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'clave' => $result['clave'],
            'cliente_id' => $result['cliente']->cliente_id,
            'solicitud_id' => $result['solicitud']->id,
            'whatsapp_avisado' => filled($result['solicitud']->whatsapp),
        ], 'Solicitud aprobada y vinculada correctamente');
    }

    /**
     * POST /api/v1/staff/solicitudes/{id}/rechazar
     */
    public function rechazar(Request $request, int $id)
    {
        $validated = $request->validate([
            'motivo' => ['nullable', 'string', 'max:500'],
        ]);

        $solicitud = SolicitudAcceso::findOrFail($id);

        try {
            $result = $this->service->rechazar(
                $solicitud,
                $request->user(),
                $validated['motivo'] ?? null
            );
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'solicitud_id' => $result['solicitud']->id,
            'estado' => $result['solicitud']->estado,
            'whatsapp_avisado' => filled($result['solicitud']->whatsapp),
        ], 'Solicitud rechazada');
    }

    /**
     * GET /api/v1/staff/clientes/resumen
     *
     * Métricas para pantalla APP Clientes (staff).
     */
    public function clientesResumen()
    {
        // Mismo criterio que card "CLIENTES" del dashboard web: solo estado activo.
        $totalClientes = Cliente::query()
            ->where('estado', 'activo')
            ->count();

        // Acceso otorgado = mismas filas que GET /staff/auditoria (solicitudes aprobadas).
        $conApp = SolicitudAcceso::query()
            ->where('estado', SolicitudAcceso::ESTADO_APROBADA)
            ->count();

        // Misma lógica que auditoría filtrando app_activa.
        $appActiva = SolicitudAcceso::query()
            ->where('estado', SolicitudAcceso::ESTADO_APROBADA)
            ->whereHas('cliente', fn ($q) => $q->where('app_activa', true))
            ->count();

        $solicitudesPendientes = SolicitudAcceso::query()
            ->where('estado', SolicitudAcceso::ESTADO_PENDIENTE)
            ->count();

        return $this->ok([
            'total_clientes' => $totalClientes,
            'con_app' => $conApp,
            'app_activa' => $appActiva,
            'solicitudes_pendientes' => $solicitudesPendientes,
            // Aliases aceptados por la app
            'total' => $totalClientes,
            'clientes_total' => $totalClientes,
            'con_acceso' => $conApp,
            'aprobados' => $conApp,
            'activos' => $appActiva,
            'solicitudes' => $solicitudesPendientes,
            'pendientes' => $solicitudesPendientes,
        ]);
    }

    /**
     * GET /api/v1/staff/clientes/buscar?q=
     */
    public function buscarClientes(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 3) {
            return $this->fail('El parámetro q requiere mínimo 3 caracteres.', 422);
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
            ->limit(20)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        $data = $clientes->map(fn (Cliente $c) => [
            'id' => $c->cliente_id,
            'nombre' => trim(($c->nombre ?? '').' '.($c->apellido ?? '')),
            'documento' => $c->cedula,
        ])->values();

        return $this->ok($data);
    }

    /**
     * GET /api/v1/staff/auditoria
     */
    public function auditoria(Request $request)
    {
        $rows = SolicitudAcceso::query()
            ->with(['cliente', 'aprobador:usuario_id,name'])
            ->where('estado', SolicitudAcceso::ESTADO_APROBADA)
            ->orderByDesc('aprobado_at')
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(function (SolicitudAcceso $s) {
                $c = $s->cliente;

                return [
                    'cliente' => $c
                        ? trim(($c->nombre ?? '').' '.($c->apellido ?? ''))
                        : $s->nombre,
                    'documento' => $s->cedula,
                    'fecha_otorgamiento' => optional($c?->fecha_otorgamiento ?? $s->aprobado_at ?? $s->updated_at)?->format('Y-m-d H:i:s'),
                    'aprobado_por' => $c?->aprobado_por ?: $s->aprobador?->name,
                    'ultimo_ingreso' => optional($c?->ultimo_ingreso)?->format('Y-m-d H:i:s'),
                    'dispositivo' => $c?->dispositivo,
                    'app_version' => $c?->app_version,
                    'app_activa' => (bool) ($c?->app_activa ?? false),
                    'fecha_activacion_app' => optional($c?->fecha_activacion_app)?->format('Y-m-d H:i:s'),
                ];
            });

        return $this->ok($rows);
    }
}
