<?php

namespace App\Services\Olt;

use App\Models\Olt;
use App\Models\Router;
use App\Services\MikroTikService;
use App\Support\OltN6DesvioConfig;
use Illuminate\Support\Facades\Log;
use RouterOS\Query;
use Throwable;

class OltN6DesvioService
{
    public function __construct(
        private readonly MikroTikService $mikrotik,
    ) {}

    /**
     * @return array{ok: bool, message: string, ping?: array<string, mixed>}
     */
    public function aplicarDesvio(): array
    {
        $n6 = Router::query()->where('nombre', OltN6DesvioConfig::ROUTER_N6)->first();
        $n2 = Router::query()->where('nombre', OltN6DesvioConfig::ROUTER_N2)->first();
        if (! $n6 || ! $n2) {
            return ['ok' => false, 'message' => 'No están MK-N6-R1 o MK-N2-R2.'];
        }

        $errores = [];
        $errores = array_merge($errores, $this->asegurarAddress($n6, OltN6DesvioConfig::IP_DESVIO, OltN6DesvioConfig::IFACE, OltN6DesvioConfig::COMMENT_ADDR));
        $errores = array_merge($errores, $this->asegurarProxyArp($n6, OltN6DesvioConfig::IFACE));
        $errores = array_merge($errores, $this->asegurarRuta($n6, OltN6DesvioConfig::IP_ACTUAL.'/32', OltN6DesvioConfig::IFACE, OltN6DesvioConfig::COMMENT_RUTA_N6));
        $errores = array_merge($errores, $this->asegurarRuta($n2, OltN6DesvioConfig::IP_ACTUAL.'/32', OltN6DesvioConfig::GW_N6_BACKBONE, OltN6DesvioConfig::COMMENT_RUTA_N2));
        $errores = array_merge($errores, $this->asegurarArpPublicado($n2, OltN6DesvioConfig::IP_ACTUAL, OltN6DesvioConfig::IFACE_LAN_N2, OltN6DesvioConfig::COMMENT_ARP_N2));

        $src = explode('/', OltN6DesvioConfig::IP_DESVIO, 2)[0];
        $ping = $this->mikrotik->pingFromRouter($n6, OltN6DesvioConfig::IP_ACTUAL, 3, $src);

        if ($errores !== []) {
            return [
                'ok' => false,
                'message' => 'Desvío parcial: '.implode(' ', $errores),
                'ping' => $ping,
            ];
        }

        $pingOk = (bool) ($ping['ok'] ?? false);
        $lat = $ping['latency_ms'] ?? null;

        return [
            'ok' => true,
            'message' => $pingOk
                ? 'Desvío listo. MK-N6-R1 '.OltN6DesvioConfig::IFACE.' llega a '.OltN6DesvioConfig::IP_ACTUAL
                    .($lat !== null ? ' ('.$lat.' ms).' : '.')
                : 'Desvío aplicado en N6 y N2, pero el ping a '.OltN6DesvioConfig::IP_ACTUAL.' aún no responde. Revisá el enlace en ether2.',
            'ping' => $ping,
        ];
    }

    /**
     * Saca el desvío 172.16.30.x, mete ether2 en bridge-lan y apunta Infinity a 172.16.60.200.
     *
     * @return array{ok: bool, message: string, ping?: array<string, mixed>}
     */
    public function prepararIpNueva(Olt $olt): array
    {
        return $this->pasarAlBridgeLan($olt);
    }

