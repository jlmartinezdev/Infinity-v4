<?php

namespace App\Support;

use App\Models\FacturacionParametro;
use App\Models\Router;
use App\Models\User;

/**
 * Failover de salida ISP (ping 1.1.1.1 vía ISP 1 → tráfico por ISP 2).
 * Config y estado en facturacion_parametros.
 */
class IspFailoverConfig
{
    public const MODO_PRIMARIO = 'primario';

    public const MODO_FAILOVER = 'failover';

    public static function defaults(): array
    {
        return [
            'enabled' => false,
            'router_id' => null,
            'ping_host' => '1.1.1.1',
            'ping_count' => 2,
            'isp1_nombre' => 'ISP 1',
            'isp1_interface' => '',
            'isp1_src_address' => '',
            'isp1_ruta_comentario' => 'ISP1',
            'isp1_gateway' => '',
            'isp2_nombre' => 'ISP 2',
            'isp2_ruta_comentario' => 'ISP2',
            'isp2_gateway' => '',
            'confirmaciones' => 3,
            'confirmaciones_ok' => 3,
            'auto_failover' => true,
            'webhook_base_url' => '',
            'usuario_ids' => [],
        ];
    }

    public static function estadoDefaults(): array
    {
        return [
            'modo' => self::MODO_PRIMARIO,
            'isp_activa' => 1,
            'fallos_seguidos' => 0,
            'ok_seguidos' => 0,
            'ping_ok' => null,
            'latency_ms' => null,
            'last_error' => null,
            'last_at' => null,
            'failover_at' => null,
            'failback_at' => null,
            'aviso_failover_enviado' => false,
            'aviso_failback_enviado' => false,
            'ultimo_evento' => null,
        ];
    }

    public static function get(): array
    {
        $raw = FacturacionParametro::obtener('isp_failover_config', '');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $cfg = array_merge(self::defaults(), $decoded);
        $cfg['enabled'] = (bool) $cfg['enabled'];
        $cfg['auto_failover'] = (bool) $cfg['auto_failover'];
        $cfg['router_id'] = $cfg['router_id'] !== null && $cfg['router_id'] !== ''
            ? (int) $cfg['router_id']
            : null;
        $cfg['ping_host'] = trim((string) ($cfg['ping_host'] ?: '1.1.1.1'));
        $cfg['ping_count'] = max(1, min(10, (int) ($cfg['ping_count'] ?? 2)));
        $cfg['confirmaciones'] = max(1, min(20, (int) ($cfg['confirmaciones'] ?? 3)));
        $cfg['confirmaciones_ok'] = max(1, min(20, (int) ($cfg['confirmaciones_ok'] ?? 3)));
        $cfg['usuario_ids'] = array_values(array_unique(array_map(
            'intval',
            is_array($cfg['usuario_ids'] ?? null) ? $cfg['usuario_ids'] : []
        )));

        foreach ([
            'isp1_nombre', 'isp1_interface', 'isp1_src_address', 'isp1_ruta_comentario', 'isp1_gateway',
            'isp2_nombre', 'isp2_ruta_comentario', 'isp2_gateway', 'webhook_base_url',
        ] as $k) {
            $cfg[$k] = trim((string) ($cfg[$k] ?? ''));
        }

        if ($cfg['isp1_nombre'] === '') {
            $cfg['isp1_nombre'] = 'ISP 1';
        }
        if ($cfg['isp2_nombre'] === '') {
            $cfg['isp2_nombre'] = 'ISP 2';
        }

        return $cfg;
    }

    public static function enabled(): bool
    {
        return (bool) self::get()['enabled'];
    }

    public static function estado(): array
    {
        $raw = FacturacionParametro::obtener('isp_failover_estado', '');
        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            $decoded = [];
        }

