<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\MikroTikService;
use App\Services\Ubnt\UbntAntenaService;
use App\Support\HerramientasRedPayload;
use Illuminate\Support\Facades\Log;

/**
 * Lectura de enlace, puerto, equipos, ping CPE y óptica. No cambia la configuración.
 */
class RecolectorDiagnostico
{
    /** RX óptica GPON por debajo de este umbral se considera mala (dBm). */
    public const OPTICA_RX_MALA_DBM = -28.0;

    public function __construct(
        private readonly MikroTikService $mikrotik,
        private readonly UbntAntenaService $ubnt,
        private readonly GenieAcsService $genie,
        private readonly HuaweiOnuService $huawei,
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
            'ping_cpe_ok' => null,
            'ping_cpe_ms' => null,
            'mac' => null,
            'uptime' => null,
            'bytes_in' => null,
            'bytes_out' => null,
            'arp_presente' => null,
            'sitio' => $sitio,
            'sitio_ok' => null,
            'sitio_ms' => null,
            'lan_speed' => null,
            'lan_conectado' => null,
            'senal_antena' => null,
            'chain_delta' => null,
            'optica_rx_dbm' => null,
            'optica_tx_dbm' => null,
            'optica_ok' => null,
            'optica_fuente' => null,
            'accesos_fallidos' => [],
            'es_fibra' => HerramientasRedPayload::esFibra($servicio),
            'es_antena' => HerramientasRedPayload::esAntena($servicio),
            'equipos' => [],
            'causas' => [],
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
                    $medicion['mac'] = $red['mac'] ?? null;
                    $medicion['uptime'] = $red['uptime'] ?? null;
                    $medicion['bytes_in'] = $red['bytes_in'] ?? null;
                    $medicion['bytes_out'] = $red['bytes_out'] ?? null;
                    $medicion['arp_presente'] = isset($red['detalle']['arp']);
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

            $ipCpe = trim((string) ($servicio->ip ?? ''));
            if ($ipCpe !== '' && filter_var($ipCpe, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $cpe = $this->ping($router, $ipCpe, $src !== '' ? $src : null);
                if ($cpe !== null) {
                    $medicion['pudo_medir'] = true;
                    $medicion['ping_cpe_ok'] = $cpe['ok'];
                    $medicion['ping_cpe_ms'] = $cpe['ms'];
                }
            }
        }

        $ipAntena = trim((string) ($servicio->ip ?? ''));
        if ($ipAntena !== '' && ($medicion['es_antena'] ?? false)) {
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
                $this->anotarAcceso($servicio, $medicion, 'ssh', $ap);
            } catch (\Throwable $e) {
                Log::warning('[soporte] antena', ['error' => $e->getMessage()]);
                $this->anotarAcceso($servicio, $medicion, 'ssh', ['success' => false, 'message' => $e->getMessage()]);
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

        if ($medicion['es_fibra'] ?? false) {
            $this->medirOptica($servicio, $medicion);
        }

        try {
            $hosts = $this->genie->hosts($servicio);
            if (($hosts['success'] ?? false) === true && is_array($hosts['hosts'] ?? null)) {
                $medicion['pudo_medir'] = true;
                $medicion['equipos'] = $this->mezclarEquipos($medicion['equipos'], $hosts['hosts']);
            }
            $this->anotarAcceso($servicio, $medicion, 'acs', $hosts);
        } catch (\Throwable $e) {
            Log::warning('[soporte] acs', ['error' => $e->getMessage()]);
            $this->anotarAcceso($servicio, $medicion, 'acs', ['success' => false, 'message' => $e->getMessage()]);
        }

        $medicion['causas'] = $this->derivarCausas($medicion);

        return $medicion;
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @param  array<string, mixed>  $respuesta
     */
    private function anotarAcceso(Servicio $servicio, array &$medicion, string $via, array $respuesta): void
    {
        $linea = InformeAccesoCpe::registrarSiFalla($servicio, $via, $respuesta);
        if ($linea !== null) {
            $medicion['accesos_fallidos'][] = $linea;
        }
    }

    /**
     * @param  array<string, mixed>  $medicion
     */
    private function medirOptica(Servicio $servicio, array &$medicion): void
    {
        try {
            $optica = $this->huawei->leerOptica($servicio);
            if (($optica['success'] ?? false) === true) {
                $medicion['pudo_medir'] = true;
                $medicion['optica_rx_dbm'] = $optica['rx_power_dbm'] ?? null;
                $medicion['optica_tx_dbm'] = $optica['tx_power_dbm'] ?? null;
                $medicion['optica_fuente'] = 'huawei_live';
                $rx = $medicion['optica_rx_dbm'];
                if (is_numeric($rx)) {
                    $medicion['optica_ok'] = (float) $rx > self::OPTICA_RX_MALA_DBM;
                }

                return;
            }
        } catch (\Throwable $e) {
            Log::warning('[soporte] optica_live', ['error' => $e->getMessage()]);
        }

        try {
            $ultimo = ServicioConexionEvento::query()
                ->where('servicio_id', $servicio->servicio_id)
                ->where('tipo', ServicioConexionEvento::TIPO_SENAL_OPTICA)
                ->orderByDesc('id')
                ->first();
            if ($ultimo && $ultimo->rx_power_dbm !== null) {
                $medicion['pudo_medir'] = true;
                $medicion['optica_rx_dbm'] = (float) $ultimo->rx_power_dbm;
                $medicion['optica_fuente'] = 'evento_historial';
                $medicion['optica_ok'] = (float) $ultimo->rx_power_dbm > self::OPTICA_RX_MALA_DBM;
            }
        } catch (\Throwable $e) {
            Log::warning('[soporte] optica_hist', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $medicion
     * @return list<string>
     */
    private function derivarCausas(array $medicion): array
    {
        $causas = [];
        if (($medicion['sesion_online'] ?? null) === false) {
            $causas[] = 'sesion_offline';
        }
        if (($medicion['ping_cpe_ok'] ?? null) === false) {
            $causas[] = 'cpe_sin_respuesta';
        }
        if (($medicion['ping_general_ok'] ?? null) === false) {
            $causas[] = 'salida_pop_caida';
        }
        if (($medicion['optica_ok'] ?? null) === false && ($medicion['optica_rx_dbm'] ?? null) !== null) {
            $causas[] = 'optica_mala';
        }
        $lan = $medicion['lan_speed'] ?? null;
        if (($medicion['lan_conectado'] ?? null) === false || (is_numeric($lan) && (int) $lan > 0 && (int) $lan < 1000)) {
            $causas[] = 'cable_lan';
        }
        $delta = $medicion['chain_delta'] ?? null;
        $senal = $medicion['senal_antena'] ?? null;
        if ((is_numeric($delta) && (float) $delta > 4) || (is_numeric($senal) && (int) $senal <= -75)) {
            $causas[] = 'antena_debil';
        }

        return $causas;
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
    private function ping(Router $router, string $destino, ?string $src): ?array
    {
        try {
            $ping = $this->mikrotik->pingFromRouter($router, $destino, 2, $src ?: null);
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