    /**
     * @return array{ok: bool, message: string, ping?: array<string, mixed>}
     */
    public function pasarAlBridgeLan(Olt $olt): array
    {
        $n6 = Router::query()->where('nombre', OltN6DesvioConfig::ROUTER_N6)->first();
        $n2 = Router::query()->where('nombre', OltN6DesvioConfig::ROUTER_N2)->first();
        if (! $n6 || ! $n2) {
            return ['ok' => false, 'message' => 'No están MK-N6-R1 o MK-N2-R2.'];
        }

        $errores = [];
        $errores = array_merge($errores, $this->quitarAddressInterfaz($n6, OltN6DesvioConfig::IFACE, OltN6DesvioConfig::COMMENT_ADDR));
        $errores = array_merge($errores, $this->quitarRutasDestino($n6, OltN6DesvioConfig::IP_ACTUAL.'/32'));
        $errores = array_merge($errores, $this->quitarRutasDestino($n2, OltN6DesvioConfig::IP_ACTUAL.'/32'));
        $errores = array_merge($errores, $this->quitarArpPublicado($n2, OltN6DesvioConfig::IP_ACTUAL, OltN6DesvioConfig::IFACE_LAN_N2));
        $errores = array_merge($errores, $this->setArpEthernet($n6, OltN6DesvioConfig::IFACE, 'enabled'));
        $errores = array_merge($errores, $this->moverPuertoAlBridge($n6, OltN6DesvioConfig::IFACE, OltN6DesvioConfig::BRIDGE_LAN_N6));

        $olt->update(['ip' => OltN6DesvioConfig::IP_NUEVA]);
        $ping = $this->mikrotik->pingFromRouter($n6, OltN6DesvioConfig::IP_NUEVA, 3, OltN6DesvioConfig::GW_NUEVA);

        if ($errores !== []) {
            return [
                'ok' => false,
                'message' => 'Quedó parcial: '.implode(' ', $errores),
                'ping' => $ping,
            ];
        }

        $pingOk = (bool) ($ping['ok'] ?? false);
        $lat = $ping['latency_ms'] ?? null;

        return [
            'ok' => true,
            'message' => $pingOk
                ? 'Desvío quitado. '.OltN6DesvioConfig::IFACE.' está en '.OltN6DesvioConfig::BRIDGE_LAN_N6
                    .' y '.OltN6DesvioConfig::IP_NUEVA.' responde'
                    .($lat !== null ? ' ('.$lat.' ms).' : '.')
                    .' En el OLT falta gateway '.OltN6DesvioConfig::GW_NUEVA.' si todavía no lo cambiaste.'
                : 'Desvío quitado y '.OltN6DesvioConfig::IFACE.' en '.OltN6DesvioConfig::BRIDGE_LAN_N6
                    .'. Infinity apunta a '.OltN6DesvioConfig::IP_NUEVA
                    .', pero aún no hay ping. Poné gateway '.OltN6DesvioConfig::GW_NUEVA.' en el OLT.',
            'ping' => $ping,
        ];
    }

