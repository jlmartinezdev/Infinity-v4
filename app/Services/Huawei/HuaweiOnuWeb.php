<?php

namespace App\Services\Huawei;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class HuaweiOnuWeb
{
    /** Prefijo CGI para crear instancias IPv6 (EG8145V5). */
    private const CGI_ALTA = 'Add_';

    private Client $http;

    private CookieJar $jar;

    public function __construct(
        private string $host,
        private int $port = 80,
        private int $timeout = 12,
    ) {
        $this->jar = new CookieJar();
        $this->http = new Client([
            'base_uri' => 'https://'.$this->host.':'.$this->port,
            'verify' => false,
            'cookies' => $this->jar,
            'timeout' => $this->timeout,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0',
                'Referer' => 'https://'.$this->host.':'.$this->port.'/',
            ],
        ]);
    }

    public static function detectarCacheado(string $host, int $port = 80, int $timeout = 3): bool
    {
        $key = 'huawei-web-'.$host;
        $cached = Cache::get($key);
        if (is_bool($cached)) {
            return $cached;
        }
        $ok = self::pareceHuawei($host, $port, $timeout);
        Cache::put($key, $ok, $ok ? 3600 : 120);

        return $ok;
    }

    public static function pareceHuawei(string $host, int $port = 80, int $timeout = 3): bool
    {
        try {
            $html = (new self($host, $port, $timeout))->get('/');
        } catch (Throwable) {
            return false;
        }

        return self::htmlEsHuawei($html);
    }

    public static function htmlEsHuawei(string $html): bool
    {
        if (preg_match("/ProductName\s*=\s*'([^']+)'/", $html, $m)) {
            $name = strtoupper($m[1]);

            return (bool) preg_match('/EG|HG|HS|HN|ONT|HUAWEI/', $name);
        }

        return (bool) preg_match('/Huawei Home Gateway|GetRandCount\.asp|telecomadmin/i', $html);
    }

    /**
     * @param  list<array{user: string, password: string}>  $cuentas
     */
    public function login(array $cuentas): void
    {
        $this->get('/');
        $last = 'sin cuentas web';
        foreach ($cuentas as $cuenta) {
            $user = trim((string) ($cuenta['user'] ?? ''));
            $password = (string) ($cuenta['password'] ?? '');
            if ($user === '') {
                continue;
            }
            try {
                $this->intentarLogin($user, $password);

                return;
            } catch (Throwable $e) {
                $last = $e->getMessage();
            }
        }

        throw new RuntimeException('No se pudo entrar a la web de la ONU: '.$last);
    }

    /**
     * @return array{rx_power_dbm: float|null, tx_power_dbm: float|null, temperatura_c: float|null, raw: string}
     */
    public function leerOptica(): array
    {
        $html = $this->get('/html/amp/opticinfo/opticinfo.asp');
        $optic = self::parseOpticHtml($html);
        $optic['raw'] = $html;

        return $optic;
    }

    /**
     * @return list<string>
     */
    public function leerSsids(): array
    {
        $html = $this->get('/html/amp/wlanbasic/WlanBasic.asp?2G');

        return self::parseSsidsHtml($html);
    }

    /**
     * @return list<array{host: string, ip: string, mac: string, ssid: string, tiempo: string}>
     */
    public function listarAsociados(): array
    {
        $wifi = $this->get('/html/amp/wlanbasic/WlanBasic.asp?2G');
        $ssids = self::parseSsidPorInstancia($wifi);
        $sta = $this->post('/html/amp/wlaninfo/getassociateddeviceinfo.asp', []);

        return self::parseAsociadosHtml($sta, $ssids);
    }

    /**
     * @return list<array{domain: string, name: string, service: string, ipv4: bool, ipv6: bool, prefix_origin: string, address_origin: string, prefix_domain: ?string, address_domain: ?string}>
     */
    public function leerWans(): array
    {
        $wans = self::parseWanPppHtml($this->get('/html/bbsp/common/wan_list.asp'));
        $acquire = $this->get('/html/bbsp/common/wanaddressacquire.asp');

        return self::cruzarWansConAcquire($wans, $acquire);
    }

    /**
     * @return array{domain: string, name: string, service: string, ipv4: bool, ipv6: bool, prefix_origin: string, address_origin: string, prefix_domain: ?string, address_domain: ?string}
     */
    public function encenderIpv6(): array
    {
        $wans = $this->leerWans();
        $wan = self::wanInternet($wans);
        if ($wan === null) {
            throw new RuntimeException('No se encontró una WAN PPPoE Internet en la web.');
        }
        if (self::ipv6ConPd($wan)) {
            return $wan;
        }

        $html = $this->get('/html/bbsp/wan/wan.asp');
        $token = self::tokenDe($html);
        $payload = self::payloadIpv6($wan, $token);
        try {
            $this->http->post($payload['path'], [
                'form_params' => $payload['fields'],
                'timeout' => 20,
            ]);
        } catch (Throwable) {
        }

        $despues = $this->esperarIpv6ConPd() ?? $wan;
        if (! self::ipv6ConPd($despues)) {
            throw new RuntimeException('La web no dejó DHCPv6-PD en la WAN (prefijo='.($despues['prefix_origin'] ?? 'None').').');
        }

        return $despues;
    }

    /**
     * @param  list<array<string, mixed>>  $wans
     * @return array<string, mixed>|null
     */
    public static function wanInternet(array $wans): ?array
    {
        foreach ($wans as $wan) {
            if (str_contains((string) ($wan['service'] ?? ''), 'INTERNET')) {
                return $wan;
            }
        }

        return $wans[0] ?? null;
    }

    /**
     * @param  array<string, mixed>  $wan
     */
    public static function ipv6ConPd(array $wan): bool
    {
        if (! ($wan['ipv6'] ?? false)) {
            return false;
        }

        return self::esPrefixDelegation((string) ($wan['prefix_origin'] ?? ''));
    }

    public static function esPrefixDelegation(string $origin): bool
    {
        $origin = strtolower(trim($origin));

        return in_array($origin, ['prefixdelegation', 'dhcpv6-pd', 'autoconfigured', 'routeradvertisement'], true);
    }

    /**
     * @param  list<array<string, mixed>>  $wans
     * @return list<array{domain: string, name: string, service: string, ipv4: bool, ipv6: bool, prefix_origin: string, address_origin: string, prefix_domain: ?string, address_domain: ?string}>
     */
    public static function cruzarWansConAcquire(array $wans, string $acquireHtml): array
    {
        $prefixes = self::parsePrefixAcquire($acquireHtml);
        $addrs = self::parseAddressAcquire($acquireHtml);
        $out = [];
        foreach ($wans as $wan) {
            $p = self::matchAcquire($prefixes, (string) $wan['domain']);
            $a = self::matchAcquire($addrs, (string) $wan['domain']);
            $wan['prefix_origin'] = $p['origin'] ?? 'None';
            $wan['prefix_domain'] = $p['domain'] ?? null;
            $wan['address_origin'] = $a['origin'] ?? 'None';
            $wan['address_domain'] = $a['domain'] ?? null;
            $out[] = $wan;
        }

        return $out;
    }

    /**
     * @param  array{domain: string, prefix_domain?: ?string, address_domain?: ?string}  $wan
     * @return array{path: string, fields: array<string, string>}
     */
    public static function payloadIpv6(array $wan, string $token): array
    {
        $domain = $wan['domain'];
        $query = ['y='.rawurlencode($domain)];
        $mKey = 'm';
        $nKey = 'n';
        $addrDomain = $wan['address_domain'] ?? null;
        $prefDomain = $wan['prefix_domain'] ?? null;

        if ($addrDomain) {
            $query[] = 'm='.rawurlencode($addrDomain);
        } else {
            $mKey = self::CGI_ALTA.'m';
            $query[] = $mKey.'='.rawurlencode($domain.'.X_HW_IPv6.IPv6Address');
        }
        if ($prefDomain) {
            $query[] = 'n='.rawurlencode($prefDomain);
        } else {
            $nKey = self::CGI_ALTA.'n';
            $query[] = $nKey.'='.rawurlencode($domain.'.X_HW_IPv6.IPv6Prefix');
        }

        return [
            'path' => '/html/bbsp/wan/complex.cgi?'.implode('&', $query)
                .'&RequestFile=html/bbsp/wan/confirmwancfginfo.html',
            'fields' => [
                'y.X_HW_IPv4Enable' => '1',
                'y.X_HW_IPv6Enable' => '1',
                $mKey.'.Alias' => '',
                $mKey.'.Origin' => 'AutoConfigured',
                $mKey.'.IPAddress' => '',
                $mKey.'.ChildPrefixBits' => '',
                $mKey.'.AddrMaskLen' => '0',
                $mKey.'.DefaultGateway' => '',
                $nKey.'.Alias' => '',
                $nKey.'.Origin' => 'PrefixDelegation',
                $nKey.'.Prefix' => '',
                'x.X_HW_Token' => $token,
            ],
        ];
    }

    /**
     * @return list<array{domain: string, origin: string, prefix: string}>
     */
    public static function parsePrefixAcquire(string $html): array
    {
        $out = [];
        if (! preg_match_all('/new PrefixAcquireItem\(((?:[^()]|\([^()]*\))*)\)/', $html, $m)) {
            return $out;
        }
        foreach ($m[1] as $inner) {
            $args = self::argsJs($inner);
            if (count($args) < 3) {
                continue;
            }
            $out[] = [
                'domain' => self::decodeJs($args[0]),
                'origin' => self::normalizarPrefixOrigin(self::decodeJs($args[2])),
                'prefix' => self::decodeJs($args[3] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{domain: string, origin: string}>
     */
    public static function parseAddressAcquire(string $html): array
    {
        $out = [];
        if (! preg_match_all('/new IPAddressAcquire(?:PPP|IP)Item\(((?:[^()]|\([^()]*\))*)\)/', $html, $m)) {
            return $out;
        }
        foreach ($m[1] as $inner) {
            $args = self::argsJs($inner);
            if (count($args) < 3) {
                continue;
            }
            $out[] = [
                'domain' => self::decodeJs($args[0]),
                'origin' => self::normalizarAddressOrigin(self::decodeJs($args[2])),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{domain: string, origin: string}>  $items
     * @return array{domain: string, origin: string}|null
     */
    public static function matchAcquire(array $items, string $wanDomain): ?array
    {
        foreach ($items as $item) {
            if (str_contains($item['domain'], $wanDomain)) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function esperarIpv6ConPd(): ?array
    {
        $ultimo = null;
        for ($i = 0; $i < 6; $i++) {
            if ($i > 0) {
                sleep(1);
            }
            $ultimo = self::wanInternet($this->leerWans());
            if ($ultimo !== null && self::ipv6ConPd($ultimo)) {
                return $ultimo;
            }
        }

        return $ultimo;
    }

    public static function normalizarPrefixOrigin(string $origin): string
    {
        $origin = strtolower(trim($origin));

        return match ($origin) {
            'autoconfigured', 'routeradvertisement', 'prefixdelegation', 'dhcpv6-pd' => 'PrefixDelegation',
            'static' => 'Static',
            'none', '' => 'None',
            default => $origin === '' ? 'None' : $origin,
        };
    }

    public static function normalizarAddressOrigin(string $origin): string
    {
        $origin = strtolower(trim($origin));

        return match ($origin) {
            'autoconfigured' => 'AutoConfigured',
            'dhcpv6' => 'DHCPv6',
            'static' => 'Static',
            'none', '' => 'None',
            default => $origin === '' ? 'None' : $origin,
        };
    }

    /**
     * @return list<array{domain: string, name: string, service: string, ipv4: bool, ipv6: bool}>
     */
    public static function parseWanPppHtml(string $html): array
    {
        $out = [];
        if (! preg_match_all('/new WanPPP\(((?:[^()]|\([^()]*\))*)\)/', $html, $m)) {
            return $out;
        }
        foreach ($m[1] as $inner) {
            $args = self::argsJs($inner);
            if (count($args) < 32) {
                continue;
            }
            $domain = self::decodeJs($args[0]);
            if (! str_contains($domain, 'WANPPPConnection')) {
                continue;
            }
            $out[] = [
                'domain' => $domain,
                'name' => self::decodeJs($args[6]),
                'service' => strtoupper(self::decodeJs($args[25])),
                'ipv4' => self::decodeJs($args[30]) === '1',
                'ipv6' => self::decodeJs($args[31]) === '1',
            ];
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function argsJs(string $inner): array
    {
        if (! preg_match_all('/"(?:\\\\.|[^"\\\\])*"/', $inner, $m)) {
            return [];
        }

        return array_map(static fn (string $q): string => substr($q, 1, -1), $m[0]);
    }

    public function cambiarWifi(string $ssid, string $password): array
    {
        $html = $this->get('/html/amp/wlanbasic/WlanBasic.asp?2G');
        $token = self::tokenDe($html);
        $instancias = self::parseSsidPorInstancia($html);
        if ($instancias === []) {
            $instancias = [1 => $ssid, 5 => $ssid.'_5G'];
        }

        $i = 0;
        foreach ($instancias as $inst => $actual) {
            $nombre = $i === 0 ? $ssid : $ssid.'_5G';
            if (count($instancias) === 1) {
                $nombre = $ssid;
            }
            $domain = 'InternetGatewayDevice.LANDevice.1.WLANConfiguration.'.$inst;
            $this->post(
                '/html/amp/wlanbasic/set.cgi?y='.rawurlencode($domain)
                .'&k='.rawurlencode($domain.'.PreSharedKey.1')
                .'&RequestFile=html/amp/wlanbasic/WlanBasic.asp',
                [
                    'y.SSID' => $nombre,
                    'k.PreSharedKey' => $password,
                    'x.X_HW_Token' => $token,
                ]
            );
            $i++;
        }

        return $this->leerSsids();
    }

    public function encenderSshWan(): void
    {
        $html = $this->get('/html/bbsp/portacl/newacl.asp');
        $token = self::tokenDe($html);
        $campos = [
            'x.SSHWanEnable' => '1',
            'x.SSHLanEnable' => '1',
            'x.X_HW_Token' => $token,
        ];
        foreach ([
            '/html/bbsp/portacl/set.cgi?x='.rawurlencode('InternetGatewayDevice.X_HW_Security.AclServices')
            .'&RequestFile=html/bbsp/portacl/newacl.asp',
            '/html/ssmp/common/setajax.cgi?x='.rawurlencode('InternetGatewayDevice.X_HW_Security.AclServices')
            .'&RequestFile=html/bbsp/portacl/newacl.asp',
        ] as $path) {
            try {
                $this->http->post($path, [
                    'form_params' => $campos,
                    'timeout' => 8,
                ]);
            } catch (Throwable) {
            }
        }
    }

    public function reiniciar(): void
    {
        $html = $this->get('/html/ssmp/cfgfile/cfgfile.asp');
        $token = self::tokenDe($html);
        try {
            $this->http->post(
                '/html/ssmp/cfgfile/set.cgi?x=InternetGatewayDevice.X_HW_DEBUG.SSP.DBSave&y=InternetGatewayDevice.X_HW_DEBUG.SMP.DM.ResetBoard&RequestFile=html/ssmp/cfgfile/cfgfile.asp',
                [
                    'form_params' => ['x.X_HW_Token' => $token],
                    'timeout' => 6,
                ]
            );
        } catch (Throwable) {
        }
    }

    /**
     * @return array{rx_power_dbm: float|null, tx_power_dbm: float|null, temperatura_c: float|null}
     */
    public static function parseOpticHtml(string $html): array
    {
        $vacio = ['rx_power_dbm' => null, 'tx_power_dbm' => null, 'temperatura_c' => null];
        if (! preg_match('/new stOpticInfo\("([^"]*)","([^"]*)","([^"]*)","([^"]*)","([^"]*)"/', $html, $m)) {
            return $vacio;
        }

        return [
            'tx_power_dbm' => self::aFloat(self::decodeJs($m[2])),
            'rx_power_dbm' => self::aFloat(self::decodeJs($m[3])),
            'temperatura_c' => self::aFloat(self::decodeJs($m[5])),
        ];
    }

    /**
     * @return list<string>
     */
    public static function parseSsidsHtml(string $html): array
    {
        return array_values(self::parseSsidPorInstancia($html));
    }

    /**
     * @return array<int, string>
     */
    public static function parseSsidPorInstancia(string $html): array
    {
        $out = [];
        if (! preg_match_all('/new stWlanWifi\("([^"]+)","[^"]+","[^"]+","([^"]+)"/', $html, $m, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($m as $row) {
            if (! preg_match('/WLANConfiguration\.(\d+)/', self::decodeJs($row[1]), $inst)) {
                continue;
            }
            $ssid = trim(self::decodeJs($row[2]));
            if ($ssid !== '') {
                $out[(int) $inst[1]] = $ssid;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $ssids
     * @return list<array{host: string, ip: string, mac: string, ssid: string, tiempo: string}>
     */
    public static function parseAsociadosHtml(string $raw, array $ssids = []): array
    {
        $rows = [];
        if (! preg_match_all('/new stAssociatedDevice\("([^"]+)","([^"]+)","([^"]+)"/', $raw, $m, PREG_SET_ORDER)) {
            return $rows;
        }
        foreach ($m as $row) {
            $domain = self::decodeJs($row[1]);
            $mac = HuaweiOnuService::normalizarMac(self::decodeJs($row[2]));
            if ($mac === '') {
                continue;
            }
            $inst = 0;
            if (preg_match('/WLANConfiguration\.(\d+)/', $domain, $im)) {
                $inst = (int) $im[1];
            }
            $rows[] = [
                'host' => '',
                'ip' => '',
                'mac' => $mac,
                'ssid' => $ssids[$inst] ?? '',
                'tiempo' => self::decodeJs($row[3]),
            ];
        }

        return $rows;
    }

    public static function decodeJs(string $value): string
    {
        return preg_replace_callback('/\\\\x([0-9A-Fa-f]{2})/', function (array $m) {
            return chr(hexdec($m[1]));
        }, $value) ?? $value;
    }

    public static function tokenDe(string $html): string
    {
        if (preg_match('/id="hwonttoken"[^>]*value="([^"]+)"/', $html, $m)) {
            return $m[1];
        }

        throw new RuntimeException('La web de la ONU no entregó token.');
    }

    protected function intentarLogin(string $user, string $password): void
    {
        $rand = $this->randToken();
        $html = $this->post('/login.cgi', [
            'UserName' => $user,
            'PassWord' => base64_encode($password),
            'Language' => 'english',
            'x.X_HW_Token' => $rand,
        ]);
        if (! str_contains($html, 'index.asp') && ! str_contains($html, 'sid=')) {
            throw new RuntimeException('usuario o clave web rechazados ('.$user.')');
        }
        $index = $this->get('/index.asp');
        if (strlen($index) < 2000 || str_contains($index, 'Waiting...')) {
            throw new RuntimeException('sesión web no quedó abierta ('.$user.')');
        }
    }

    protected function randToken(): string
    {
        $raw = $this->post('/asp/GetRandCount.asp', []);

        return trim(str_replace("\xEF\xBB\xBF", '', $raw));
    }

    protected function get(string $path): string
    {
        $res = $this->http->get($path);

        return (string) $res->getBody();
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function post(string $path, array $fields): string
    {
        $res = $this->http->post($path, [
            'form_params' => $fields,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        return (string) $res->getBody();
    }

    protected static function aFloat(string $value): ?float
    {
        $value = trim(str_replace(',', '.', $value));
        if ($value === '' || $value === '--' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
