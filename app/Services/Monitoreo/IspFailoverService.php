<?php

namespace App\Services\Monitoreo;

use App\Models\Router;
use App\Services\MikroTikService;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\IspFailoverConfig;
use Illuminate\Support\Facades\Log;

class IspFailoverService
{
    public function __construct(
        private readonly MikroTikService $mikrotik,
        private readonly WhatsAppOutboundNotifier $whatsapp,
    ) {}

    /**
     * Chequeo periódico: ping 1.1.1.1 vía ISP 1 y failover/failback según umbral.
     *
     * @return array{ok: bool, message: string, ping_ok?: bool, modo?: string, accion?: string}
     */
    public function verificar(bool $forzarWhatsapp = false): array
    {
        $cfg = IspFailoverConfig::get();
        if (! $cfg['enabled']) {
            return ['ok' => true, 'message' => 'Failover ISP desactivado.', 'accion' => 'omitido'];
        }

        $router = IspFailoverConfig::router();
        if (! $router) {
            return ['ok' => false, 'message' => 'No hay router de borde configurado.'];
        }

        if ($cfg['isp1_src_address'] === '' && $cfg['isp1_interface'] === '') {
            return ['ok' => false, 'message' => 'Indicá la IP origen (src-address) de ISP 1. En RouterOS 7 el ping no admite interface.'];
        }

        $src = $cfg['isp1_src_address'];
        if ($src === '' && $cfg['isp1_interface'] !== '') {
            $src = (string) ($this->mikrotik->ipv4OnInterface($router, $cfg['isp1_interface']) ?? '');
            if ($src === '') {
                return ['ok' => false, 'message' => 'No hay IPv4 en la interfaz '.$cfg['isp1_interface'].'. Cargá src-address a mano.'];
            }
        }

        $ping = $this->mikrotik->pingFromRouter(
            $router,
            $cfg['ping_host'],
            $cfg['ping_count'],
            $src
        );

        $estado = IspFailoverConfig::estado();
        $estado['ping_ok'] = $ping['ok'];
        $estado['latency_ms'] = $ping['ok'] ? $ping['latency_ms'] : null;
        $estado['last_error'] = $ping['ok'] ? null : ($ping['error'] ?: 'timeout');
        $estado['last_at'] = now()->toIso8601String();

        if ($ping['ok']) {
            $estado['ok_seguidos'] = min(255, ((int) $estado['ok_seguidos']) + 1);
            $estado['fallos_seguidos'] = 0;
        } else {
            $estado['fallos_seguidos'] = min(255, ((int) $estado['fallos_seguidos']) + 1);
            $estado['ok_seguidos'] = 0;
        }

        $accion = 'ninguna';

        if (! $ping['ok'] && $estado['fallos_seguidos'] >= $cfg['confirmaciones']) {
            $r = $this->entrarFailover($router, $cfg, $estado, $forzarWhatsapp);
            $accion = $r['accion'];
            $estado = $r['estado'];
        } elseif ($ping['ok'] && $estado['modo'] === IspFailoverConfig::MODO_FAILOVER
            && $estado['ok_seguidos'] >= $cfg['confirmaciones_ok']) {
            $r = $this->salirFailover($router, $cfg, $estado, $forzarWhatsapp);
            $accion = $r['accion'];
            $estado = $r['estado'];
        }

        IspFailoverConfig::guardarEstado($estado);

        Log::info('[isp-failover] chequeo', [
            'router_id' => $router->router_id,
            'src_address' => $src,
            'ping_ok' => $ping['ok'],
            'latency_ms' => $ping['latency_ms'],
            'fallos' => $estado['fallos_seguidos'],
            'oks' => $estado['ok_seguidos'],
            'modo' => $estado['modo'],
            'accion' => $accion,
        ]);

        $lat = $ping['latency_ms'] !== null ? $ping['latency_ms'].' ms' : '—';

        return [
            'ok' => true,
            'message' => $ping['ok']
                ? "Salida {$cfg['isp1_nombre']} OK ({$lat}). Modo: {$estado['modo']}."
                : "Salida {$cfg['isp1_nombre']} sin ping a {$cfg['ping_host']}. Fallos: {$estado['fallos_seguidos']}. Modo: {$estado['modo']}.",
            'ping_ok' => $ping['ok'],
            'latency_ms' => $ping['latency_ms'],
            'modo' => $estado['modo'],
            'accion' => $accion,
            'estado' => $estado,
        ];
    }