    /**
     * @return list<string>
     */
    private function asegurarAddress(Router $router, string $cidr, string $iface, string $comment): array
    {
        try {
            foreach ($this->printFiltrado($router, '/ip/address/print', 'interface', $iface, '.id,address,interface,comment') as $row) {
                $actual = (string) ($row['address'] ?? '');
                $mismoComentario = trim((string) ($row['comment'] ?? '')) === $comment;
                if ($actual === $cidr && $mismoComentario) {
                    return [];
                }
                if ($mismoComentario || $this->mismaIpHost($actual, $cidr)) {
                    $this->apiRemove($router, '/ip/address/remove', (string) ($row['.id'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            Log::warning('[OLT N6 desvío] lectura de IPs omitida, se intenta crear', [
                'router' => $router->nombre,
                'error' => $e->getMessage(),
            ]);
        }

        $r = $this->mikrotik->apiAdd($router, '/ip/address/add', [
            'address' => $cidr,
            'interface' => $iface,
            'comment' => $comment,
        ]);
        if (! ($r['success'] ?? false)) {
            $err = (string) ($r['error'] ?? 'error');
            if (str_contains(mb_strtolower($err), 'already') || str_contains(mb_strtolower($err), 'exist')) {
                return [];
            }

            return ['IP '.$cidr.': '.$err];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function asegurarRuta(Router $router, string $dst, string $gateway, string $comment): array
    {
        try {
            foreach ($this->printFiltrado($router, '/ip/route/print', 'comment', $comment, '.id,dst-address,gateway,comment') as $row) {
                return [];
            }
            foreach ($this->printFiltrado($router, '/ip/route/print', 'dst-address', $dst, '.id,dst-address,gateway,comment') as $row) {
                if ((string) ($row['gateway'] ?? '') === $gateway) {
                    return [];
                }
            }
        } catch (Throwable $e) {
            Log::warning('[OLT N6 desvío] lectura de rutas omitida, se intenta crear', [
                'router' => $router->nombre,
                'error' => $e->getMessage(),
            ]);
        }

        $r = $this->mikrotik->apiAdd($router, '/ip/route/add', [
            'dst-address' => $dst,
            'gateway' => $gateway,
            'comment' => $comment,
        ]);
        if (! ($r['success'] ?? false)) {
            $err = (string) ($r['error'] ?? 'error');
            if (str_contains(mb_strtolower($err), 'already') || str_contains(mb_strtolower($err), 'exist')) {
                return [];
            }

            return ['Ruta '.$dst.' en '.$router->nombre.': '.$err];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function asegurarProxyArp(Router $router, string $iface): array
    {
        return $this->setArpEthernet($router, $iface, 'proxy-arp');
    }

    /**
     * @return list<string>
     */
    private function setArpEthernet(Router $router, string $iface, string $arp): array
    {
        try {
            $client = $this->mikrotik->connect($router);
            $rows = $client->query((new Query('/interface/ethernet/print'))
                ->equal('.proplist', '.id,name,arp'))
                ->read();
            $id = '';
            foreach ($rows as $row) {
                if (is_array($row) && strcasecmp((string) ($row['name'] ?? ''), $iface) === 0) {
                    $id = (string) ($row['.id'] ?? '');
                    break;
                }
            }
            if ($id === '') {
                $this->mikrotik->disconnect();

                return ['No está la interfaz '.$iface.' en '.$router->nombre.'.'];
            }
            $raw = $client->query((new Query('/interface/ethernet/set'))
                ->equal('.id', $id)
                ->equal('arp', $arp))
                ->read(false);
            $this->mikrotik->disconnect();
            if (is_array($raw)) {
                foreach ($raw as $row) {
                    if (is_array($row) && isset($row['!trap'])) {
                        return ['arp '.$arp.': '.((string) ($row['message'] ?? 'error'))];
                    }
                }
            }
        } catch (Throwable $e) {
            $this->mikrotik->disconnect();

            return ['arp '.$arp.': '.$e->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function quitarAddressInterfaz(Router $router, string $iface, string $comment): array
    {
        try {
            foreach ($this->printFiltrado($router, '/ip/address/print', 'interface', $iface, '.id,address,interface,comment') as $row) {
                $mismo = trim((string) ($row['comment'] ?? '')) === $comment
                    || $this->mismaIpHost((string) ($row['address'] ?? ''), OltN6DesvioConfig::IP_DESVIO);
                if ($mismo) {
                    $this->apiRemove($router, '/ip/address/remove', (string) ($row['.id'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            return ['Quitar IP de '.$iface.': '.$e->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function quitarRutasDestino(Router $router, string $dst): array
    {
        try {
            foreach ($this->printFiltrado($router, '/ip/route/print', 'dst-address', $dst, '.id,dst-address,gateway,comment') as $row) {
                $this->apiRemove($router, '/ip/route/remove', (string) ($row['.id'] ?? ''));
            }
        } catch (Throwable $e) {
            return ['Quitar ruta '.$dst.' en '.$router->nombre.': '.$e->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function quitarArpPublicado(Router $router, string $ip, string $iface): array
    {
        try {
            foreach ($this->printFiltrado($router, '/ip/arp/print', 'address', $ip, '.id,address,interface,published,comment') as $row) {
                $mismaIface = strcasecmp((string) ($row['interface'] ?? ''), $iface) === 0;
                $publicado = filter_var($row['published'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($mismaIface || $publicado) {
                    $this->apiRemove($router, '/ip/arp/remove', (string) ($row['.id'] ?? ''));
                }
            }
        } catch (Throwable $e) {
            return ['Quitar ARP '.$ip.': '.$e->getMessage()];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function moverPuertoAlBridge(Router $router, string $iface, string $bridge): array
    {
        try {
            $puertos = $this->printFiltrado(
                $router,
                '/interface/bridge/port/print',
                'interface',
                $iface,
                '.id,interface,bridge,disabled'
            );
            if ($puertos !== []) {
                $row = $puertos[0];
                $id = (string) ($row['.id'] ?? '');
                if ($id === '') {
                    return ['No hay .id del puerto '.$iface.'.'];
                }
                $client = $this->mikrotik->connect($router);
                $raw = $client->query((new Query('/interface/bridge/port/set'))
                    ->equal('.id', $id)
                    ->equal('bridge', $bridge)
                    ->equal('disabled', 'no'))
                    ->read(false);
                $this->mikrotik->disconnect();
                if (is_array($raw)) {
                    foreach ($raw as $item) {
                        if (is_array($item) && isset($item['!trap'])) {
                            return ['Bridge '.$bridge.': '.((string) ($item['message'] ?? 'error'))];
                        }
                    }
                }

                return [];
            }

            $r = $this->mikrotik->apiAdd($router, '/interface/bridge/port/add', [
                'interface' => $iface,
                'bridge' => $bridge,
                'comment' => 'OLT-UA LAN N6',
            ]);
            if (! ($r['success'] ?? false)) {
                return ['Bridge '.$bridge.': '.((string) ($r['error'] ?? 'error'))];
            }
        } catch (Throwable $e) {
            $this->mikrotik->disconnect();

            return ['Bridge '.$bridge.': '.$e->getMessage()];
        }

        return [];
    }

    /**
     * Para que las PCs de la LAN N2 no ARP-een al OLT en local: N2 responde y rutea el /32.
     *
     * @return list<string>
     */
    private function asegurarArpPublicado(Router $router, string $ip, string $iface, string $comment): array
    {
        try {
            $existentes = $this->printFiltrado($router, '/ip/arp/print', 'address', $ip, '.id,address,interface,published,comment,dynamic');
            $yaOk = false;
            foreach ($existentes as $row) {
                $publicado = filter_var($row['published'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $mismaIface = strcasecmp((string) ($row['interface'] ?? ''), $iface) === 0;
                if ($publicado && $mismaIface) {
                    $yaOk = true;

                    continue;
                }
                $this->apiRemove($router, '/ip/arp/remove', (string) ($row['.id'] ?? ''));
            }
            if ($yaOk) {
                return [];
            }
        } catch (Throwable $e) {
            Log::warning('[OLT N6 desvío] lectura ARP omitida, se intenta crear', [
                'router' => $router->nombre,
                'error' => $e->getMessage(),
            ]);
        }

        $r = $this->mikrotik->apiAdd($router, '/ip/arp/add', [
            'address' => $ip,
            'interface' => $iface,
            'published' => 'yes',
            'comment' => $comment,
        ]);
        if (! ($r['success'] ?? false)) {
            $err = (string) ($r['error'] ?? 'error');
            if (str_contains(mb_strtolower($err), 'already') || str_contains(mb_strtolower($err), 'exist')) {
                return [];
            }

            return ['ARP publicado '.$ip.': '.$err];
        }

        return [];
    }

    private function mismaIpHost(string $address, string $cidr): bool
    {
        $a = explode('/', $address, 2)[0];
        $b = explode('/', $cidr, 2)[0];

        return $a !== '' && $a === $b;
    }

    private function apiRemove(Router $router, string $path, string $rosId): void
    {
        $rosId = trim($rosId);
        if ($rosId === '') {
            return;
        }
        try {
            $client = $this->mikrotik->connect($router);
            $client->query((new Query($path))->equal('.id', $rosId))->read(false);
            $this->mikrotik->disconnect();
        } catch (Throwable $e) {
            $this->mikrotik->disconnect();
            Log::warning('[OLT N6 desvío] no se pudo borrar '.$path, [
                'id' => $rosId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function printFiltrado(Router $router, string $path, string $whereKey, string $whereVal, string $proplist): array
    {
        $client = $this->mikrotik->connect($router);
        $rows = $client->query((new Query($path))
            ->equal('.proplist', $proplist)
            ->where($whereKey, $whereVal))
            ->read();
        $this->mikrotik->disconnect();

        $out = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row[$whereKey])) {
                $out[] = $row;
            }
        }

        return $out;
    }
}
