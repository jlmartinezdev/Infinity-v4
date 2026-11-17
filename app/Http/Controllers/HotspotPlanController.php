<?php

namespace App\Http\Controllers;

use App\Models\HotspotPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HotspotPlanController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        HotspotPlan::query()->create($this->validar($request));

        return $this->volver($request, 'Plan de gigas cargado.');
    }

    public function update(Request $request, HotspotPlan $plan): RedirectResponse
    {
        $plan->update($this->validar($request));

        return $this->volver($request, 'Plan de gigas actualizado.');
    }

    public function destroy(Request $request, HotspotPlan $plan): RedirectResponse
    {
        if ($plan->ventas()->exists()) {
            $plan->update(['activo' => false]);

            return $this->volver($request, 'El plan tiene ventas, así que quedó desactivado.');
        }

        $plan->delete();

        return $this->volver($request, 'Plan de gigas eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'gigas' => ['required', 'integer', 'min:1', 'max:10240'],
            'precio' => ['required', 'integer', 'min:1000', 'max:50000000'],
            'vigencia_horas' => ['required', 'integer', 'min:0', 'max:8760'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nombre.required' => 'Poné un nombre para el plan.',
            'gigas.required' => 'Indicá cuántos gigas incluye.',
            'gigas.min' => 'El plan tiene que incluir al menos 1 GB.',
            'precio.required' => 'Indicá el precio.',
            'precio.min' => 'El precio mínimo es 1.000 Gs.',
            'vigencia_horas.required' => 'Indicá cuántas horas dura el acceso. Usá 0 si dura hasta agotar los gigas.',
        ]);

        $rate = trim((string) ($datos['rate_limit'] ?? ''));

        return [
            'nombre' => trim($datos['nombre']),
            'gigas' => (int) $datos['gigas'],
            'precio' => (int) $datos['precio'],
            'vigencia_horas' => (int) $datos['vigencia_horas'],
            'rate_limit' => $rate !== '' ? $rate : null,
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => $request->boolean('activo'),
        ];
    }

    private function volver(Request $request, string $aviso): RedirectResponse
    {
        $routerId = (int) $request->input('router_id', 0);

        return redirect()
            ->route('hotspot.dashboard', array_filter([
                'vista' => 'planes',
                'router_id' => $routerId > 0 ? $routerId : null,
            ]))
            ->with('success', $aviso);
    }
}
