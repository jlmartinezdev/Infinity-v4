<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Staff\StaffVisitaService;
use Illuminate\Http\Request;

class StaffVisitaController extends ApiController
{
    public function __construct(
        private readonly StaffVisitaService $visitas
    ) {}

    public function index(Request $request)
    {
        $items = $this->visitas->listarPara($request->user())
            ->map(fn ($ticket) => $this->visitas->toVisitaItem($ticket))
            ->values()
            ->all();

        return $this->ok($items);
    }

    public function show(Request $request, int $id)
    {
        $ticket = $this->visitas->encontrarPara($request->user(), $id);
        if (! $ticket) {
            return $this->fail('Visita no encontrada.', 404);
        }

        return $this->ok($this->visitas->toVisitaItem($ticket));
    }

    public function actualizar(Request $request, int $id)
    {
        $validated = $request->validate([
            'estado' => ['nullable', 'string', 'max:50'],
            'nota_tecnico' => ['nullable', 'string', 'max:500'],
            'detalle_tecnico' => ['nullable', 'string', 'max:5000'],
        ]);

        $tieneEstado = filled($validated['estado'] ?? null);
        $tieneNota = array_key_exists('nota_tecnico', $validated) && $validated['nota_tecnico'] !== null;
        $tieneDetalle = array_key_exists('detalle_tecnico', $validated) && $validated['detalle_tecnico'] !== null;

        if (! $tieneEstado && ! $tieneNota && ! $tieneDetalle) {
            return $this->fail('Indicá al menos estado, nota_tecnico o detalle_tecnico.', 422);
        }

        $ticket = $this->visitas->encontrarAccesible($request->user(), $id);
        if (! $ticket) {
            return $this->fail('Visita no encontrada.', 404);
        }

        $ticket = $this->visitas->actualizar($ticket, $request->user(), $validated);

        return $this->ok($this->visitas->toVisitaItem($ticket), 'ok');
    }
}
