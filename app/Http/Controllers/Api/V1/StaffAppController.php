<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Staff\PlayIntegrityService;
use App\Services\Staff\StaffAuditoriaDispositivosService;
use App\Services\Staff\StaffDashboardService;
use App\Services\Staff\StaffEvidenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffAppController extends ApiController
{
    public function __construct(
        private readonly StaffAuditoriaDispositivosService $auditoria,
        private readonly StaffDashboardService $dashboard,
        private readonly StaffEvidenciaService $evidencias,
        private readonly PlayIntegrityService $integrity,
    ) {}

    /**
     * GET /staff/auditoria — activos / dispositivos (paginado).
     */
    public function auditoria(Request $request): JsonResponse
    {
        $paginator = $this->auditoria->paginar([
            'app_activa' => $request->query('app_activa', 1),
            'q' => $request->query('q'),
            'recencia' => $request->query('recencia'),
            'page' => $request->query('page', 1),
            'per_page' => $request->query('per_page', 50),
        ]);

        return $this->ok([
            'items' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /staff/dashboard — counts del Panel.
     */
    public function dashboard(Request $request): JsonResponse
    {
        return $this->ok($this->dashboard->counts($request->user()));
    }

    public function evidenciaVisita(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'foto' => ['required', 'file', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:500'],
            'client_photo_id' => ['nullable', 'uuid'],
        ]);

        try {
            $data = $this->evidencias->guardarVisita(
                $request->user(),
                $id,
                $validated['foto'],
                $validated['caption'] ?? null,
                $validated['client_photo_id'] ?? null,
            );
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo guardar la evidencia.';

            return $this->fail((string) $msg, 422, $e->errors());
        }

        return $this->ok($data, 'Evidencia guardada');
    }

    public function evidenciaPedido(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'foto' => ['required', 'file', 'image', 'max:8192'],
            'caption' => ['nullable', 'string', 'max:500'],
            'client_photo_id' => ['nullable', 'uuid'],
        ]);

        try {
            $data = $this->evidencias->guardarPedido(
                $request->user(),
                $id,
                $validated['foto'],
                $validated['caption'] ?? null,
                $validated['client_photo_id'] ?? null,
            );
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo guardar la evidencia.';

            return $this->fail((string) $msg, 422, $e->errors());
        }

        return $this->ok($data, 'Evidencia guardada');
    }

    /**
     * GET /staff/integrity/nonce — público (sin Bearer).
     */
    public function integrityNonce(Request $request): JsonResponse
    {
        $data = $this->integrity->emitirNonce($request->ip());

        return $this->ok($data);
    }
}