    /**
     * Evento desde Netwatch del MikroTik (down = ISP 1 caído, up = recuperado).
     *
     * @return array{ok: bool, message: string, accion?: string}
     */
    public function aplicarWebhook(string $evento, Router $router): array
    {
        $cfg = IspFailoverConfig::get();
        if (! $cfg['enabled']) {
            return ['ok' => true, 'message' => 'Failover ISP desactivado; evento ignorado.', 'accion' => 'omitido'];
        }

        $esperado = (int) ($cfg['router_id'] ?? 0);
        if ($esperado > 0 && (int) $router->router_id !== $esperado) {
            return ['ok' => false, 'message' => 'El webhook no corresponde al router de borde configurado.'];
        }

        $evento = strtolower(trim($evento));
        $down = in_array($evento, ['down', 'failover', 'isp1_down', '0', 'false'], true);
        $up = in_array($evento, ['up', 'failback', 'isp1_up', '1', 'true'], true);
        if (! $down && ! $up) {
            return ['ok' => false, 'message' => 'Evento inválido. Usá down o up.'];
        }

        $estado = IspFailoverConfig::estado();
        $estado['last_at'] = now()->toIso8601String();
        $estado['ultimo_evento'] = $evento;

        if ($down) {
            $estado['ping_ok'] = false;
            $estado['fallos_seguidos'] = max($cfg['confirmaciones'], (int) $estado['fallos_seguidos']);
            $estado['ok_seguidos'] = 0;
            $r = $this->entrarFailover($router, $cfg, $estado, false);
        } else {
            $estado['ping_ok'] = true;
            $estado['ok_seguidos'] = max($cfg['confirmaciones_ok'], (int) $estado['ok_seguidos']);
            $estado['fallos_seguidos'] = 0;
            $r = $this->salirFailover($router, $cfg, $estado, false);
        }

        IspFailoverConfig::guardarEstado($r['estado']);

        return [
            'ok' => true,
            'message' => $r['accion'] === 'ya_en_failover' || $r['accion'] === 'ya_en_primario'
                ? 'Estado ya aplicado (sin reenviar aviso).'
                : 'Evento ISP procesado.',
            'accion' => $r['accion'],
            'modo' => $r['estado']['modo'] ?? null,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function forzarFailover(): array
    {
        $cfg = IspFailoverConfig::get();
        $router = IspFailoverConfig::router();
        if (! $router) {
            return ['ok' => false, 'message' => 'No hay router de borde configurado.'];
        }

        $estado = IspFailoverConfig::estado();
        $estado['fallos_seguidos'] = $cfg['confirmaciones'];
        $r = $this->entrarFailover($router, $cfg, $estado, true);
        IspFailoverConfig::guardarEstado($r['estado']);

        return [
            'ok' => true,
            'message' => $r['accion'] === 'ya_en_failover'
                ? 'Ya estaba en failover ISP 2.'
                : 'Failover a '.$cfg['isp2_nombre'].' aplicado.',
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function restaurarPrimario(): array
    {
        $cfg = IspFailoverConfig::get();
        $router = IspFailoverConfig::router();
        if (! $router) {
            return ['ok' => false, 'message' => 'No hay router de borde configurado.'];
        }

        $estado = IspFailoverConfig::estado();
        $estado['ok_seguidos'] = $cfg['confirmaciones_ok'];
        $r = $this->salirFailover($router, $cfg, $estado, true);
        IspFailoverConfig::guardarEstado($r['estado']);

        return [
            'ok' => true,
            'message' => $r['accion'] === 'ya_en_primario'
                ? 'Ya estaba con '.$cfg['isp1_nombre'].' como salida principal.'
                : 'Restaurado '.$cfg['isp1_nombre'].' como salida principal.',
        ];
    }

    /**
     * Rutas default 0.0.0.0/0 del router (para el panel).
     *
     * @return list<array{id: string, dst: string, gateway: string, distance: mixed, disabled: bool, active: bool, comment: string}>
     */
    public function rutasDefault(Router $router): array
    {
        $out = [];
        foreach ($this->mikrotik->getIpv4Routes($router) as $row) {
            $dst = trim((string) ($row['dst-address'] ?? ''));
            if ($dst !== '0.0.0.0/0' && $dst !== '0.0.0.0') {
                continue;
            }
            $out[] = [
                'id' => (string) ($row['.id'] ?? ''),
                'dst' => $dst !== '' ? $dst : '0.0.0.0/0',
                'gateway' => (string) ($row['gateway'] ?? ''),
                'distance' => isset($row['distance']) && is_numeric($row['distance']) ? (int) $row['distance'] : $row['distance'] ?? null,
                'disabled' => $this->rosYes($row['disabled'] ?? 'no'),
                'active' => $this->rosYes($row['active'] ?? 'no'),
                'comment' => (string) ($row['comment'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $estado
     * @return array{accion: string, estado: array<string, mixed>}
     */
    private function entrarFailover(Router $router, array $cfg, array $estado, bool $forzarAviso): array
    {
        if ($estado['modo'] === IspFailoverConfig::MODO_FAILOVER && $estado['aviso_failover_enviado'] && ! $forzarAviso) {
            return ['accion' => 'ya_en_failover', 'estado' => $estado];
        }

        $erroresRuta = [];
        if ($cfg['auto_failover']) {
            $erroresRuta = $this->aplicarRutas($router, $cfg, failover: true);
        }

        $estado['modo'] = IspFailoverConfig::MODO_FAILOVER;
        $estado['isp_activa'] = 2;
        $estado['failover_at'] = now()->toIso8601String();
        $estado['aviso_failback_enviado'] = false;
        $estado['ultimo_evento'] = 'failover';
        if ($erroresRuta !== []) {
            $estado['last_error'] = implode('; ', $erroresRuta);
        }

        $titulo = sprintf(
            '%s sin internet (ping %s). Failover activo hacia %s.',
            $cfg['isp1_nombre'],
            $cfg['ping_host'],
            $cfg['isp2_nombre']
        );
        $enviado = $this->avisarWhatsapp($titulo, $cfg, $router, $forzarAviso);
        if ($enviado || $estado['aviso_failover_enviado']) {
            $estado['aviso_failover_enviado'] = true;
        }

        Log::warning('[isp-failover] FAILOVER a ISP 2', [
            'router_id' => $router->router_id,
            'auto' => $cfg['auto_failover'],
            'rutas' => $erroresRuta,
            'whatsapp' => $enviado,
        ]);

        return ['accion' => 'failover', 'estado' => $estado];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>  $estado
     * @return array{accion: string, estado: array<string, mixed>}
     */
    private function salirFailover(Router $router, array $cfg, array $estado, bool $forzarAviso): array
    {
        if ($estado['modo'] !== IspFailoverConfig::MODO_FAILOVER && ! $forzarAviso) {
            $estado['aviso_failover_enviado'] = false;

            return ['accion' => 'ya_en_primario', 'estado' => $estado];
        }

        $erroresRuta = [];
        if ($cfg['auto_failover']) {
            $erroresRuta = $this->aplicarRutas($router, $cfg, failover: false);
        }

        $estado['modo'] = IspFailoverConfig::MODO_PRIMARIO;
        $estado['isp_activa'] = 1;
        $estado['failback_at'] = now()->toIso8601String();
        $estado['aviso_failover_enviado'] = false;
        $estado['ultimo_evento'] = 'failback';
        if ($erroresRuta !== []) {
            $estado['last_error'] = implode('; ', $erroresRuta);
        }

        $debeAvisar = $forzarAviso || ! $estado['aviso_failback_enviado'];
        $titulo = sprintf(
            '%s recuperado (ping %s). Volvió a ser la salida principal. %s queda de respaldo.',
            $cfg['isp1_nombre'],
            $cfg['ping_host'],
            $cfg['isp2_nombre']
        );
        $enviado = $debeAvisar ? $this->avisarWhatsapp($titulo, $cfg, $router, $forzarAviso) : false;
        if ($enviado) {
            $estado['aviso_failback_enviado'] = true;
        }

        Log::info('[isp-failover] FAILBACK a ISP 1', [
            'router_id' => $router->router_id,
            'whatsapp' => $enviado,
        ]);

        return ['accion' => 'failback', 'estado' => $estado];
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return list<string>
     */
    private function aplicarRutas(Router $router, array $cfg, bool $failover): array
    {
        $errores = [];
        $isp1 = $this->buscarRutas($router, $cfg['isp1_ruta_comentario'], $cfg['isp1_gateway']);
        $isp2 = $this->buscarRutas($router, $cfg['isp2_ruta_comentario'], $cfg['isp2_gateway']);

        if ($isp1 === []) {
            $errores[] = 'No se encontró ruta default de '.$cfg['isp1_nombre'].' (comentario o gateway).';
        }
        if ($isp2 === []) {
            $errores[] = 'No se encontró ruta default de '.$cfg['isp2_nombre'].' (comentario o gateway).';
        }

        foreach ($isp1 as $ruta) {
            $id = (string) ($ruta['.id'] ?? '');
            $r = $this->mikrotik->setIpv4RouteDisabled($router, $id, $failover);
            if (! ($r['success'] ?? false)) {
                $errores[] = $cfg['isp1_nombre'].': '.($r['error'] ?? 'error al cambiar ruta');
            }
        }

        foreach ($isp2 as $ruta) {
            $id = (string) ($ruta['.id'] ?? '');
            // En failover, ISP 2 debe estar habilitada; en primario también (respaldo).
            $r = $this->mikrotik->setIpv4RouteDisabled($router, $id, false);
            if (! ($r['success'] ?? false)) {
                $errores[] = $cfg['isp2_nombre'].': '.($r['error'] ?? 'error al cambiar ruta');
            }
        }

        return $errores;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buscarRutas(Router $router, string $comentario, string $gateway): array
    {
        $comentario = trim($comentario);
        $gateway = trim($gateway);
        $matched = [];

        foreach ($this->mikrotik->getIpv4Routes($router) as $row) {
            $dst = trim((string) ($row['dst-address'] ?? ''));
            if ($dst !== '0.0.0.0/0' && $dst !== '0.0.0.0') {
                continue;
            }
            $c = trim((string) ($row['comment'] ?? ''));
            $gw = trim((string) ($row['gateway'] ?? ''));
            $okComment = $comentario !== '' && strcasecmp($c, $comentario) === 0;
            $okGw = $gateway !== '' && $gw === $gateway;
            if ($okComment || $okGw) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $cfg
     */
    private function avisarWhatsapp(string $titulo, array $cfg, Router $router, bool $esPrueba): bool
    {
        $destinatarios = IspFailoverConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            Log::info('[isp-failover] aviso omitido: sin destinatarios');

            return false;
        }

        return $this->whatsapp->ispFailover(
            $titulo,
            (string) $cfg['ping_host'],
            (string) ($router->nombre ?: ('#'.$router->router_id)),
            $destinatarios,
            $esPrueba
        );
    }

    private function rosYes(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['yes', 'true', '1'], true);
    }
}
