<?php

namespace App\Services;

use App\Models\MikrotikOperacionPendiente;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Illuminate\Support\Facades\Log;
use Throwable;

class MikrotikPendienteEjecutor
{
    public function __construct(
        protected MikroTikService $mikrotik
    ) {
    }

    /**
     * Ejecuta una operación pendiente y actualiza estado en BD.
     *
     * @return array{success: bool, error?: string}
     */
    public function ejecutar(MikrotikOperacionPendiente $op): array
    {
        if ($op->estado !== MikrotikOperacionPendiente::ESTADO_PENDIENTE) {
            return ['success' => false, 'error' => 'La operación no está pendiente.'];
        }

        $payload = $op->payload ?? [];

        $op->increment('intentos');
        $op->update(['last_attempt_at' => now()]);

        try {
            $result = match ($op->tipo) {
                MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO => $this->syncPppoeServicio($payload),
                MikrotikOperacionPendiente::TIPO_PPPOE_DISABLED => $this->pppoeDisabled($payload),
                MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET => $this->removePppoeSecret($payload),
                MikrotikOperacionPendiente::TIPO_SYNC_HOTSPOT => $this->syncHotspot($payload),
                default => ['success' => false, 'error' => 'Tipo de operación desconocido: ' . $op->tipo],
            };
        } catch (Throwable $e) {
            Log::error('[MikrotikPendienteEjecutor] excepción', ['op_id' => $op->id, 'error' => $e->getMessage()]);
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        if (! empty($result['success'])) {
            $op->update([
                'estado' => MikrotikOperacionPendiente::ESTADO_COMPLETADO,
                'error_ultimo' => null,
            ]);
        } else {
            $op->update([
                'error_ultimo' => mb_substr($result['error'] ?? 'Error desconocido', 0, 5000),
            ]);
        }

        return $result;
    }

    /**
     * @return array{success: bool, error?: string}
     */
    protected function syncPppoeServicio(array $payload): array
    {
        $id = (int) ($payload['servicio_id'] ?? 0);
        if ($id < 1) {
            return ['success' => false, 'error' => 'servicio_id inválido'];
        }
        $servicio = Servicio::with(['pool.router', 'plan.perfilPppoe', 'cliente'])->find($id);
        if (! $servicio) {
            return ['success' => false, 'error' => 'Servicio no encontrado'];
        }

        return $this->mikrotik->syncPppoeServicio($servicio);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    protected function pppoeDisabled(array $payload): array
    {
        $id = (int) ($payload['servicio_id'] ?? 0);
        $disabled = (bool) ($payload['disabled'] ?? true);
        if ($id < 1) {
            return ['success' => false, 'error' => 'servicio_id inválido'];
        }
        $servicio = Servicio::with(['pool.router'])->find($id);
        if (! $servicio) {
            return ['success' => false, 'error' => 'Servicio no encontrado'];
        }

        return $this->mikrotik->setPppoeDisabledEnRouter($servicio, $disabled);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    protected function removePppoeSecret(array $payload): array
    {
        $routerId = (int) ($payload['router_id'] ?? 0);
        $usuario = trim((string) ($payload['usuario_pppoe'] ?? ''));
        if ($routerId < 1 || $usuario === '') {
            return ['success' => false, 'error' => 'router_id o usuario_pppoe inválido'];
        }
        $router = Router::find($routerId);
        if (! $router) {
            return ['success' => false, 'error' => 'Router no encontrado'];
        }

        return $this->mikrotik->removePppoeSecretByName($router, $usuario);
    }

    /**
     * @return array{success: bool, error?: string}
     */
    protected function syncHotspot(array $payload): array
    {
        $id = (int) ($payload['servicio_hotspot_id'] ?? 0);
        if ($id < 1) {
            return ['success' => false, 'error' => 'servicio_hotspot_id inválido'];
        }
        $sh = ServicioHotspot::with(['router', 'hotspotPerfil'])->find($id);
        if (! $sh) {
            return ['success' => false, 'error' => 'Servicio hotspot no encontrado'];
        }

        return $this->mikrotik->syncHotspotServicio($sh);
    }
}
