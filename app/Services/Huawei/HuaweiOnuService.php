<?php

namespace App\Services\Huawei;

use App\Models\Servicio;
use App\Support\CpeInventario;
use Illuminate\Support\Facades\Cache;
use Throwable;

class HuaweiOnuService
{
    /**
     * @return array{success: bool, message: string, via?: string, wan?: string, protocol?: string, ipv6?: bool}
     */
    public function configurarIpv6(Servicio $servicio): array
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }

        // Telnet/SSH solo prende IPv6CP; DHCPv6-PD vive en la web.
        return $this->configurarIpv6Web($servicio);
    }

    /**
     * @return array{success: bool, message: string, via?: string, dispositivos?: list<array<string, string>>, raw?: string}
     */
    public function listarConectados(Servicio $servicio): array
    {
        return $this->listarDispositivosCruzados($servicio);
    }

    /**
     * @return array{success: bool, message: string, via?: string, dispositivos?: list<array<string, string>>, raw?: string}
     */
    public function listarDhcpLeases(Servicio $servicio): array
    {
        return $this->listarDispositivosCruzados($servicio);
    }

    /**
     * @return array{success: bool, message: string, via?: string, dispositivos?: list<array<string, string>>, raw?: string}
     */
    protected function listarDispositivosCruzados(Servicio $servicio): array
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }
        if (! $this->tieneCli($host)) {
            return $this->listarAsociadosWeb($servicio);
        }

        try {
            $cli = $this->cli($host);
            $out = $cli->run(
                [
                    'display wifi associate',
                    'display dhcp server user all',
                ],
                function (array $outputs): array {
                    $leases = self::parseDhcpUsers($outputs[1] ?? '');

                    return self::comandosDetalleDhcp($leases);
                }
            );
            $estaciones = self::parseWifiAssociate($out['outputs'][0] ?? '');
            $leases = self::enriquecerHostsDhcp(
                self::parseDhcpUsers($out['outputs'][1] ?? ''),
                array_slice($out['outputs'], 2)
            );
            $dispositivos = self::cruzarDispositivos($estaciones, $leases);

            return [
                'success' => true,
                'message' => $dispositivos === []
                    ? 'Ningún dispositivo en WiFi ni DHCP.'
                    : count($dispositivos).' dispositivo(s).',
                'via' => $out['via'],
                'dispositivos' => $dispositivos,
                'raw' => trim(($out['outputs'][0] ?? '')."\n\n".($out['outputs'][1] ?? '')),
            ];
        } catch (Throwable $e) {
            return $this->listarAsociadosWeb($servicio, $e->getMessage());
        }
    }

    /**
     * @param  list<array<string, string>>  $estaciones
     * @param  list<array<string, string>>  $leases
     * @return list<array{host: string, ip: string, mac: string, ssid: string, tiempo: string}>
     */
    public static function cruzarDispositivos(array $estaciones, array $leases): array
    {
        $byMac = [];
        foreach ($estaciones as $est) {
            $mac = self::normalizarMac($est['mac'] ?? '');
            if ($mac === '') {
                continue;
            }
            $byMac[$mac] = [
                'host' => '',
                'ip' => '',
                'mac' => $mac,
                'ssid' => trim((string) ($est['ssid'] ?? '')),
                'tiempo' => trim((string) ($est['tiempo'] ?? '')),
            ];
        }
        foreach ($leases as $lease) {
            $mac = self::normalizarMac($lease['mac'] ?? '');
            if ($mac === '') {
                continue;
            }
            $row = $byMac[$mac] ?? [
                'host' => '',
                'ip' => '',
                'mac' => $mac,
                'ssid' => '',
                'tiempo' => '',
            ];
            $row['host'] = trim((string) ($lease['host'] ?? ''));
            $row['ip'] = trim((string) ($lease['ip'] ?? ''));
            $byMac[$mac] = $row;
        }

        return array_values($byMac);
    }

    public static function normalizarMac(string $mac): string
    {
        $mac = strtoupper(str_replace('-', ':', trim($mac)));

        return preg_match('/^[0-9A-F]{2}(?::[0-9A-F]{2}){5}$/', $mac) ? $mac : '';
    }

    /**
     * @return array{success: bool, message: string, via?: string, ssids?: list<string>}
     */
    public function cambiarWifi(Servicio $servicio, string $ssid, string $password): array
    {
        $ssid = trim($ssid);
        $password = trim($password);
        if ($ssid === '' || strlen($ssid) > 32) {
            return ['success' => false, 'message' => 'El SSID debe tener entre 1 y 32 caracteres.'];
        }
        if (strlen($password) < 8 || strlen($password) > 63) {
            return ['success' => false, 'message' => 'La clave WiFi debe tener entre 8 y 63 caracteres.'];
        }
        if (preg_match('/[`\'&$\\\\,]/', $ssid.$password)) {
            return ['success' => false, 'message' => 'SSID o clave con caracteres no soportados por la ONU (` \' & $ \\ ,).'];
        }

        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }

        if (! $this->tieneCli($host)) {
            return $this->cambiarWifiWeb($servicio, $ssid, $password);
        }

        try {
            $cli = $this->cli($host);
            $info = $cli->run(['display wifi information']);
            $indices = self::parseWifiIndices($info['outputs'][0] ?? '');
            if ($indices === []) {
                $indices = [1, 5];
            }

            $cmds = [];
            foreach ($indices as $i => $index) {
                $name = $i === 0 ? $ssid : $ssid.'_5G';
                if (count($indices) === 1) {
                    $name = $ssid;
                }
                $cmds[] = 'set ssid index '.$index.' name '.$name.' visible 1 security WPA-WPA2-Personal password '.$password;
            }
            $cmds[] = 'save data';
            $cmds[] = 'display wifi information';

            $out = $cli->run($cmds);
            $ssids = self::parseWifiSsids($out['outputs'][array_key_last($out['outputs'])] ?? '');

            return [
                'success' => true,
                'message' => 'SSID y clave aplicados en '.count($indices).' radio(s).',
                'via' => $out['via'],
                'ssid' => self::ssidPrincipal($ssids),
                'ssids' => $ssids,
            ];
        } catch (Throwable $e) {
            return $this->cambiarWifiWeb($servicio, $ssid, $password, $e->getMessage());
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string, ssid?: string, ssids?: list<string>}
     */
    public function leerWifi(Servicio $servicio): array
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }

        if (! $this->tieneCli($host)) {
            return $this->leerWifiWeb($servicio);
        }

        try {
            $cli = $this->cli($host);
            $info = $cli->run(['display wifi information']);
            $ssids = self::parseWifiSsids($info['outputs'][0] ?? '');
            $ssid = self::ssidPrincipal($ssids);
            if ($ssid === '') {
                return [
                    'success' => false,
                    'message' => 'No se pudo leer el SSID de la ONU.',
                    'via' => $info['via'],
                    'ssids' => $ssids,
                ];
            }

            return [
                'success' => true,
                'message' => 'SSID actual: '.$ssid,
                'via' => $info['via'],
                'ssid' => $ssid,
                'ssids' => $ssids,
            ];
        } catch (Throwable $e) {
            return $this->leerWifiWeb($servicio, $e->getMessage());
        }
    }

    /**
     * Nombre 2.4 GHz (sin sufijo _5G) para el formulario.
     *
     * @param  list<string>  $ssids
     */
    public static function ssidPrincipal(array $ssids): string
    {
        foreach ($ssids as $s) {
            $s = trim((string) $s);
            if ($s === '' || preg_match('/_5G$/i', $s) === 1) {
                continue;
            }

            return $s;
        }
        foreach ($ssids as $s) {
            $s = trim((string) $s);
            if ($s !== '') {
                return (string) preg_replace('/_5G$/i', '', $s);
            }
        }

        return '';
    }

    public static function parseWanPrincipal(string $raw): ?array
    {
        if (! preg_match('/^\s*\d+\s+(\S+)\s+\S+\s+\S+\s+(\S+)/m', $raw, $m)) {
            return null;
        }
        $protocol = $m[2];

        return [
            'name' => $m[1],
            'protocol' => $protocol,
            'dual' => (bool) preg_match('/IPv6|IPv4\s*&\s*IPv6|IPv4\/IPv6/i', $protocol),
        ];
    }

    /**
     * @return list<array{mac: string, ssid: string, tiempo: string, tx: string, rx: string}>
     */
    public static function parseWifiAssociate(string $raw): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if (! preg_match('/^([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5}|[0-9A-Fa-f]{2}(?:-[0-9A-Fa-f]{2}){5})\s+(\S.*?)\s+(\d+)\s+(\S+)\s+(\S+)/', $line, $m)) {
                continue;
            }
            $rows[] = [
                'mac' => strtoupper($m[1]),
                'ssid' => $m[2],
                'tiempo' => $m[3],
                'tx' => $m[4],
                'rx' => $m[5],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array{ip: string, mac: string, host: string, puerto: string, expira: string}>
     */
    public static function parseDhcpUsers(string $raw): array
    {
        $rows = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if (! preg_match('/^(\d+)\s+(\S+)\s+(\d+\.\d+\.\d+\.\d+)\s+(\S+)\s+([0-9A-Fa-f]{2}(?::[0-9A-Fa-f]{2}){5}|[0-9A-Fa-f]{2}(?:-[0-9A-Fa-f]{2}){5})\s+(.+)$/', $line, $m)) {
                continue;
            }
            $rows[] = [
                'index' => $m[1],
                'puerto' => $m[2],
                'ip' => $m[3],
                'host' => $m[4],
                'mac' => strtoupper($m[5]),
                'expira' => trim($m[6]),
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array<string, string>>  $leases
     * @return list<string>
     */
    public static function comandosDetalleDhcp(array $leases): array
    {
        $cmds = [];
        foreach ($leases as $lease) {
            $index = (int) ($lease['index'] ?? 0);
            if ($index < 1 || $index > 32) {
                continue;
            }
            $cmds[] = 'display dhcp server user index '.$index;
            if (count($cmds) >= 16) {
                break;
            }
        }

        return $cmds;
    }

    /**
     * @param  list<array<string, string>>  $leases
     * @param  list<string>  $detalles
     * @return list<array<string, string>>
     */
    public static function enriquecerHostsDhcp(array $leases, array $detalles): array
    {
        foreach ($leases as $i => $lease) {
            $nombre = self::parseDhcpHostName($detalles[$i] ?? '');
            if ($nombre !== '') {
                $leases[$i]['host'] = $nombre;
            }
        }

        return $leases;
    }

    public static function parseDhcpHostName(string $raw): string
    {
        if (! preg_match('/Host\s*Name\s*:\s*(.+)$/mi', $raw, $m)) {
            return '';
        }
        $nombre = trim($m[1]);
        if ($nombre === '' || strcasecmp($nombre, 'success!') === 0) {
            return '';
        }

        return $nombre;
    }

    /**
     * @return list<int>
     */
    public static function parseWifiIndices(string $raw): array
    {
        preg_match_all('/SSID Index\s*:\s*(\d+)/i', $raw, $m);

        return array_values(array_unique(array_map('intval', $m[1] ?? [])));
    }

    /**
     * @return list<string>
     */
    public static function parseWifiSsids(string $raw): array
    {
        preg_match_all('/^SSID\s+:\s*(.+)$/mi', $raw, $m);

        return array_values(array_filter(array_map('trim', $m[1] ?? [])));
    }

    /**
     * @return array{success: bool, message: string, via?: string, rx_power_dbm?: float|null, tx_power_dbm?: float|null, temperatura_c?: float|null, raw?: string}
     */
    public function leerOptica(Servicio $servicio): array
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }

        if (! $this->tieneCli($host)) {
            return $this->leerOpticaWeb($servicio);
        }

        try {
            $cli = $this->cli($host);
            $info = $cli->run(['display optic']);
            $optic = self::parseOptic($info['outputs'][0] ?? '');
            if ($optic['rx_power_dbm'] === null && $optic['tx_power_dbm'] === null) {
                return [
                    'success' => false,
                    'message' => 'No se pudo leer la potencia óptica de la ONU.',
                    'via' => $info['via'],
                    'raw' => $info['outputs'][0] ?? '',
                ];
            }

            $parts = [];
            if ($optic['rx_power_dbm'] !== null) {
                $parts[] = 'RX '.$optic['rx_power_dbm'].' dBm';
            }
            if ($optic['tx_power_dbm'] !== null) {
                $parts[] = 'TX '.$optic['tx_power_dbm'].' dBm';
            }
            if ($optic['temperatura_c'] !== null) {
                $parts[] = $optic['temperatura_c'].' °C';
            }

            return [
                'success' => true,
                'message' => implode(' · ', $parts),
                'via' => $info['via'],
                'rx_power_dbm' => $optic['rx_power_dbm'],
                'tx_power_dbm' => $optic['tx_power_dbm'],
                'temperatura_c' => $optic['temperatura_c'],
                'raw' => $info['outputs'][0] ?? '',
            ];
        } catch (Throwable $e) {
            return $this->leerOpticaWeb($servicio, $e->getMessage());
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string}
     */
    public function reiniciar(Servicio $servicio): array
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            return ['success' => false, 'message' => 'El servicio no tiene IP para conectar a la ONU.'];
        }

        if (! $this->tieneCli($host)) {
            return $this->reiniciarWeb($servicio);
        }

        try {
            $cli = $this->cli($host);
            $out = $cli->reboot();

            return [
                'success' => true,
                'message' => 'Reinicio enviado. La ONU tarda unos minutos en volver.',
                'via' => $out['via'],
            ];
        } catch (Throwable $e) {
            return $this->reiniciarWeb($servicio, $e->getMessage());
        }
    }

    /**
     * @return array{rx_power_dbm: float|null, tx_power_dbm: float|null, temperatura_c: float|null}
     */
    public static function parseOptic(string $raw): array
    {
        return [
            'rx_power_dbm' => self::opticNumero($raw, [
                'Rx power', 'RX power', 'Rx optical power', 'RX optical power',
                'Receive optical power', 'RxPower',
            ]),
            'tx_power_dbm' => self::opticNumero($raw, [
                'Tx power', 'TX power', 'Tx optical power', 'TX optical power',
                'Transmit optical power', 'TxPower',
            ]),
            'temperatura_c' => self::opticNumero($raw, ['Temperature', 'Temp']),
        ];
    }

    /**
     * @param  list<string>  $etiquetas
     */
    protected static function opticNumero(string $raw, array $etiquetas): ?float
    {
        foreach ($etiquetas as $etiqueta) {
            $pat = '/'.preg_quote($etiqueta, '/').'\s*(?:\([^)]+\))?\s*[:=]\s*(-?\d+(?:[.,]\d+)?)/i';
            if (preg_match($pat, $raw, $m)) {
                return (float) str_replace(',', '.', $m[1]);
            }
        }

        return null;
    }

    public function puedeOperar(Servicio $servicio): bool
    {
        if (CpeInventario::esHuaweiOnu($servicio)) {
            return true;
        }
        $host = $this->hostDe($servicio);

        return $host !== null && HuaweiOnuWeb::detectarCacheado($host);
    }

    /**
     * @return array{success: bool, message: string, via?: string, rx_power_dbm?: float|null, tx_power_dbm?: float|null, temperatura_c?: float|null, raw?: string}
     */
    protected function leerOpticaWeb(Servicio $servicio, ?string $cliError = null): array
    {
        try {
            $web = $this->webDe($servicio);
            $optic = $web->leerOptica();
            if ($optic['rx_power_dbm'] === null && $optic['tx_power_dbm'] === null) {
                throw new \RuntimeException('No se pudo leer la potencia óptica de la web.');
            }
            $this->marcarHuaweiSiFalta($servicio);
            $parts = [];
            if ($optic['rx_power_dbm'] !== null) {
                $parts[] = 'RX '.$optic['rx_power_dbm'].' dBm';
            }
            if ($optic['tx_power_dbm'] !== null) {
                $parts[] = 'TX '.$optic['tx_power_dbm'].' dBm';
            }
            if ($optic['temperatura_c'] !== null) {
                $parts[] = $optic['temperatura_c'].' °C';
            }

            return [
                'success' => true,
                'message' => implode(' · ', $parts),
                'via' => 'web',
                'rx_power_dbm' => $optic['rx_power_dbm'],
                'tx_power_dbm' => $optic['tx_power_dbm'],
                'temperatura_c' => $optic['temperatura_c'],
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string}
     */
    protected function reiniciarWeb(Servicio $servicio, ?string $cliError = null): array
    {
        try {
            $this->webDe($servicio, true)->reiniciar();
            $this->marcarHuaweiSiFalta($servicio);

            return [
                'success' => true,
                'message' => 'Reinicio enviado por web. La ONU tarda unos minutos en volver.',
                'via' => 'web',
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string, wan?: string, protocol?: string, ipv6?: bool}
     */
    protected function configurarIpv6Web(Servicio $servicio, ?string $cliError = null): array
    {
        try {
            $wan = $this->webDe($servicio, true)->encenderIpv6();
            $this->marcarHuaweiSiFalta($servicio);
            $servicio->update(['ipv6_configurado' => true]);
            $msg = 'IPv6 con DHCPv6-PD en '.$wan['name']
                .' (prefijo '.$wan['prefix_origin'].', IP '.$wan['address_origin'].').';

            return [
                'success' => true,
                'message' => $msg,
                'via' => 'web',
                'wan' => $wan['name'],
                'protocol' => 'IPv4/IPv6',
                'ipv6' => true,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string, ssid?: string, ssids?: list<string>}
     */
    protected function leerWifiWeb(Servicio $servicio, ?string $cliError = null): array
    {
        try {
            $ssids = $this->webDe($servicio)->leerSsids();
            $ssid = self::ssidPrincipal($ssids);
            if ($ssid === '') {
                throw new \RuntimeException('No se pudo leer el SSID de la web.');
            }
            $this->marcarHuaweiSiFalta($servicio);

            return [
                'success' => true,
                'message' => 'SSID actual: '.$ssid,
                'via' => 'web',
                'ssid' => $ssid,
                'ssids' => $ssids,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string, ssid?: string, ssids?: list<string>}
     */
    protected function cambiarWifiWeb(Servicio $servicio, string $ssid, string $password, ?string $cliError = null): array
    {
        try {
            $ssids = $this->webDe($servicio, true)->cambiarWifi($ssid, $password);
            $this->marcarHuaweiSiFalta($servicio);

            return [
                'success' => true,
                'message' => 'SSID y clave aplicados por web.',
                'via' => 'web',
                'ssid' => self::ssidPrincipal($ssids) ?: $ssid,
                'ssids' => $ssids,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    /**
     * @return array{success: bool, message: string, via?: string, dispositivos?: list<array<string, string>>}
     */
    protected function listarAsociadosWeb(Servicio $servicio, ?string $cliError = null): array
    {
        try {
            $dispositivos = $this->webDe($servicio)->listarAsociados();
            $this->marcarHuaweiSiFalta($servicio);

            return [
                'success' => true,
                'message' => $dispositivos === []
                    ? 'Ningún cliente WiFi asociado.'
                    : count($dispositivos).' dispositivo(s).',
                'via' => 'web',
                'dispositivos' => $dispositivos,
            ];
        } catch (Throwable $e) {
            return ['success' => false, 'message' => $this->mensajeMixto($cliError, $e->getMessage())];
        }
    }

    protected function webDe(Servicio $servicio, bool $cambiar = false): HuaweiOnuWeb
    {
        $host = $this->hostDe($servicio);
        if ($host === null) {
            throw new \RuntimeException('El servicio no tiene IP para conectar a la ONU.');
        }
        $web = new HuaweiOnuWeb(
            $host,
            (int) config('huawei.web_port', 80),
            (int) config('huawei.web_timeout', 15),
        );
        $web->login($this->cuentasWeb($cambiar));

        return $web;
    }

    /**
     * telecomadmin puede aplicar cambios; root solo sirve para lectura.
     *
     * @return list<array{user: string, password: string}>
     */
    protected function cuentasWeb(bool $cambiar): array
    {
        $cuentas = [[
            'user' => (string) config('huawei.web_user', 'telecomadmin'),
            'password' => (string) config('huawei.web_password', 'admintelecom'),
        ]];
        if (! $cambiar) {
            $cuentas[] = [
                'user' => (string) config('huawei.onu_user', 'root'),
                'password' => (string) config('huawei.onu_password', 'admin'),
            ];
        }

        return $cuentas;
    }

    protected function tieneCli(string $host): bool
    {
        return $this->tcpAbierto($host, (int) config('huawei.ssh_port', 22), 1.2)
            || $this->tcpAbierto($host, (int) config('huawei.telnet_port', 23), 1.0);
    }

    protected function tcpAbierto(string $host, int $port, float $timeout): bool
    {
        $fp = @stream_socket_client('tcp://'.$host.':'.$port, $errno, $errstr, $timeout);
        if (is_resource($fp)) {
            fclose($fp);

            return true;
        }

        return false;
    }

    protected function marcarHuaweiSiFalta(Servicio $servicio): void
    {
        if (filled($servicio->cpe_onu)) {
            return;
        }
        $servicio->update(['cpe_onu' => 'huawei']);
        Cache::put('huawei-web-'.$servicio->ip, true, 3600);
    }

    protected function mensajeMixto(?string $cliError, string $webError): string
    {
        return $cliError ? $cliError.' · Web: '.$webError : $webError;
    }

    protected function cli(string $host): HuaweiOnuCli
    {
        return new HuaweiOnuCli(
            $host,
            (string) config('huawei.onu_user', 'root'),
            (string) config('huawei.onu_password', 'admin'),
            (int) config('huawei.ssh_port', 22),
            (int) config('huawei.telnet_port', 23),
            (int) config('huawei.timeout', 12),
        );
    }

    protected function hostDe(Servicio $servicio): ?string
    {
        $ip = trim((string) ($servicio->ip ?? ''));

        return $ip !== '' ? $ip : null;
    }
}
