<?php

namespace App\Services\GenieAcs;

/**
 * Extrae _value de árboles CWMP (InternetGatewayDevice / Device) en el JSON de GenieACS.
 */
final class CwmpValues
{
    public static function get(array $device, string ...$paths): mixed
    {
        foreach ($paths as $path) {
            $v = self::path($device, $path);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    public static function path(array $node, string $path): mixed
    {
        $cur = $node;
        foreach (explode('.', $path) as $part) {
            if (! is_array($cur) || ! array_key_exists($part, $cur)) {
                return null;
            }
            $cur = $cur[$part];
        }

        if (is_array($cur) && array_key_exists('_value', $cur)) {
            return $cur['_value'];
        }

        return is_scalar($cur) ? $cur : null;
    }

    /**
     * WAN / IP de gestión del CPE. No usa Device.IP.Interface.1 (suele ser el LAN br0).
     */
    public static function wanIp(array $device): ?string
    {
        $ppp = self::get(
            $device,
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ExternalIPAddress',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.ExternalIPAddress',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.2.ExternalIPAddress',
            'Device.PPP.Interface.1.IPCP.LocalIPAddress',
        );
        if (self::ipUtil($ppp) && ! self::esIpLanTipica((string) $ppp)) {
            return (string) $ppp;
        }

        $dhcp = self::wanIpDesdeDhcpClient($device);
        if ($dhcp !== null) {
            return $dhcp;
        }

        $cr = self::ipDesdeConnectionRequest($device);
        if ($cr !== null) {
            return $cr;
        }

        if (self::ipUtil($ppp)) {
            return (string) $ppp;
        }

        return self::wanIpDesdeIpInterface($device);
    }

    public static function wanMac(array $device): ?string
    {
        $links = [];
        self::walkEthernetLinks($device, $links);
        usort($links, fn (array $a, array $b) => ($b['bytes'] <=> $a['bytes']));
        foreach ($links as $link) {
            if ($link['mac'] !== null) {
                return $link['mac'];
            }
        }

        $mac = self::get(
            $device,
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.MACAddress',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress',
            'Device.Ethernet.Link.2.MACAddress',
            'Device.Ethernet.Interface.1.MACAddress',
        );

        return $mac !== null ? strtoupper((string) $mac) : null;
    }

    public static function lanIp(array $device): ?string
    {
        $ip = self::get($device, 'Device.IP.Interface.1.IPv4Address.1.IPAddress');
        if (self::ipUtil($ip) && self::esIpLanTipica((string) $ip)) {
            return (string) $ip;
        }

        return self::ipUtil($ip) ? (string) $ip : null;
    }

    public static function connectionRequestUrl(array $device): ?string
    {
        $url = self::get(
            $device,
            'Device.ManagementServer.ConnectionRequestURL',
            'InternetGatewayDevice.ManagementServer.ConnectionRequestURL',
        );

        return is_string($url) && $url !== '' ? $url : null;
    }

    public static function connectionRequestOk(array $device): bool
    {
        $url = self::connectionRequestUrl($device);
        if ($url === null) {
            return false;
        }
        if (preg_match('#://[^/]+:0(?:/|$)#', $url) === 1) {
            return false;
        }
        $port = parse_url($url, PHP_URL_PORT);
        if ($port === 0 || $port === '0') {
            return false;
        }

        return true;
    }

    /**
     * @return list<array{ip: ?string, mac: ?string, hostname: ?string, rssi: ?int, downlink: ?int, source: string}>
     */
    public static function hosts(array $device): array
    {
        $hosts = [];
        self::walkHosts($device, $hosts);
        self::walkAssociated($device, $hosts);

        $uniq = [];
        $out = [];
        foreach ($hosts as $h) {
            $key = strtolower(($h['mac'] ?? '').'|'.($h['ip'] ?? ''));
            if ($key === '|' || isset($uniq[$key])) {
                continue;
            }
            $uniq[$key] = true;
            $out[] = $h;
        }

        return $out;
    }

    /**
     * SSIDs habilitados (TR-098 WLANConfiguration / TR-181 Device.WiFi.SSID).
     *
     * @return list<string>
     */
    public static function ssids(array $device): array
    {
        $nombres = [];
        foreach (self::wifiNetworks($device) as $net) {
            if (($net['enabled'] ?? false) && ($net['ssid'] ?? '') !== '') {
                $nombres[] = (string) $net['ssid'];
            }
        }
        if ($nombres !== []) {
            return array_values(array_unique($nombres));
        }

        $ssids = [];
        self::walkSsids($device, $ssids);

        return array_values(array_unique(array_filter($ssids)));
    }

    /**
     * Redes WiFi con path de KeyPassphrase para SetParameterValues.
     * No incluye la clave actual: el ACS casi nunca la devuelve.
     *
     * @return list<array{id: string, ssid: string, enabled: bool, band: ?string, security: ?string, passphrase_path: string, mode_path: ?string, ssid_path: string|null}>
     */
    public static function wifiNetworks(array $device): array
    {
        $tr181 = self::wifiNetworksTr181($device);
        if ($tr181 !== []) {
            return $tr181;
        }

        return self::wifiNetworksTr098($device);
    }

    public static function adminPasswordPath(array $device): ?string
    {
        foreach ([
            'Device.LANConfigSecurity.ConfigPassword',
            'InternetGatewayDevice.LANConfigSecurity.ConfigPassword',
            'Device.Users.User.1.Password',
        ] as $path) {
            if (self::nodoExiste($device, $path)) {
                return $path;
            }
        }

        return null;
    }

    public static function nodoExiste(array $device, string $path): bool
    {
        $cur = $device;
        foreach (explode('.', $path) as $part) {
            if (! is_array($cur) || ! array_key_exists($part, $cur)) {
                return false;
            }
            $cur = $cur[$part];
        }

        return true;
    }

    private static function ipDesdeConnectionRequest(array $device): ?string
    {
        $url = self::connectionRequestUrl($device);
        if ($url === null) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $host === '' || $host === '0.0.0.0') {
            return null;
        }

        return $host;
    }

    private static function wanIpDesdeDhcpClient(array $device): ?string
    {
        $client = $device['Device']['DHCPv4']['Client'] ?? null;
        if (! is_array($client)) {
            return null;
        }
        foreach ($client as $key => $inst) {
            if (! is_array($inst) || ! self::esIndiceObjeto($key)) {
                continue;
            }
            if (! self::truthy(self::path($inst, 'Enable'))) {
                continue;
            }
            $status = strtolower((string) (self::path($inst, 'Status') ?? ''));
            if (in_array($status, ['disabled', 'error', ''], true)) {
                continue;
            }
            $ip = self::path($inst, 'IPAddress');
            if (self::ipUtil($ip) && ! self::esIpLanTipica((string) $ip)) {
                return (string) $ip;
            }
        }

        return null;
    }

    private static function wanIpDesdeIpInterface(array $device): ?string
    {
        $ifs = $device['Device']['IP']['Interface'] ?? null;
        if (! is_array($ifs)) {
            return null;
        }
        foreach ($ifs as $key => $inst) {
            if (! is_array($inst) || ! self::esIndiceObjeto($key)) {
                continue;
            }
            if (self::truthy(self::path($inst, 'Loopback'))) {
                continue;
            }
            $name = strtolower((string) (self::path($inst, 'Name') ?? ''));
            if (in_array($name, ['br0', 'br-lan', 'lan', 'lo'], true)) {
                continue;
            }
            $ip = self::path($inst, 'IPv4Address.1.IPAddress');
            if (self::ipUtil($ip) && ! self::esIpLanTipica((string) $ip)) {
                return (string) $ip;
            }
        }

        return null;
    }

    /**
     * @param  list<array{mac: ?string, bytes: int}>  $out
     */
    private static function walkEthernetLinks(array $node, array &$out): void
    {
        $linkRoot = $node['Device']['Ethernet']['Link'] ?? null;
        if (is_array($linkRoot)) {
            foreach ($linkRoot as $key => $link) {
                if (! is_array($link) || ! self::esIndiceObjeto($key)) {
                    continue;
                }
                $mac = self::path($link, 'MACAddress');
                $bytes = (int) (self::path($link, 'Stats.BytesReceived') ?? 0);
                $out[] = [
                    'mac' => $mac !== null && $mac !== '' ? strtoupper((string) $mac) : null,
                    'bytes' => $bytes,
                ];
            }

            return;
        }

        foreach ($node as $key => $child) {
            if (! is_array($child) || str_starts_with((string) $key, '_')) {
                continue;
            }
            self::walkEthernetLinks($child, $out);
        }
    }

    /**
     * @param  list<array{ip: ?string, mac: ?string, hostname: ?string, rssi: ?int, downlink: ?int, source: string}>  $out
     */
    private static function walkHosts(array $node, array &$out): void
    {
        if (isset($node['Host']) && is_array($node['Host'])) {
            foreach ($node['Host'] as $key => $host) {
                if (! is_array($host) || ! self::esIndiceObjeto($key)) {
                    continue;
                }
                $ip = self::path($host, 'IPAddress')
                    ?? self::path($host, 'IPAddress.1.IPAddress');
                $mac = self::path($host, 'MACAddress');
                $name = self::path($host, 'HostName');
                if ($ip || $mac) {
                    $out[] = [
                        'ip' => $ip !== null ? (string) $ip : null,
                        'mac' => $mac !== null ? strtoupper((string) $mac) : null,
                        'hostname' => $name !== null && $name !== '' ? (string) $name : null,
                        'rssi' => null,
                        'downlink' => null,
                        'source' => 'lan',
                    ];
                }
            }
        }

        foreach ($node as $key => $child) {
            if ($key === 'Host' || ! is_array($child) || str_starts_with((string) $key, '_')) {
                continue;
            }
            self::walkHosts($child, $out);
        }
    }

    /**
     * Clientes WiFi TR-181: Device.WiFi.AccessPoint.{i}.AssociatedDevice.{j}
     *
     * @param  list<array{ip: ?string, mac: ?string, hostname: ?string, rssi: ?int, downlink: ?int, source: string}>  $out
     */
    private static function walkAssociated(array $node, array &$out): void
    {
        if (isset($node['AssociatedDevice']) && is_array($node['AssociatedDevice'])) {
            foreach ($node['AssociatedDevice'] as $key => $sta) {
                if (! is_array($sta) || ! self::esIndiceObjeto($key)) {
                    continue;
                }
                $mac = self::path($sta, 'MACAddress');
                if ($mac === null || $mac === '') {
                    continue;
                }
                $rssi = self::path($sta, 'SignalStrength');
                $down = self::path($sta, 'LastDataDownlinkRate');
                $out[] = [
                    'ip' => null,
                    'mac' => strtoupper((string) $mac),
                    'hostname' => null,
                    'rssi' => is_numeric($rssi) ? (int) $rssi : null,
                    'downlink' => is_numeric($down) ? (int) $down : null,
                    'source' => 'wifi',
                ];
            }
        }

        foreach ($node as $key => $child) {
            if ($key === 'AssociatedDevice' || ! is_array($child) || str_starts_with((string) $key, '_')) {
                continue;
            }
            self::walkAssociated($child, $out);
        }
    }

    /**
     * @return list<array{id: string, ssid: string, enabled: bool, band: ?string, security: ?string, passphrase_path: string, mode_path: ?string, ssid_path: string|null}>
     */
    private static function wifiNetworksTr181(array $device): array
    {
        $aps = $device['Device']['WiFi']['AccessPoint'] ?? null;
        if (! is_array($aps)) {
            return [];
        }
        $ssids = $device['Device']['WiFi']['SSID'] ?? [];
        $radios = $device['Device']['WiFi']['Radio'] ?? [];
        $out = [];
        foreach ($aps as $key => $ap) {
            if (! is_array($ap) || ! self::esIndiceObjeto($key)) {
                continue;
            }
            $ref = (string) (self::path($ap, 'SSIDReference') ?? '');
            $ssidIdx = null;
            if (preg_match('/SSID\.(\d+)/', $ref, $m) === 1) {
                $ssidIdx = $m[1];
            }
            $ssidNode = ($ssidIdx !== null && isset($ssids[$ssidIdx]) && is_array($ssids[$ssidIdx]))
                ? $ssids[$ssidIdx]
                : null;
            $ssidName = is_array($ssidNode) ? self::path($ssidNode, 'SSID') : null;
            $ssidEnable = is_array($ssidNode) ? self::path($ssidNode, 'Enable') : null;
            $apEnable = self::path($ap, 'Enable');
            $enabled = self::truthy($apEnable) && ($ssidEnable === null || self::truthy($ssidEnable));
            $band = null;
            if (is_array($ssidNode)) {
                $lower = (string) (self::path($ssidNode, 'LowerLayers') ?? '');
                if (preg_match('/Radio\.(\d+)/', $lower, $rm) === 1) {
                    $radio = $radios[$rm[1]] ?? null;
                    if (is_array($radio)) {
                        $band = self::path($radio, 'OperatingFrequencyBand');
                    }
                }
            }
            if (! is_string($band) || $band === '') {
                $band = self::bandaDesdeNombre(is_string($ssidName) ? $ssidName : null);
            }
            $out[] = [
                'id' => 'ap-'.$key,
                'ssid' => is_string($ssidName) && $ssidName !== '' ? $ssidName : ('AP '.$key),
                'enabled' => $enabled,
                'band' => is_string($band) && $band !== '' ? $band : null,
                'security' => self::scalarOrNull(self::path($ap, 'Security.ModeEnabled')),
                'passphrase_path' => 'Device.WiFi.AccessPoint.'.$key.'.Security.KeyPassphrase',
                'mode_path' => 'Device.WiFi.AccessPoint.'.$key.'.Security.ModeEnabled',
                'ssid_path' => $ssidIdx !== null ? 'Device.WiFi.SSID.'.$ssidIdx.'.SSID' : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{id: string, ssid: string, enabled: bool, band: ?string, security: ?string, passphrase_path: string, mode_path: ?string, ssid_path: string|null}>
     */
    private static function wifiNetworksTr098(array $device): array
    {
        $lanDev = $device['InternetGatewayDevice']['LANDevice'] ?? null;
        if (! is_array($lanDev)) {
            return [];
        }
        $out = [];
        foreach ($lanDev as $dKey => $lan) {
            if (! is_array($lan) || ! self::esIndiceObjeto($dKey)) {
                continue;
            }
            $wlans = $lan['WLANConfiguration'] ?? null;
            if (! is_array($wlans)) {
                continue;
            }
            foreach ($wlans as $wKey => $wlan) {
                if (! is_array($wlan) || ! self::esIndiceObjeto($wKey)) {
                    continue;
                }
                $base = 'InternetGatewayDevice.LANDevice.'.$dKey.'.WLANConfiguration.'.$wKey;
                $ssidName = self::path($wlan, 'SSID');
                $band = self::path($wlan, 'OperatingFrequencyBand')
                    ?? self::bandaDesdeNombre(is_string($ssidName) ? $ssidName : null);
                $out[] = [
                    'id' => 'wlan-'.$dKey.'-'.$wKey,
                    'ssid' => is_string($ssidName) && $ssidName !== '' ? $ssidName : ('WLAN '.$wKey),
                    'enabled' => self::truthy(self::path($wlan, 'Enable')),
                    'band' => is_string($band) && $band !== '' ? $band : null,
                    'security' => self::scalarOrNull(self::path($wlan, 'BeaconType') ?? self::path($wlan, 'IEEE11iEncryptionMode')),
                    'passphrase_path' => $base.'.KeyPassphrase',
                    'mode_path' => null,
                    'ssid_path' => $base.'.SSID',
                ];
            }
        }

        return $out;
    }

    private static function bandaDesdeNombre(?string $ssid): ?string
    {
        if ($ssid === null || $ssid === '') {
            return null;
        }
        if (preg_match('/5\.?8?\s*G|5GHz|_5G|-5G/i', $ssid) === 1) {
            return '5GHz';
        }
        if (preg_match('/2\.4|2GHz|_2G|-2G/i', $ssid) === 1) {
            return '2.4GHz';
        }

        return null;
    }

    private static function scalarOrNull(mixed $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return is_scalar($v) ? (string) $v : null;
    }

    /**
     * @param  list<string>  $out
     */
    private static function walkSsids(array $node, array &$out): void
    {
        if (isset($node['SSID']) && is_array($node['SSID']) && array_key_exists('_value', $node['SSID'])) {
            $v = trim((string) $node['SSID']['_value']);
            if ($v !== '') {
                $enable = self::path($node, 'Enable');
                if ($enable === null || self::truthy($enable)) {
                    $out[] = $v;
                }
            }
        }

        foreach ($node as $key => $child) {
            if (! is_array($child) || str_starts_with((string) $key, '_')) {
                continue;
            }
            self::walkSsids($child, $out);
        }
    }

    private static function truthy(mixed $v): bool
    {
        if ($v === true || $v === 1 || $v === '1') {
            return true;
        }

        return is_string($v) && strtolower($v) === 'true';
    }

    private static function ipUtil(mixed $ip): bool
    {
        if (! is_string($ip) && ! is_numeric($ip)) {
            return false;
        }
        $s = trim((string) $ip);

        return $s !== '' && $s !== '0.0.0.0';
    }

    private static function esIpLanTipica(string $ip): bool
    {
        return str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '127.')
            || (bool) preg_match('/^172\.(1[6-9]|2\d|3[0-1])\./', $ip);
    }

    private static function esIndiceObjeto(mixed $key): bool
    {
        if (is_int($key)) {
            return true;
        }
        $s = (string) $key;

        return $s !== '' && $s[0] !== '_' && ctype_digit($s);
    }
}
