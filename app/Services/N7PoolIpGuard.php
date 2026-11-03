<?php

namespace App\Services;

use App\Models\PoolIpAsignada;
use App\Models\Router;
use App\Models\RouterIpPool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Evita que una instalación en MK-N7-01 o MK-N7-02 reciba una IP
 * que ya está como remote-address PPPoE en el otro router.
 */
class N7PoolIpGuard
{
    public const ROUTER_N7_01 = 'MK-N7-01';

    public const ROUTER_N7_02 = 'MK-N7-02';

    private const CACHE_SEGUNDOS = 90;

    public function __construct(
        private MikroTikService $mikrotik
    ) {}

    public static function nombreCompanero(?string $nombreRouter): ?string
    {
        return match ($nombreRouter) {
            self::ROUTER_N7_01 => self::ROUTER_N7_02,
            self::ROUTER_N7_02 => self::ROUTER_N7_01,
            default => null,
        };
    }

    public static function ipDentroDeRango(string $ip, string $cidr): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false || ! str_contains($cidr, '/')) {
            return false;
        }

        [$red, $bits] = explode('/', $cidr, 2);
        $redLong = ip2long($red);
        $bits = (int) $bits;
        if ($redLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mascara = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mascara) === ($redLong & $mascara);
    }

    /**
     * Marca como reservadas las IP del pool que ya usa el router compañero.
     */
    public function reservarSolapadas(RouterIpPool $pool): int
    {
        $ips = $this->ipsOcupadasEnCompanero($pool);
        if ($ips === []) {
            return 0;
        }

        return PoolIpAsignada::query()
            ->where('pool_id', $pool->pool_id)
            ->where('estado', 'disponible')
            ->whereIn('ip', $ips)
            ->update(['estado' => 'reservada']);
    }

    public function ipOcupadaEnCompanero(RouterIpPool $pool, string $ip): bool
    {
        return in_array($ip, $this->ipsOcupadasEnCompanero($pool), true);
    }

    public function mensajeSiOcupada(RouterIpPool $pool, string $ip): ?string
    {
        if (! $this->ipOcupadaEnCompanero($pool, $ip)) {
            return null;
        }

        $companero = self::nombreCompanero($pool->router?->nombre) ?? 'el otro nodo N7';

        return "La IP {$ip} ya está asignada en {$companero}. Elegí otra para que N7-01 y N7-02 no compartan la misma dirección.";
    }

    /**
     * Al liberar una IP, si el compañero todavía la usa queda reservada.
     */
    public function liberar(int $poolId, string $ip): void
    {
        $pool = RouterIpPool::with('router')->find($poolId);
        $estado = ($pool && $this->ipOcupadaEnCompanero($pool, $ip)) ? 'reservada' : 'disponible';

        PoolIpAsignada::query()
            ->where('pool_id', $poolId)
            ->where('ip', $ip)
            ->update(['estado' => $estado]);
    }

    /**
     * @return list<string>
     */
    public function ipsOcupadasEnCompanero(RouterIpPool $pool): array
    {
        if (! $pool->relationLoaded('router')) {
            $pool->load('router');
        }

        $companeroNombre = self::nombreCompanero($pool->router?->nombre);
        $rango = trim((string) $pool->ip_range);
        if ($companeroNombre === null || $rango === '') {
            return [];
        }

        $companero = Router::query()->where('nombre', $companeroNombre)->first();
        if (! $companero) {
            return [];
        }

        $ocupadas = [];
        foreach ($this->remoteAddresses($companero) as $ip) {
            if (self::ipDentroDeRango($ip, $rango)) {
                $ocupadas[] = $ip;
            }
        }

        return array_values(array_unique($ocupadas));
    }

    /**
     * @return list<string>
     */
    private function remoteAddresses(Router $router): array
    {
        $cacheKey = 'n7-ppp-remote:'.$router->router_id;

        try {
            $ips = Cache::remember($cacheKey, self::CACHE_SEGUNDOS, function () use ($router) {
                $lista = [];
                foreach ($this->mikrotik->getPppoeSecrets($router) as $secreto) {
                    $ip = trim((string) ($secreto['remote-address'] ?? ''));
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $lista[] = $ip;
                    }
                }

                return array_values(array_unique($lista));
            });
        } catch (Throwable $e) {
            Log::warning('[N7PoolIpGuard] No se pudieron leer los secretos del router compañero', [
                'router' => $router->nombre,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return is_array($ips) ? $ips : [];
    }
}
