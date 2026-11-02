<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Router;
use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\MikroTikService;
use App\Services\Ubnt\UbntAntenaService;
use Illuminate\Support\Facades\Log;

/**
 * Lectura de enlace, puerto, equipos y ping. No cambia la configuración.
 */
class RecolectorDiagnostico
{
    public function __construct(
        private readonly MikroTikService $mikrotik,
        private readonly UbntAntenaService $ubnt,
        private readonly GenieAcsService $genie,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function medir(Servicio $servicio, ?string $sitio = null): array
    {
        $medicion = [
            'pudo_medir' => false,
            'sesion_online' => null,
            'ping_general_ok' => null,
            'ping_general_ms' => null,
            'sitio' => $sitio,
            'sitio_ok' => null,
            'sitio_ms' => null,
            'lan_speed' => null,
            'lan_conectado' => null,
            'senal_antena' => null,
            'chain_delta' => null,
            'equipos' => [],
        ];

        $router = $servicio->pool?->router;
        if ($router) {
            try {
                $red = $this->mikrotik->consultarClienteRed(
                    $router,
                    $servicio->ip,
                    $servicio->usuario_pppoe,
                );
                if (($red['success'] ?? false) === true) {
                    $medicion['pudo_medir'] = true;
                    $medicion['sesion_online'] = (bool) ($red['online'] ?? false);
                }
            } catch (\Throwable $e) {
                Log::warning('[soporte] sesion', ['error' => $e->getMessage()]);
            }

            $src = trim((string) $router->ip);
            if ($src !== '') {
                $general = $this->ping($router, '1.1.1.1', $src);
                if ($general !== null) {
                    $medicion['pudo_medir'] = true;
                    $medicion['ping_general_ok'] = $general['ok'];
                    $medicion['ping_general_ms'] = $general['ms'];
                }
                if ($sitio) {
                    $ip = $this->resolverSitio($sitio);
                    if ($ip) {
                        $sitioPing = $this->ping($router, $ip, $src);
                        if ($sitioPing !== null) {
                            $medicion['sitio_ok'] = $sitioPing['ok'];
                            $medicion['sitio_ms'] = $sitioPing['ms'];
                        }
                    }
                }
            }
        }

        $ipAntena = trim((string) ($servicio->ip ?? ''));
        if ($ipAntena !== '' && \App\Support\HerramientasRedPayload::esAntena($servicio)) {
            try {
                $ap = $this->ubnt->consultarApAirOs($ipAntena);
                if (($ap['success'] ?? false) === true) {
                    $medicion['pudo_medir'] = true;
                    $medicion['lan_speed'] = $ap['lan_speed'] ?? null;
                    $medicion['lan_conectado'] = $ap['lan_conectado'] ?? null;
                    $estacion = is_array($ap['estaciones_detalle'][0] ?? null) ? $ap['estaciones_detalle'][0] : [];
                    $medicion['senal_antena'] = $estacion['signal_dbm'] ?? ($ap['extra']['signal'] ?? null);
                    $medicion['chain_delta'] = $estacion['chain_delta'] ?? null;
                }
            } catch (\Throwable $e) {
                Log::warning('[soporte] antena', ['error' => $e->getMessage()]);
            }

            try {
                $dhcp = $this->ubnt->consultarDhcpLeases($ipAntena);
                if (($dhcp['success'] ?? false) === true && is_array($dhcp['leases'] ?? null)) {
                    foreach ($dhcp['leases'] as $lease) {
                        if (! is_array($lease)) {
                            continue;
                        }
                        $medicion['equipos'][] = $this->equipo(
                            (string) ($lease['hostname'] ?? ''),
                            (string) ($lease['mac'] ?? ''),
                            (string) ($lease['ip'] ?? ''),
                            null,
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[soporte] dhcp', ['error' => $e->getMessage()]);
            }
        }

        try {
            $hosts = $this->genie->hosts($servicio);
            if (($hosts['success'] ?? false) === true && is_array($hosts['hosts'] ?? null)) {
                $medicion['pudo_medir'] = true;
                $medicion['equipos'] = $this->mezclarEquipos($medicion['equipos'], $hosts['hosts']);
            }
        } catch (\Throwable $e) {
            Log::warning('[soporte] acs', ['error' => $e->getMessage()]);
        }

        return $medicion;
    }

    /**
     * @param  list<array<string, mixed>>  $actuales
     * @param  list<array<string, mixed>>  $hosts
     * @return list<array<string, mixed>>
     */
    private function mezclarEquipos(array $actuales, array $hosts): array
    {
        $porMac = [];
        foreach ($actuales as $equipo) {
            $mac = strtolower((string) ($equipo['mac'] ?? ''));
            if ($mac !== '') {
                $porMac[$mac] = $equipo;
            }
        }

        $salida = [];
        foreach ($hosts as $host) {
            if (! is_array($host)) {
                continue;
            }
            $mac = strtolower((string) ($host['mac'] ?? ''));
            $prev = $mac !== '' && isset($porMac[$mac]) ? $porMac[$mac] : [];
            $salida[] = $this->equipo(
                (string) ($host['hostname'] ?? $prev['hostname'] ?? ''),
                (string) ($host['mac'] ?? $prev['mac'] ?? ''),
                (string) ($host['ip'] ?? $prev['ip'] ?? ''),
                isset($host['rssi']) && is_numeric($host['rssi']) ? (int) $host['rssi'] : ($prev['rssi'] ?? null),
            );
            if ($mac !== '') {
                unset($porMac[$mac]);
            }
        }

        foreach ($porMac as $resto) {
            $salida[] = $resto;
        }

        if ($salida === []) {
            return $actuales;
        }

        return array_values($salida);
    }

    /**
     * @return array{nombre: string, hostname: string, mac: string, ip: string, rssi: ?int}
     */
    private function equipo(string $hostname, string $mac, string $ip, ?int $rssi): array
    {
        $nombre = trim($hostname);
        if ($nombre === '' || filter_var($nombre, FILTER_VALIDATE_IP)) {
            $nombre = 'Equipo';
        }

        return [
            'nombre' => $nombre,
            'hostname' => $hostname,
            'mac' => $mac,
            'ip' => $ip,
            'rssi' => $rssi,
        ];
    }

    /**
     * @return array{ok: bool, ms: ?int}|null
     */
    private function ping(Router $router, string $destino, string $src): ?array
    {
        try {
            $ping = $this->mikrotik->pingFromRouter($router, $destino, 2, $src);
        } catch (\Throwable $e) {
            Log::warning('[soporte] ping', ['error' => $e->getMessage()]);

            return null;
        }

        if (($ping['error'] ?? null) === 'Host de ping inválido' || ($ping['error'] ?? null) === 'Falta src-address IPv4 (RouterOS 7 no permite ping por interface)') {
            return null;
        }

        return [
            'ok' => (bool) ($ping['ok'] ?? false),
            'ms' => $ping['latency_ms'] ?? null,
        ];
    }

    private function resolverSitio(string $host): ?string
    {
        $host = strtolower(trim($host));
        if (! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            return null;
        }

        $ips = @gethostbynamel($host);
        if (! is_array($ips)) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                continue;
            }

            return $ip;
        }

        return null;
    }
}
