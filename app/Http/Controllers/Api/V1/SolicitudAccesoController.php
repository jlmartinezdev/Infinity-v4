<?php

namespace App\Http\Controllers\Api\V1;

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
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'frente' => ['required', 'string'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $pendiente = SolicitudAcceso::pendientes()
            ->where('cedula', trim($validated['cedula']))
            ->exists();
        if ($pendiente) {
            return $this->fail('Ya existe una solicitud pendiente con este documento.', 422);
        }

        try {
            $solicitud = $this->service->crear($validated);
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'id' => $solicitud->id,
            'estado' => $solicitud->estado,
        ], 'Solicitud registrada correctamente', 201);
    }

    /**
     * GET /api/v1/staff/solicitudes
     */
    public function index(Request $request)
    {
        $estado = $request->get('estado', SolicitudAcceso::ESTADO_PENDIENTE);

        $query = SolicitudAcceso::query()->orderByDesc('id');
        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        $items = $query->limit(200)->get()->map(fn (SolicitudAcceso $s) => [
            'id' => $s->id,
            'nombre' => $s->nombre,
            'documento' => $s->cedula,
            'fecha' => optional($s->created_at)?->format('Y-m-d H:i:s'),
            'estado' => $s->estado,
        ]);

        return $this->ok($items);
    }

    /**
     * GET /api/v1/staff/solicitudes/{id}
     */
    public function show(int $id)
    {
        $solicitud = SolicitudAcceso::findOrFail($id);
        $coincide = $this->service->clienteCoincidePorDocumento($solicitud->cedula) !== null;

        return $this->ok([
            'id' => $solicitud->id,
            'nombre' => $solicitud->nombre,
            'documento' => $solicitud->cedula,
            'telefono' => $solicitud->whatsapp,
            'direccion' => $solicitud->direccion,
            'latitud' => $solicitud->latitud,
            'longitud' => $solicitud->longitud,
            'frente' => $solicitud->frente_url,
            'estado' => $solicitud->estado,
            'coincide_bd' => $coincide,
        ]);
    }

    /**
     * POST /api/v1/staff/solicitudes/{id}/aprobar
     */
    public function aprobar(Request $request, int $id)
    {
        $solicitud = SolicitudAcceso::findOrFail($id);

        try {
            $result = $this->service->aprobar($solicitud, $request->user());
        } catch (\RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'clave' => $result['clave'],
            'cliente_id' => $result['cliente']->cliente_id,
            'solicitud_id' => $result['solicitud']->id,
        ], 'Solicitud aprobada y vinculada correctamente');
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
                    'fecha_otorgamiento' => optional($s->aprobado_at ?? $s->updated_at)?->format('Y-m-d H:i:s'),
                    'aprobado_por' => $s->aprobador?->name,
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