        $estado = array_merge(self::estadoDefaults(), $decoded);
        $estado['modo'] = $estado['modo'] === self::MODO_FAILOVER
            ? self::MODO_FAILOVER
            : self::MODO_PRIMARIO;
        $estado['isp_activa'] = (int) ($estado['isp_activa'] ?? 1) === 2 ? 2 : 1;
        $estado['fallos_seguidos'] = max(0, (int) ($estado['fallos_seguidos'] ?? 0));
        $estado['ok_seguidos'] = max(0, (int) ($estado['ok_seguidos'] ?? 0));
        $estado['aviso_failover_enviado'] = (bool) ($estado['aviso_failover_enviado'] ?? false);
        $estado['aviso_failback_enviado'] = (bool) ($estado['aviso_failback_enviado'] ?? false);
        if ($estado['latency_ms'] !== null && $estado['latency_ms'] !== '') {
            $estado['latency_ms'] = (int) $estado['latency_ms'];
        } else {
            $estado['latency_ms'] = null;
        }

        return $estado;
    }

    public static function guardarEstado(array $estado): void
    {
        $merged = array_merge(self::estado(), $estado);
        FacturacionParametro::establecer(
            'isp_failover_estado',
            json_encode($merged, JSON_UNESCAPED_UNICODE),
            'Estado runtime failover ISP'
        );
    }

    /**
     * @param  list<int|string>  $usuarioIds
     */
    public static function guardar(array $datos): void
    {
        $cfg = array_merge(self::defaults(), self::get(), $datos);
        $cfg['enabled'] = (bool) ($datos['enabled'] ?? $cfg['enabled']);
        $cfg['auto_failover'] = (bool) ($datos['auto_failover'] ?? $cfg['auto_failover']);
        $cfg['router_id'] = isset($datos['router_id']) && $datos['router_id'] !== '' && $datos['router_id'] !== null
            ? (int) $datos['router_id']
            : null;
        $cfg['usuario_ids'] = array_values(array_unique(array_filter(array_map(
            'intval',
            is_array($datos['usuario_ids'] ?? null) ? $datos['usuario_ids'] : ($cfg['usuario_ids'] ?? [])
        ))));
        $cfg['ping_count'] = max(1, min(10, (int) ($cfg['ping_count'] ?? 2)));
        $cfg['confirmaciones'] = max(1, min(20, (int) ($cfg['confirmaciones'] ?? 3)));
        $cfg['confirmaciones_ok'] = max(1, min(20, (int) ($cfg['confirmaciones_ok'] ?? 3)));

        unset($cfg['estado']);

        FacturacionParametro::establecer(
            'isp_failover_config',
            json_encode($cfg, JSON_UNESCAPED_UNICODE),
            'Failover salida ISP 1 → ISP 2'
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    public static function destinatarios()
    {
        $ids = self::get()['usuario_ids'] ?? [];
        if ($ids === []) {
            return collect();
        }

        return User::query()
            ->staff()
            ->activos()
            ->whereIn('usuario_id', $ids)
            ->orderBy('name')
            ->get();
    }

    public static function router(): ?Router
    {
        $id = self::get()['router_id'] ?? null;
        if (! $id) {
            return null;
        }

        return Router::query()->find((int) $id);
    }

    /**
     * Payload para monitoreo de red / panel.
     */
    public static function snapshot(): array
    {
        $cfg = self::get();
        $estado = self::estado();
        $router = self::router();

        return [
            'enabled' => (bool) $cfg['enabled'],
            'modo' => $estado['modo'],
            'isp_activa' => $estado['isp_activa'],
            'isp1_nombre' => $cfg['isp1_nombre'],
            'isp2_nombre' => $cfg['isp2_nombre'],
            'ping_host' => $cfg['ping_host'],
            'ping_ok' => $estado['ping_ok'],
            'latency_ms' => $estado['latency_ms'],
            'fallos_seguidos' => $estado['fallos_seguidos'],
            'ok_seguidos' => $estado['ok_seguidos'],
            'last_error' => $estado['last_error'],
            'last_at' => $estado['last_at'],
            'failover_at' => $estado['failover_at'],
            'failback_at' => $estado['failback_at'],
            'ultimo_evento' => $estado['ultimo_evento'],
            'router_id' => $cfg['router_id'],
            'router_nombre' => $router?->nombre,
            'auto_failover' => (bool) $cfg['auto_failover'],
        ];
    }
}
