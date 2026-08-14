<?php

namespace App\Services\Portal;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Services\Ubnt\UbntAntenaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DHCP clients del CPE (LAN) del cliente portal — vía SSH Ubiquiti.
 * Soft-fail: lista vacía si no aplica / sin IP / SSH falla.
 */
class PortalCpeDhcpService
{
    public function __construct(
        private readonly UbntAntenaService $ubnt,
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

            if ($this->servicioEsFibra($servicio)) {
                return $empty;
            }

            $gatewayIp = trim((string) ($servicio->ip ?? ''));
            if ($gatewayIp === '' || filter_var($gatewayIp, FILTER_VALIDATE_IP) === false) {
                return $empty;
            }

            @set_time_limit(45);

            $result = $this->ubnt->consultarDhcpLeases($gatewayIp);
            if (! ($result['success'] ?? false)) {
                Log::info('[Portal CPE DHCP] soft-fail', [
                    'cliente_id' => $cliente->cliente_id,
                    'servicio_id' => $servicio->servicio_id,
                    'gateway_ip' => $gatewayIp,
                    'message' => $result['message'] ?? null,
                ]);

                return array_merge($empty, [
                    'gateway_ip' => $gatewayIp,
                    'servicio_id' => (int) $servicio->servicio_id,
                ]);
            }

            $clients = $this->mapClients($result['leases'] ?? []);

            return [
                'source' => 'ubnt_dhcpd_leases',
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

        // Preferir wireless/antena con IP válida.
        $antena = $servicios->first(function (Servicio $s) {
            return ! $this->servicioEsFibra($s)
                && filled(trim((string) ($s->ip ?? '')))
                && filter_var(trim((string) $s->ip), FILTER_VALIDATE_IP);
        });

        if ($antena) {
            return $antena;
        }

        return $servicios->first(function (Servicio $s) {
            return filled(trim((string) ($s->ip ?? '')))
                && filter_var(trim((string) $s->ip), FILTER_VALIDATE_IP);
        });
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

    /**
     * @param  list<array<string, mixed>>  $leases
     * @return list<array{ip: string, mac: string, hostname: string|null, online: bool|null, lease_expires_at: string|null}>
     */
    private function mapClients(array $leases): array
    {
        $now = time();
        $out = [];

        foreach ($leases as $lease) {
            $ip = trim((string) ($lease['ip'] ?? ''));
            $mac = $this->normalizarMac((string) ($lease['mac'] ?? ''));
            if ($ip === '' || $mac === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
                continue;
            }

            $expiresAt = isset($lease['expires_at']) ? (int) $lease['expires_at'] : 0;
            $hostname = filled($lease['hostname'] ?? null) ? (string) $lease['hostname'] : null;

            $online = null;
            $leaseExpiresIso = null;
            if ($expiresAt > 0) {
                $online = $expiresAt > $now;
                $leaseExpiresIso = Carbon::createFromTimestamp($expiresAt)->utc()->toIso8601String();
            }

            $out[] = [
                'ip' => $ip,
                'mac' => $mac,
                'hostname' => $hostname,
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

    private function normalizarMac(string $mac): string
    {
        $mac = strtolower(trim($mac));
        $hex = preg_replace('/[^0-9a-f]/', '', $mac) ?? '';
        if (strlen($hex) !== 12) {
            return $mac;
        }

        return implode(':', str_split($hex, 2));
    }
}
