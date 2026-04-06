<?php

namespace App\Http\Controllers;

use App\Models\MikrotikOperacionPendiente;
use App\Services\MikrotikPendienteEjecutor;

class MikrotikPendienteController extends Controller
{
    public function index()
    {
        $pendientes = MikrotikOperacionPendiente::query()
            ->where('estado', MikrotikOperacionPendiente::ESTADO_PENDIENTE)
            ->orderByDesc('updated_at')
            ->paginate(30);

        $recientes = MikrotikOperacionPendiente::query()
            ->whereIn('estado', [
                MikrotikOperacionPendiente::ESTADO_COMPLETADO,
                MikrotikOperacionPendiente::ESTADO_DESCARTADO,
            ])
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get();

        $labelsTipo = self::labelsTipo();

        return view('sistema.mikrotik-pendientes', compact('pendientes', 'recientes', 'labelsTipo'));
    }

    /**
     * @return array<string, string>
     */
    public static function labelsTipo(): array
    {
        return [
            MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO => 'Sincronizar PPPoE (servicio)',
            MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED => 'Habilitar / deshabilitar PPPoE',
            MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET => 'Eliminar secreto PPPoE',
            MikrotikOperacionPendiente::TIPO_SYNC_HOTSPOT => 'Sincronizar usuario Hotspot',
        ];
    }

    public function reintentar(MikrotikPendienteEjecutor $ejecutor, int $id)
    {
        $op = MikrotikOperacionPendiente::findOrFail($id);
        if ($op->estado !== MikrotikOperacionPendiente::ESTADO_PENDIENTE) {
            return redirect()->route('sistema.mikrotik-pendientes.index')
                ->with('error', 'La operación ya no está pendiente.');
        }

        $result = $ejecutor->ejecutar($op);
        $op->refresh();

        if (! empty($result['success'])) {
            return redirect()->route('sistema.mikrotik-pendientes.index')
                ->with('success', 'Operación ejecutada correctamente en MikroTik.');
        }

        return redirect()->route('sistema.mikrotik-pendientes.index')
            ->with('error', 'Sigue fallando: ' . ($result['error'] ?? 'error desconocido'));
    }

    public function descartar(int $id)
    {
        $op = MikrotikOperacionPendiente::findOrFail($id);
        if ($op->estado !== MikrotikOperacionPendiente::ESTADO_PENDIENTE) {
            return redirect()->route('sistema.mikrotik-pendientes.index')
                ->with('error', 'La operación ya no está pendiente.');
        }
        $op->update(['estado' => MikrotikOperacionPendiente::ESTADO_DESCARTADO]);

        return redirect()->route('sistema.mikrotik-pendientes.index')
            ->with('success', 'Operación descartada.');
    }

    public function reintentarTodos(MikrotikPendienteEjecutor $ejecutor)
    {
        $ops = MikrotikOperacionPendiente::query()->pendientes()->orderBy('id')->get();
        $ok = 0;
        $fail = 0;
        foreach ($ops as $op) {
            $r = $ejecutor->ejecutar($op);
            if (! empty($r['success'])) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return redirect()->route('sistema.mikrotik-pendientes.index')
            ->with('success', "Reintento masivo: {$ok} correctos, {$fail} con error.");
    }
}
