<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Models\ServicioObservacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EquipoObservacionController extends Controller
{
    public function store(Request $request, $servicio_id): RedirectResponse
    {
        $servicio = Servicio::query()->findOrFail($servicio_id);
        $datos = $request->validate([
            'motivo' => ['required', 'in:cambio_equipo,falla,senal'],
            'horas' => ['required', 'integer', 'in:24,48,72,168'],
            'equipo' => ['nullable', 'string', 'max:120'],
            'nota' => ['nullable', 'string', 'max:500'],
        ]);

        $abierta = ServicioObservacion::query()
            ->where('servicio_id', $servicio->servicio_id)
            ->whereNull('cerrada_at')
            ->exists();
        if ($abierta) {
            return back()->with('error', 'Este servicio ya está en observación.');
        }

        $horas = (int) $datos['horas'];
        $ahora = now();
        ServicioObservacion::query()->create([
            'servicio_id' => $servicio->servicio_id,
            'usuario_id' => $request->user()?->usuario_id,
            'motivo' => $datos['motivo'],
            'equipo' => filled($datos['equipo'] ?? null) ? trim((string) $datos['equipo']) : null,
            'nota' => filled($datos['nota'] ?? null) ? trim((string) $datos['nota']) : null,
            'horas' => $horas,
            'inicia_at' => $ahora,
            'vence_at' => $ahora->copy()->addHours($horas),
        ]);

        return back()->with('success', 'Equipo en observación por '.ServicioObservacion::horasEtiqueta($horas).'. El informe queda en el panel del técnico.');
    }

    public function cerrar(Request $request, ServicioObservacion $observacion): RedirectResponse
    {
        $datos = $request->validate([
            'resultado' => ['required', 'in:resuelto,reincidio'],
        ]);
        if ($observacion->cerrada_at !== null) {
            return back()->with('error', 'Esta observación ya estaba cerrada.');
        }

        $observacion->update([
            'cerrada_at' => now(),
            'resultado' => $datos['resultado'],
        ]);

        $mensaje = $datos['resultado'] === ServicioObservacion::RESULTADO_RESUELTO
            ? 'Observación cerrada: la falla no volvió.'
            : 'Observación cerrada: volvió la falla.';

        return back()->with('success', $mensaje);
    }
}
