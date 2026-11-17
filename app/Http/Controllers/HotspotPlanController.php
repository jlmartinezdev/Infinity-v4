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
        // Un plan ilimitado se corta por tiempo: sin horas sería acceso gratis para siempre.
        $horasMinimas = $request->boolean('ilimitado') ? 1 : 0;

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80'],
            'ilimitado' => ['nullable', 'boolean'],
            'gigas' => ['required_unless:ilimitado,1', 'nullable', 'integer', 'min:1', 'max:10240'],
            'precio' => ['required', 'integer', 'min:1000', 'max:50000000'],
            'vigencia_horas' => ['required', 'integer', 'min:'.$horasMinimas, 'max:8760'],
            'rate_limit' => ['nullable', 'string', 'max:50'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nombre.required' => 'Poné un nombre para el plan.',
            'gigas.required_unless' => 'Indicá cuántos gigas incluye, o marcá el plan como ilimitado.',
            'gigas.min' => 'El plan tiene que incluir al menos 1 GB, o ser ilimitado.',
            'precio.required' => 'Indicá el precio.',
            'precio.min' => 'El precio mínimo es 1.000 Gs.',
            'vigencia_horas.required' => 'Indicá cuántas horas dura el acceso.',
            'vigencia_horas.min' => 'Un plan ilimitado necesita una duración: al menos 1 hora.',
        ]);

        $rate = trim((string) ($datos['rate_limit'] ?? ''));
        $ilimitado = $request->boolean('ilimitado');

        return [
            'nombre' => trim($datos['nombre']),
            'gigas' => $ilimitado ? null : (int) $datos['gigas'],
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
