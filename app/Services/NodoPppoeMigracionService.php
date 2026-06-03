<?php

namespace App\Services;

use App\Models\MikrotikOperacionPendiente;
use App\Models\Nodo;
use App\Models\PoolIpAsignada;
use App\Models\Router;
use App\Models\RouterIpPool;
use App\Models\Servicio;
use Illuminate\Support\Facades\DB;

class NodoPppoeMigracionService
{
    public function __construct(
        private MikroTikService $mikrotik
    ) {}

    /**
     * @return array{routers_nodo: array<int, array>, routers_todos: array<int, array>}
     */
    public function datosIniciales(Nodo $nodo): array
    {
        $routersNodo = Router::where('nodo_id', $nodo->nodo_id)
            ->orderBy('nombre')
            ->get()
            ->map(fn (Router $r) => $this->formatRouter($r))
            ->values()
            ->all();

        $routersTodos = Router::with('nodo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Router $r) => $this->formatRouter($r, true))
            ->values()
            ->all();

        return [
            'routers_nodo' => $routersNodo,
            'routers_todos' => $routersTodos,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarServiciosPorRouter(int $routerOrigenId): array
    {
        return Servicio::query()
            ->with(['cliente', 'plan', 'pool'])
            ->whereHas('pool', fn ($q) => $q->where('router_id', $routerOrigenId))
            ->whereNotNull('usuario_pppoe')
            ->where('usuario_pppoe', '!=', '')
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->orderBy('usuario_pppoe')
            ->get()
            ->map(function (Servicio $s) {
                $cliente = trim(($s->cliente?->nombre ?? '') . ' ' . ($s->cliente?->apellido ?? ''));

                return [
                    'servicio_id' => $s->servicio_id,
                    'usuario_pppoe' => $s->usuario_pppoe,
                    'ip' => $s->ip,
                    'estado' => $s->estado,
                    'estado_label' => Servicio::estadosDisponibles()[$s->estado] ?? $s->estado,
                    'cliente' => $cliente !== '' ? $cliente : '—',
                    'plan' => $s->plan?->nombre ?? '—',
                    'pool_id' => $s->pool_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarPoolsPorRouter(int $routerDestinoId): array
    {
        return RouterIpPool::query()
            ->where('router_id', $routerDestinoId)
            ->where('activo', true)
            ->orderBy('descripcion')
            ->orderBy('pool_id')
            ->get()
            ->map(fn (RouterIpPool $p) => [
                'pool_id' => $p->pool_id,
                'descripcion' => $p->descripcion,
                'ip_range' => $p->ip_range,
                'label' => trim(($p->descripcion ?: 'Pool #' . $p->pool_id) . ' (' . $p->ip_range . ')'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int>  $servicioIds
     * @return array{ok: int, errores: array<int, array{servicio_id: int, usuario: string, error: string}>, detalles: array<int, string>}
     */
    public function ejecutar(
        string $modo,
        int $routerOrigenId,
        int $routerDestinoId,
        int $poolDestinoId,
        array $servicioIds,
        bool $asignarIpAutomatica = true
    ): array {
        $modo = $modo === 'copiar' ? 'copiar' : 'mover';

        if ($routerOrigenId === $routerDestinoId && $modo === 'mover') {
            throw new \InvalidArgumentException('El router origen y destino deben ser distintos para mover.');
        }

        $poolDestino = null;
        if ($modo === 'mover') {
            if (! $poolDestinoId) {
                throw new \InvalidArgumentException('Seleccioná un pool destino para mover.');
            }
            $poolDestino = RouterIpPool::with('router')->findOrFail($poolDestinoId);
            if ((int) $poolDestino->router_id !== $routerDestinoId) {
                throw new \InvalidArgumentException('El pool seleccionado no pertenece al router destino.');
            }
        }

        $routerOrigen = Router::findOrFail($routerOrigenId);
        $routerDestino = Router::findOrFail($routerDestinoId);

        $servicios = Servicio::query()
            ->with(['pool.router', 'plan.perfilPppoe', 'cliente'])
            ->whereIn('servicio_id', $servicioIds)
            ->whereHas('pool', fn ($q) => $q->where('router_id', $routerOrigenId))
            ->whereNotNull('usuario_pppoe')
            ->where('usuario_pppoe', '!=', '')
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
            ->get();

        if ($servicios->isEmpty()) {
            throw new \InvalidArgumentException('No hay servicios válidos para procesar.');
        }

        $ok = 0;
        $errores = [];
        $detalles = [];

        foreach ($servicios as $servicio) {
            try {
                if ($modo === 'copiar') {
                    $resultado = $this->copiarServicio($servicio, $routerDestino);
                } else {
                    $resultado = $this->moverServicio($servicio, $routerOrigen, $routerDestino, $poolDestino, $asignarIpAutomatica);
                }

                if ($resultado['success']) {
                    $ok++;
                    $detalles[] = $resultado['mensaje'] ?? ($servicio->usuario_pppoe . ': OK');
                } else {
                    $errores[] = [
                        'servicio_id' => $servicio->servicio_id,
                        'usuario' => (string) $servicio->usuario_pppoe,
                        'error' => $resultado['error'] ?? 'Error desconocido',
                    ];
                }
            } catch (\Throwable $e) {
                $errores[] = [
                    'servicio_id' => $servicio->servicio_id,
                    'usuario' => (string) $servicio->usuario_pppoe,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return ['ok' => $ok, 'errores' => $errores, 'detalles' => $detalles];
    }

    /**
     * @return array{success: bool, mensaje?: string, error?: string}
     */
    private function copiarServicio(Servicio $servicio, Router $routerDestino): array
    {
        if (! $servicio->estaActivo()) {
            return [
                'success' => false,
                'error' => 'El servicio no está activo; no se copia a MikroTik.',
            ];
        }

        $sync = $this->mikrotik->syncPppoeServicioEnRouter($servicio, $routerDestino, false);
        if (! $sync['success']) {
            return ['success' => false, 'error' => $sync['error'] ?? 'Error al copiar en MikroTik'];
        }

        return [
            'success' => true,
            'mensaje' => $servicio->usuario_pppoe . ': copiado en ' . ($routerDestino->nombre ?? $routerDestino->ip),
        ];
    }

    /**
     * @return array{success: bool, mensaje?: string, error?: string}
     */
    private function moverServicio(
        Servicio $servicio,
        Router $routerOrigen,
        Router $routerDestino,
        RouterIpPool $poolDestino,
        bool $asignarIpAutomatica
    ): array {
        $usuario = trim((string) $servicio->usuario_pppoe);
        if ($usuario === '') {
            return ['success' => false, 'error' => 'Sin usuario PPPoE'];
        }

        $ipAnterior = $servicio->ip;
        $poolAnterior = $servicio->pool_id;
        $nuevaIp = null;

        DB::transaction(function () use (
            $servicio,
            $poolDestino,
            $asignarIpAutomatica,
            $ipAnterior,
            $poolAnterior,
            &$nuevaIp
        ) {
            if ($ipAnterior && $poolAnterior) {
                PoolIpAsignada::where('pool_id', $poolAnterior)
                    ->where('ip', $ipAnterior)
                    ->update(['estado' => 'disponible']);
            }

            $nuevaIp = $asignarIpAutomatica
                ? $this->primeraIpDisponible((int) $poolDestino->pool_id, (int) $servicio->servicio_id)
                : $ipAnterior;

            if ($asignarIpAutomatica && ! $nuevaIp) {
                throw new \RuntimeException('No hay IP disponible en el pool destino.');
            }

            if ($nuevaIp) {
                $ocupada = Servicio::where('pool_id', $poolDestino->pool_id)
                    ->where('ip', $nuevaIp)
                    ->where('servicio_id', '!=', $servicio->servicio_id)
                    ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
                    ->exists();

                if ($ocupada) {
                    throw new \RuntimeException('La IP ' . $nuevaIp . ' ya está en uso en el pool destino.');
                }

                PoolIpAsignada::where('pool_id', $poolDestino->pool_id)
                    ->where('ip', $nuevaIp)
                    ->update(['estado' => 'asignada']);
            }

            $servicio->update([
                'pool_id' => $poolDestino->pool_id,
                'ip' => $nuevaIp,
                'pppoe_synced' => null,
                'pppoe_status' => null,
            ]);
        });

        $servicio->refresh();
        $servicio->load(['pool.router', 'plan.perfilPppoe', 'cliente']);

        $quitar = $this->mikrotik->removePppoeSecretByName($routerOrigen, $usuario);
        if (! $quitar['success']) {
            MikrotikOperacionPendiente::registrarSiFallo(
                MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET,
                ['router_id' => $routerOrigen->router_id, 'usuario_pppoe' => $usuario],
                $quitar['error'] ?? 'Error al eliminar secreto',
                'nodos.migrar-pppoe'
            );

            return [
                'success' => false,
                'error' => 'BD actualizada pero no se pudo quitar en origen: ' . ($quitar['error'] ?? ''),
            ];
        }

        if ($servicio->estaActivo()) {
            $sync = $this->mikrotik->syncPppoeServicio($servicio);
            if (! $sync['success']) {
                MikrotikOperacionPendiente::registrarSiFallo(
                    MikrotikOperacionPendiente::TIPO_SYNC_PPPOE_SERVICIO,
                    ['servicio_id' => $servicio->servicio_id],
                    $sync['error'] ?? 'Error al sincronizar',
                    'nodos.migrar-pppoe'
                );

                return [
                    'success' => false,
                    'error' => 'Movido en BD pero falló sync en destino: ' . ($sync['error'] ?? ''),
                ];
            }
        }

        $msg = $usuario . ': movido a ' . ($routerDestino->nombre ?? $routerDestino->ip);
        if ($nuevaIp) {
            $msg .= ' (IP ' . $nuevaIp . ')';
        }

        return ['success' => true, 'mensaje' => $msg];
    }

    private function primeraIpDisponible(int $poolId, int $excluirServicioId): ?string
    {
        $candidatas = PoolIpAsignada::where('pool_id', $poolId)
            ->where('estado', 'disponible')
            ->whereRaw("ip NOT LIKE '%.255'")
            ->orderBy('ip')
            ->pluck('ip');

        foreach ($candidatas as $ip) {
            $enUso = Servicio::where('pool_id', $poolId)
                ->where('ip', $ip)
                ->where('servicio_id', '!=', $excluirServicioId)
                ->where('estado', '!=', Servicio::ESTADO_CANCELADO)
                ->exists();

            if (! $enUso) {
                return $ip;
            }
        }

        return null;
    }

    /**
     * @return array{router_id: int, nombre: string, ip: string, nodo_id: int|null, nodo: string|null, en_nodo: bool}
     */
    private function formatRouter(Router $r, bool $conNodo = false): array
    {
        return [
            'router_id' => $r->router_id,
            'nombre' => $r->nombre ?? $r->ip,
            'ip' => $r->ip,
            'nodo_id' => $r->nodo_id,
            'nodo' => $conNodo ? ($r->nodo?->descripcion ?? '—') : null,
            'label' => $conNodo
                ? trim(($r->nombre ?? $r->ip) . ' — ' . ($r->nodo?->descripcion ?? 'Sin nodo'))
                : ($r->nombre ?? $r->ip),
        ];
    }
}
