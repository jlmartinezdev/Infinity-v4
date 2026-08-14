<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Staff\StaffAvisoEnCaminoService;
use Illuminate\Http\Request;

class StaffAvisoEnCaminoController extends ApiController
{
    public function __construct(
        private readonly StaffAvisoEnCaminoService $avisos,
    ) {}

    /**
     * POST /api/v1/staff/avisos/en-camino
     *
     * Envía aviso WhatsApp desde el número oficial (Cloud API).
     * Body: tipo (visita|instalacion), recurso_id, lat?, lng?
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', 'in:visita,instalacion'],
            'recurso_id' => ['required', 'integer', 'min:1'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->avisos->enviar(
            $request->user(),
            (string) $validated['tipo'],
            (int) $validated['recurso_id'],
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        if (! ($result['ok'] ?? false)) {
            return $this->fail(
                (string) ($result['message'] ?? 'No se pudo enviar el aviso.'),
                (int) ($result['status'] ?? 400)
            );
        }

        return $this->ok($result['data']);
    }
}
