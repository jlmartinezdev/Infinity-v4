<?php

namespace App\Services\GenieAcs;

use App\Models\Servicio;
use App\Support\CpeInventario;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenieAcsService
{
    public function configured(): bool
    {
        return (bool) config('genieacs.enabled')
            && filled(config('genieacs.nbi_url'));
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(Servicio $servicio): array
    {
        $hallado = $this->buscarDispositivo($servicio);
        if (! ($hallado['ok'] ?? false)) {
            return $hallado;
        }

        /** @var array<string, mixed> $device */
        $device = $hallado['device'];
        $lastInform = $this->fechaIso($device['_lastInform'] ?? null);
        $grace = (int) config('genieacs.online_grace_seconds', 900);
        $online = false;
        if ($lastInform) {
            try {
                $online = \Carbon\Carbon::parse($lastInform)->gte(now()->subSeconds($grace));
            } catch (Throwable) {
                $online = false;
            }
        }

        $ssids = CwmpValues::ssids($device);
        $wanIp = CwmpValues::wanIp($device);
        $crOk = CwmpValues::connectionRequestOk($device);
        $pppStatus = CwmpValues::get(
            $device,
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.ConnectionStatus',
            'Device.PPP.Interface.1.Status',
        );

        $id = $device['_deviceId'] ?? [];
        $serial = is_array($id)
            ? ($id['_SerialNumber'] ?? null)
            : null;
        $serial = $serial
            ?? CwmpValues::get($device, 'InternetGatewayDevice.DeviceInfo.SerialNumber', 'Device.DeviceInfo.SerialNumber');
        $manufacturer = is_array($id) ? ($id['_Manufacturer'] ?? null) : null;
        $manufacturer = $manufacturer
            ?: CwmpValues::get($device, 'Device.DeviceInfo.Manufacturer', 'InternetGatewayDevice.DeviceInfo.Manufacturer');

        $aviso = null;
        if (! $crOk) {
            $aviso = 'El CPE anuncia Connection Request en puerto 0: reboot/refresh quedan en cola hasta el próximo Inform.';
        }

        return [
            'success' => true,
            'message' => 'CPE encontrado en GenieACS.',
            'via' => $hallado['via'] ?? 'serial',
            'device_id' => $device['_id'] ?? null,
            'online' => $online,
            'last_inform' => $lastInform,
            'manufacturer' => $manufacturer,
            'product_class' => is_array($id) ? ($id['_ProductClass'] ?? null) : null,
            'serial' => $serial,
            'model' => CwmpValues::get($device, 'InternetGatewayDevice.DeviceInfo.ModelName', 'Device.DeviceInfo.ModelName', 'Device.DeviceInfo.Description'),
            'software_version' => CwmpValues::get($device, 'InternetGatewayDevice.DeviceInfo.SoftwareVersion', 'Device.DeviceInfo.SoftwareVersion'),
            'hardware_version' => CwmpValues::get($device, 'InternetGatewayDevice.DeviceInfo.HardwareVersion', 'Device.DeviceInfo.HardwareVersion'),
            'wan_ip' => $wanIp,
            'wan_mac' => CwmpValues::wanMac($device),
            'lan_ip' => CwmpValues::lanIp($device),
            'connection_request_ok' => $crOk,
            'aviso' => $aviso,
            'ppp_status' => $pppStatus !== null ? (string) $pppStatus : null,
            'ssid' => $ssids[0] ?? null,
            'ssids' => $ssids,
            'wifi' => array_map(static fn (array $n) => [
                'id' => $n['id'],
                'ssid' => $n['ssid'],
                'enabled' => $n['enabled'],
                'band' => $n['band'],
                'security' => $n['security'],
            ], CwmpValues::wifiNetworks($device)),
            'puede_admin_password' => CwmpValues::adminPasswordPath($device) !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function hosts(Servicio $servicio): array
    {
        $hallado = $this->buscarDispositivo($servicio);
        if (! ($hallado['ok'] ?? false)) {
            return $hallado;
        }

        /** @var array<string, mixed> $device */
        $device = $hallado['device'];
        $hosts = CwmpValues::hosts($device);

        return [
            'success' => true,
            'message' => count($hosts) === 1
                ? '1 dispositivo (LAN o WiFi)'
                : count($hosts).' dispositivos (LAN / WiFi)',
            'device_id' => $device['_id'] ?? null,
            'hosts' => $hosts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reboot(Servicio $servicio): array
    {
        return $this->encolarTarea($servicio, ['name' => 'reboot'], 'Reboot encolado en el ACS.');
    }

    /**
     * Cambia clave WiFi o la del panel del router (SetParameterValues).
     *
     * @return array<string, mixed>
     */
    public function setPassword(Servicio $servicio, string $tipo, string $password, ?string $wifiId = null): array
    {
        $hallado = $this->buscarDispositivo($servicio);
        if (! ($hallado['ok'] ?? false)) {
            return $hallado;
        }

        /** @var array<string, mixed> $device */
        $device = $hallado['device'];
        $id = (string) ($device['_id'] ?? '');
        $crOk = CwmpValues::connectionRequestOk($device);

        if ($tipo === 'admin') {
            $path = CwmpValues::adminPasswordPath($device);
            if ($path === null) {
                return [
                    'success' => false,
                    'message' => 'Este CPE no expone un parámetro de clave de administrador por TR-069.',
                ];
            }
            $valores = [[$path, $password, 'xsd:string']];
            $okMsg = 'Clave del router encolada en el ACS.';
        } else {
            $redes = CwmpValues::wifiNetworks($device);
            if ($wifiId && $wifiId !== 'all') {
                $redes = array_values(array_filter($redes, fn (array $n) => ($n['id'] ?? '') === $wifiId));
            } else {
                $redes = array_values(array_filter($redes, fn (array $n) => (bool) ($n['enabled'] ?? false)));
            }
            if ($redes === []) {
                return [
                    'success' => false,
                    'message' => $wifiId && $wifiId !== 'all'
                        ? 'No se encontró esa red WiFi en el CPE.'
                        : 'No hay SSIDs activos para cambiar la clave.',
                ];
            }
            $valores = [];
            foreach ($redes as $net) {
                $valores[] = [$net['passphrase_path'], $password, 'xsd:string'];
                $sec = strtolower((string) ($net['security'] ?? ''));
                if (($net['mode_path'] ?? null) && ($sec === '' || $sec === 'none')) {
                    $valores[] = [$net['mode_path'], 'WPA2-Personal', 'xsd:string'];
                }
            }
            $nombres = array_values(array_unique(array_map(fn (array $n) => (string) $n['ssid'], $redes)));
            $okMsg = count($nombres) === 1
                ? 'Clave WiFi encolada para «'.$nombres[0].'».'
                : 'Clave WiFi encolada para '.count($nombres).' SSIDs.';
        }

        $res = $this->postTarea($id, [
            'name' => 'setParameterValues',
            'parameterValues' => $valores,
        ]);
        if (! $res['ok']) {
            return ['success' => false, 'message' => $res['message']];
        }

        Log::info('[GenieACS] SetParameterValues password', [
            'servicio_id' => $servicio->servicio_id,
            'device_id' => $id,
            'tipo' => $tipo,
            'wifi_id' => $wifiId,
            'paths' => array_map(static fn (array $row) => $row[0], $valores),
        ]);

        if (! $crOk) {
            $okMsg .= ' Connection Request no disponible: se aplica en el próximo Inform.';
        }

        return [
            'success' => true,
            'message' => $okMsg,
            'device_id' => $id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(Servicio $servicio): array
    {
        $hallado = $this->buscarDispositivo($servicio);
        if (! ($hallado['ok'] ?? false)) {
            return $hallado;
        }

        /** @var array<string, mixed> $device */
        $device = $hallado['device'];
        $id = (string) ($device['_id'] ?? '');
        $tareas = [
            ['name' => 'refreshObject', 'objectName' => 'InternetGatewayDevice'],
            ['name' => 'refreshObject', 'objectName' => 'Device'],
        ];
        $ok = 0;
        $ultimoError = null;
        foreach ($tareas as $tarea) {
            $res = $this->postTarea($id, $tarea);
            if ($res['ok']) {
                $ok++;
            } else {
                $ultimoError = $res['message'];
            }
        }

        if ($ok === 0) {
            return [
                'success' => false,
                'message' => $ultimoError ?: 'No se pudo encolar el refresh.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Refresh encolado. Los parámetros se actualizan en el próximo Inform o Connection Request.',
            'device_id' => $id,
        ];
    }

    /**
     * @return array{ok: bool, device?: array<string, mixed>, via?: string, success?: bool, message?: string}
     */
    public function buscarDispositivo(Servicio $servicio): array
    {
        if (CpeInventario::usaSshCpe($servicio)) {
            return [
                'ok' => false,
                'success' => false,
                'message' => 'Este servicio está marcado con acceso SSH. Los comandos ACS no aplican. Cambiá el acceso a ACS en el servicio si el CPE es TR-069.',
            ];
        }

        if (! $this->configured()) {
            return [
                'ok' => false,
                'success' => false,
                'message' => 'GenieACS no está configurado (GENIEACS_ENABLED y GENIEACS_NBI_URL).',
            ];
        }

        $serial = trim((string) ($servicio->tr069_serial ?? ''));
        $productClass = trim((string) ($servicio->tr069_product_class ?? ''));
        $mac = $this->normalizarMac($servicio->mac_address);

        if ($serial === '' && $mac === null) {
            return [
                'ok' => false,
                'success' => false,
                'message' => 'Cargá el serial TR-069 o la MAC del servicio para buscar el CPE en el ACS.',
            ];
        }

        if ($serial !== '') {
            $serialVariants = array_values(array_unique([
                $serial,
                strtolower($serial),
                strtoupper($serial),
            ]));
            $queries = [];
            foreach ($serialVariants as $s) {
                $queries[] = ['_id' => $s];
                $queries[] = ['_deviceId._SerialNumber' => $s];
                $queries[] = ['InternetGatewayDevice.DeviceInfo.SerialNumber' => $s];
                $queries[] = ['Device.DeviceInfo.SerialNumber' => $s];
            }
            if ($productClass !== '') {
                array_unshift($queries, [
                    '_deviceId._SerialNumber' => $serial,
                    '_deviceId._ProductClass' => $productClass,
                ]);
            }
            try {
                foreach ($queries as $query) {
                    $dev = $this->queryUno($query);
                    if ($dev) {
                        return ['ok' => true, 'device' => $dev, 'via' => 'serial'];
                    }
                }
            } catch (Throwable $e) {
                return $this->errorContacto($e);
            }
        }

        if ($mac !== null) {
            try {
                foreach ($this->consultasMac($mac) as $query) {
                    $dev = $this->queryUno($query);
                    if ($dev) {
                        return ['ok' => true, 'device' => $dev, 'via' => 'mac'];
                    }
                }
            } catch (Throwable $e) {
                return $this->errorContacto($e);
            }
        }

        return [
            'ok' => false,
            'success' => false,
            'message' => 'No hay un CPE en GenieACS con ese serial o MAC. ¿El equipo ya hizo Inform al ACS?',
        ];
    }

    /**
     * @param  array<string, mixed>  $tarea
     * @return array<string, mixed>
     */
    private function encolarTarea(Servicio $servicio, array $tarea, string $okMsg): array
    {
        $hallado = $this->buscarDispositivo($servicio);
        if (! ($hallado['ok'] ?? false)) {
            return $hallado;
        }
        $id = (string) (($hallado['device']['_id'] ?? ''));
        $res = $this->postTarea($id, $tarea);
        if (! $res['ok']) {
            return ['success' => false, 'message' => $res['message']];
        }

        return [
            'success' => true,
            'message' => $okMsg,
            'device_id' => $id,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function queryUno(array $query): ?array
    {
        try {
            $response = $this->http()->get(config('genieacs.nbi_url').'/devices/', [
                'query' => json_encode($query, JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $e) {
            Log::warning('[GenieACS] Error NBI', ['error' => $e->getMessage()]);
            throw $e;
        }

        if (! $response->successful()) {
            Log::warning('[GenieACS] NBI HTTP '.$response->status(), [
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException('GenieACS respondió HTTP '.$response->status());
        }

        $lista = $response->json();
        if (! is_array($lista) || $lista === []) {
            return null;
        }
        $primero = $lista[0] ?? null;

        return is_array($primero) ? $primero : null;
    }

    /**
     * @param  array<string, mixed>  $tarea
     * @return array{ok: bool, message: string}
     */
    private function postTarea(string $deviceId, array $tarea): array
    {
        if ($deviceId === '') {
            return ['ok' => false, 'message' => 'El ACS no devolvió _id del dispositivo.'];
        }

        $url = config('genieacs.nbi_url').'/devices/'.rawurlencode($deviceId).'/tasks';

        try {
            $response = $this->http()
                ->withQueryParameters(['timeout' => 3000, 'connection_request' => true])
                ->post($url, $tarea);
        } catch (Throwable $e) {
            Log::warning('[GenieACS] Error al encolar tarea', ['error' => $e->getMessage(), 'task' => $tarea['name'] ?? null]);

            return ['ok' => false, 'message' => 'No se pudo contactar GenieACS: '.$e->getMessage()];
        }

        if ($response->successful() || $response->status() === 202) {
            return ['ok' => true, 'message' => 'ok'];
        }

        $msg = $response->json('message') ?: ('HTTP '.$response->status());

        return ['ok' => false, 'message' => 'GenieACS rechazó la tarea: '.$msg];
    }

    /**
     * @return list<array<string, string>>
     */
    private function consultasMac(string $mac): array
    {
        $plain = str_replace(':', '', $mac);
        $lower = strtolower($mac);
        $paths = [
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1.MACAddress',
            'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.MACAddress',
            'InternetGatewayDevice.LANDevice.1.LANEthernetInterfaceConfig.1.MACAddress',
            'InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.BSSID',
            'Device.Ethernet.Interface.1.MACAddress',
            'Device.Ethernet.Link.1.MACAddress',
            'Device.Ethernet.Link.2.MACAddress',
            'Device.WiFi.SSID.1.BSSID',
            'Device.WiFi.SSID.1.MACAddress',
            'Device.WiFi.SSID.6.BSSID',
            'Device.WiFi.SSID.6.MACAddress',
            'Device.DeviceInfo.SerialNumber',
        ];
        $or = [];
        foreach ($paths as $path) {
            $or[] = [$path => $mac];
            $or[] = [$path => $lower];
            $or[] = [$path => $plain];
            $or[] = [$path => strtolower($plain)];
        }
        $or[] = ['_deviceId._SerialNumber' => strtolower($plain)];
        $or[] = ['_deviceId._SerialNumber' => $plain];

        return [['$or' => $or]];
    }

    private function http(): PendingRequest
    {
        $req = Http::timeout((int) config('genieacs.timeout', 20))
            ->connectTimeout(8)
            ->acceptJson()
            ->asJson();
        $user = trim((string) config('genieacs.nbi_user'));
        if ($user !== '') {
            $req = $req->withBasicAuth($user, (string) config('genieacs.nbi_password'));
        }

        return $req;
    }

    public function normalizarMac(?string $mac): ?string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', (string) $mac));
        if (strlen($hex) !== 12) {
            return null;
        }

        return implode(':', str_split($hex, 2));
    }

    private function fechaIso(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format(\DateTimeInterface::ATOM);
        }
        $s = (string) $valor;
        if (is_numeric($s) && strlen($s) >= 12) {
            try {
                return \Carbon\Carbon::createFromTimestampMs((int) $s)->toIso8601String();
            } catch (Throwable) {
                return $s;
            }
        }

        return $s;
    }

    /**
     * @return array{ok: false, success: false, message: string}
     */
    private function errorContacto(Throwable $e): array
    {
        return [
            'ok' => false,
            'success' => false,
            'message' => 'No se pudo contactar GenieACS: '.$e->getMessage(),
        ];
    }
}
