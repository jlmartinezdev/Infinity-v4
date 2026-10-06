<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\Huawei\HuaweiOnuWeb;
use App\Services\Ubnt\UbntAntenaService;
use App\Support\CpeInventario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dispositivos LAN del CPE del cliente portal.
 * FTTH/ACS → GenieACS hosts · Huawei ONU (SSH/web) · Wireless → SSH Ubiquiti.
 * Soft-fail: lista vacía si no aplica / falla.
 */
class PortalCpeDhcpService
{
    public const SOURCE_UBNT = 'ubnt_dhcpd_leases';

    public const SOURCE_TR069 = 'tr069_acs';

    public const SOURCE_HUAWEI = 'huawei_onu';

    public function __construct(
        private readonly UbntAntenaService $ubnt,
        private readonly GenieAcsService $acs,
        private readonly HuaweiOnuService $huawei,
    ) {}

    /**
     * @return array{
     *   source: string|null,
     *   collected_at: string|null,
     *   gateway_ip: string|null,
     *   servicio_id: int|null,
     *   clients: list<array{ip: string, mac: string, hostname: string|null, online: bool|null, lease_expires_at: string|null}>
     * }
     */
    public function forCliente(Cliente $cliente, ?int $servicioId = null): array
    {
        $empty = $this->payloadVacio();

        try {
            $servicio = $this->resolverServicio($cliente, $servicioId);
            if (! $servicio) {
                return $empty;
            }

            $gatewayIp = $this->gatewayIp($servicio);
            $emptyConServicio = array_merge($empty, [
                'gateway_ip' => $gatewayIp,
                'servicio_id' => (int) $servicio->servicio_id,
            ]);

            @set_time_limit(45);

            if (CpeInventario::usaAcs($servicio) && $this->acs->configured()) {
                $tr069 = $this->desdeTr069($servicio, $gatewayIp);
                if ($tr069 !== null) {
                    return $tr069;
                }
            }

            if ($this->puedeHuawei($servicio)) {
                $huawei = $this->desdeHuawei($servicio, $gatewayIp);
                if ($huawei !== null) {
                    return $huawei;
                }
            }

            if ($this->servicioEsFibra($servicio)) {
                return $emptyConServicio;
            }

            if ($gatewayIp === null) {
                return $emptyConServicio;
            }

            $result = $this->ubnt->consultarDhcpLeases($gatewayIp);
            if (! ($result['success'] ?? false)) {
                Log::info('[Portal CPE DHCP] soft-fail ubnt', [
                    'cliente_id' => $cliente->cliente_id,
                    'servicio_id' => $servicio->servicio_id,
                    'gateway_ip' => $gatewayIp,
                    'message' => $result['message'] ?? null,
                ]);

                return $emptyConServicio;
            }

            $clients = self::mapToClients($result['leases'] ?? []);

            return [
                'source' => self::SOURCE_UBNT,
                'collected_at' => now()->utc()->toIso8601String(),
                'gateway_ip' => $gatewayIp,
                'servicio_id' => (int) $servicio->servicio_id,
                'clients' => $clients,
            ];
        } catch (Throwable $e) {
            Log::warning('[Portal CPE DHCP] exception', [
                'cliente_id' => $cliente->cliente_id,
                'error' => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows  leases Ubnt, hosts TR-069 o dispositivos Huawei
     * @return list<array{ip: string, mac: string, hostname: string|null, online: bool|null, lease_expires_at: string|null}>
     */
    public static function mapToClients(array $rows): array
    {
        $now = time();
        $out = [];

        foreach ($rows as $row) {
            $ip = trim((string) ($row['ip'] ?? ''));
            $mac = self::normalizarMac((string) ($row['mac'] ?? ''));
            if ($ip === '' || $mac === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $expiresAt = isset($row['expires_at']) ? (int) $row['expires_at'] : 0;
            $hostname = filled($row['hostname'] ?? null)
                ? (string) $row['hostname']
                : (filled($row['host'] ?? null) ? (string) $row['host'] : null);

            $online = null;
            $leaseExpiresIso = null;
            if ($expiresAt > 0) {
                $online = $expiresAt > $now;
                $leaseExpiresIso = Carbon::createFromTimestamp($expiresAt)->utc()->toIso8601String();
            } elseif (array_key_exists('online', $row)) {
                $online = $row['online'] === null ? null : (bool) $row['online'];
            }

            $out[] = [
                'ip' => $ip,
                'mac' => $mac,
                'hostname' => $hostname !== '' ? $hostname : null,
                'online' => $online,
                'lease_expires_at' => $leaseExpiresIso,
            ];
        }

        usort($out, function (array $a, array $b): int {
            $ha = $a['hostname'] !== null ? 0 : 1;
            $hb = $b['hostname'] !== null ? 0 : 1;
            if ($ha !== $hb) {
                return $ha <=> $hb;
            }
            $oa = $a['online'] === true ? 0 : 1;
            $ob = $b['online'] === true ? 0 : 1;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcmp($a['ip'], $b['ip']);
        });

        return array_values($out);
    }

    public static function normalizarMac(string $mac): string
    {
        $mac = strtolower(trim($mac));
        $hex = preg_replace('/[^0-9a-f]/', '', $mac) ?? '';
        if (strlen($hex) !== 12) {
            return $mac;
        }

        return implode(':', str_split($hex, 2));
    }

    /**
     * @return array{
     *   source: string,
     *   collected_at: string,
     *   gateway_ip: string|null,
     *   servicio_id: int,
     *   clients: list<array{ip: string, mac: string, hostname: string|null, online: bool|null, lease_expires_at: string|null}>
     * }|null
     */
    private function desdeHuawei(Servicio $servicio, ?string $gatewayIp): ?array
    {
        $result = $this->huawei->listarConectados($servicio);
        if (! ($result['success'] ?? false)) {
            Log::info('[Portal CPE DHCP] soft-fail huawei', [
                'servicio_id' => $servicio->servicio_id,
                'message' => $result['message'] ?? null,
            ]);

            return null;
        }

        $rows = [];
        foreach ($result['dispositivos'] ?? [] as $dev) {
            $rows[] = [
                'ip' => $dev['ip'] ?? '',
                'mac' => $dev['mac'] ?? '',
                'hostname' => $dev['host'] ?? null,
                'online' => true,
            ];
        }

        return [
            'source' => self::SOURCE_HUAWEI,
            'collected_at' => now()->utc()->toIso8601String(),
            'gateway_ip' => $gatewayIp,
            'servicio_id' => (int) $servicio->servicio_id,
            'clients' => self::mapToClients($rows),
        ];
    }

    /**
     * @return array{
     *   source: string,
     *   collected_at: string,
     *   gateway_ip: string|null,
     *   servicio_id: int,
     *   clients: list<array{ip: string, mac: string, hostname: string|null, online: bool|null, lease_expires_at: string|null}>
     * }|null
     */
    private function desdeTr069(Servicio $servicio, ?string $gatewayIp): ?array
    {
        $result = $this->acs->hosts($servicio);
        if (! ($result['success'] ?? false)) {
            Log::info('[Portal CPE DHCP] soft-fail tr069', [
                'servicio_id' => $servicio->servicio_id,
                'message' => $result['message'] ?? null,
            ]);

            return null;
        }

        $clients = self::mapToClients($result['hosts'] ?? []);
        if ($clients === []) {
            return null;
        }

        return [
            'source' => self::SOURCE_TR069,
            'collected_at' => now()->utc()->toIso8601String(),
            'gateway_ip' => $gatewayIp,
            'servicio_id' => (int) $servicio->servicio_id,
            'clients' => $clients,
        ];
    }

    private function puedeHuawei(Servicio $servicio): bool
    {
        $ip = $this->gatewayIp($servicio);
        if ($ip === null) {
            return false;
        }

        if (CpeInventario::esHuaweiOnu($servicio) || CpeInventario::usaSshCpe($servicio)) {
            return true;
        }

        if (! $this->servicioEsFibra($servicio)) {
            return false;
        }

        return HuaweiOnuWeb::detectarCacheado(
            $ip,
            (int) config('huawei.web_port', 80),
            (int) config('huawei.web_detect_timeout', 3)
        );
    }

    /**
     * @return array{
     *   source: null,
     *   collected_at: null,
     *   gateway_ip: null,
     *   servicio_id: null,
     *   clients: list<empty>
     * }
     */
    private function payloadVacio(): array
    {
        return [
            'source' => null,
            'collected_at' => null,
            'gateway_ip' => null,
            'servicio_id' => null,
            'clients' => [],
        ];
    }

    private function resolverServicio(Cliente $cliente, ?int $servicioId): ?Servicio
    {
        $base = Servicio::query()
            ->with(['pool.router.nodo', 'plan', 'cajaNapPuertoActivo'])
            ->where('cliente_id', $cliente->cliente_id)
            ->where('estado', '!=', Servicio::ESTADO_CANCELADO);

        if ($servicioId !== null && $servicioId > 0) {
            return (clone $base)->where('servicio_id', $servicioId)->first();
        }

        $servicios = $base
            ->orderByRaw("CASE estado WHEN 'A' THEN 0 WHEN 'S' THEN 1 WHEN 'C' THEN 2 ELSE 3 END")
            ->orderBy('servicio_id')
            ->get();

        $acs = $servicios->first(fn (Servicio $s) => CpeInventario::usaAcs($s));
        if ($acs) {
            return $acs;
        }

        $huawei = $servicios->first(fn (Servicio $s) => $this->puedeHuawei($s));
        if ($huawei) {
            return $huawei;
        }

        $antena = $servicios->first(function (Servicio $s) {
            return ! $this->servicioEsFibra($s)
                && $this->gatewayIp($s) !== null;
        });

        if ($antena) {
            return $antena;
        }

        return $servicios->first(fn (Servicio $s) => $this->gatewayIp($s) !== null);
    }

    private function gatewayIp(Servicio $servicio): ?string
    {
        $ip = trim((string) ($servicio->ip ?? ''));
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
    }

    private function servicioEsFibra(Servicio $servicio): bool
    {
        if ($servicio->cajaNapPuertoActivo) {
            return true;
        }
        if ($servicio->pool?->olt_id) {
            return true;
        }
        if ($servicio->pool?->router?->nodo?->manejaGpon()) {
            return true;
        }
        $planNombre = strtolower((string) ($servicio->plan?->nombre ?? ''));

        return str_contains($planNombre, 'fibra')
            || str_contains($planNombre, 'gpon')
            || str_contains($planNombre, 'ftth');
    }
}
