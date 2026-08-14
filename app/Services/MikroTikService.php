<?php

namespace App\Services;

use App\Models\MikrotikOperacionPendiente;
use App\Models\PerfilPppoe;
use App\Models\Router;
use App\Models\RouterNetworkBackup;
use App\Models\RouterNetworkBackupAddress;
use App\Models\RouterNetworkBackupRoute;
use App\Models\RouterScheduler;
use App\Models\RouterScript;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikroTikService
{
    protected ?Client $client = null;

    protected ?Router $router = null;

    public function __construct(
        protected int $timeout = 30,
        protected int $socketTimeout = 60,
        protected bool $ssl = false
    ) {
        $this->timeout = (int) config('mikrotik.timeout', 30);
        $this->socketTimeout = (int) config('mikrotik.socket_timeout', 60);
        $this->ssl = (bool) config('mikrotik.ssl', false);
    }

    /**
     * Obtiene un cliente RouterOS conectado al router dado.
     */
    public function connect(Router $router): Client
    {
        $port = (int) ($router->api_port ?: config('mikrotik.port', 8728));
        $ssl = $this->ssl || $port === 8729;

        Log::info('[MikroTik] Conectando', ['router' => $router->ip, 'port' => $port, 'ssl' => $ssl]);

        $config = new Config([
            'host' => $router->ip,
            'user' => $router->usuario,
            'pass' => $router->password ?? '',
            'port' => $port,
            'timeout' => $this->timeout,
            'socket_timeout' => $this->socketTimeout,
            'ssl' => $ssl,
        ]);

        $this->client = new Client($config);
        $this->router = $router;

        Log::info('[MikroTik] Conexión establecida', ['router' => $router->ip]);

        return $this->client;
    }

    /**
     * Cierra la conexión actual (el cliente no suele exponer disconnect; se deja a GC).
     */
    public function disconnect(): void
    {
        $this->client = null;
        $this->router = null;
    }

    /**
     * Comprueba si la conexión al router responde.
     */
    public function testConnection(Router $router): array
    {
        try {
            $client = $this->connect($router);
            $query = new Query('/system/resource/print');
            $response = $client->query($query)->read();
            $this->disconnect();
            return ['success' => true, 'data' => $response];
        } catch (Throwable $e) {
            Log::warning('MikroTik testConnection failed', ['router' => $router->router_id, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta leases DHCP y sesiones PPPoE activas (/ppp/active).
     *
     * @return array{
     *   success: bool,
     *   dhcp_activos?: int,
     *   dhcp_total?: int,
     *   pppoe_activos?: int,
     *   dhcp_leases?: list<array{address: string, mac: string, hostname: string, status: string, server: string, expires: string, active: bool, static: bool}>,
     *   message?: string,
     *   error?: string
     * }
     */
    public function consultarDhcpYPppoeActivos(Router $router): array
    {
        try {
            $client = $this->connect($router);

            $leasesRaw = $client->query(new Query('/ip/dhcp-server/lease/print'))->read();
            $leasesRaw = is_array($leasesRaw) ? $leasesRaw : [];
            $dhcpLeases = [];
            $dhcpActivos = 0;

            foreach ($leasesRaw as $lease) {
                if (! is_array($lease)) {
                    continue;
                }
                $status = strtolower(trim((string) ($lease['status'] ?? '')));
                // bound = lease en uso; sin status (algunas versiones) también se cuenta como activo
                $activo = $status === 'bound' || $status === '';
                if ($activo) {
                    $dhcpActivos++;
                }

                // RouterOS: dynamic=true → lease dinámico; false/ausente con make-static → estático
                $dynRaw = strtolower(trim((string) ($lease['dynamic'] ?? 'true')));
                $esEstatico = ! in_array($dynRaw, ['true', 'yes', '1'], true);

                $dhcpLeases[] = [
                    'address' => trim((string) ($lease['address'] ?? '')),
                    'mac' => trim((string) ($lease['mac-address'] ?? $lease['mac_address'] ?? '')),
                    'hostname' => trim((string) ($lease['host-name'] ?? $lease['host_name'] ?? '')),
                    'status' => $status !== '' ? $status : 'bound',
                    'server' => trim((string) ($lease['server'] ?? '')),
                    'expires' => trim((string) ($lease['expires-after'] ?? $lease['expires_after'] ?? '')),
                    'active' => $activo,
                    'static' => $esEstatico,
                ];
            }

            usort($dhcpLeases, function (array $a, array $b): int {
                if ($a['active'] !== $b['active']) {
                    return $a['active'] ? -1 : 1;
                }

                return strnatcmp($a['address'], $b['address']);
            });

            $pppActive = $client->query(new Query('/ppp/active/print'))->read();
            $pppActive = is_array($pppActive) ? $pppActive : [];
            $pppoeActivos = 0;
            foreach ($pppActive as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $service = strtolower(trim((string) ($row['service'] ?? 'pppoe')));
                // Contar PPPoE; si no hay service, asumir pppoe (comportamiento habitual)
                if ($service === '' || $service === 'pppoe' || $service === 'any') {
                    $pppoeActivos++;
                }
            }

            $this->disconnect();

            $dhcpTotal = count($dhcpLeases);

            return [
                'success' => true,
                'dhcp_activos' => $dhcpActivos,
                'dhcp_total' => $dhcpTotal,
                'pppoe_activos' => $pppoeActivos,
                'dhcp_leases' => $dhcpLeases,
                'message' => sprintf(
                    'DHCP activos: %d%s · PPPoE activos: %d',
                    $dhcpActivos,
                    $dhcpTotal !== $dhcpActivos ? " (total {$dhcpTotal})" : '',
                    $pppoeActivos
                ),
            ];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('MikroTik consultarDhcpYPppoeActivos failed', [
                'router' => $router->router_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lista los perfiles PPP en el router (/ppp/profile).
     */
    public function getPppProfiles(Router $router): array
    {
        $client = $this->connect($router);
        $query = new Query('/ppp/profile/print');
        $response = $client->query($query)->read();
        $this->disconnect();
        return is_array($response) ? $response : [];
    }

    /**
     * Lista los secretos PPP en el router (/ppp/secret).
     *
     * @param  string|null  $service  p.ej. pppoe; null = todos los servicios
     */
    public function getPppoeSecrets(Router $router, ?string $service = null): array
    {
        Log::info('[MikroTik] getPppoeSecrets: iniciando', ['router' => $router->ip, 'service' => $service]);
        $client = $this->connect($router);
        $query = new Query('/ppp/secret/print');
        if ($service !== null && $service !== '') {
            $query->where('service', $service);
        }
        Log::info('[MikroTik] getPppoeSecrets: enviando query, esperando respuesta...', ['router' => $router->ip]);
        $response = $client->query($query)->read();
        $count = is_array($response) ? count($response) : 0;
        Log::info('[MikroTik] getPppoeSecrets: OK', ['router' => $router->ip, 'secrets_count' => $count]);
        $this->disconnect();

        return is_array($response) ? $response : [];
    }

    /**
     * Obtiene un solo secreto PPPoE por nombre. Mucho más rápido que getPppoeSecrets
     * cuando el router tiene muchos usuarios (evita timeout al no listar todos).
     */
    public function getPppoeSecretByName(Router $router, string $name): ?array
    {
        Log::info('[MikroTik] getPppoeSecretByName: iniciando', ['router' => $router->ip, 'name' => $name]);
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/print'))->where('name', $name);
        Log::info('[MikroTik] getPppoeSecretByName: enviando query...', ['router' => $router->ip, 'name' => $name]);
        $response = $client->query($query)->read();
        $this->disconnect();
        $items = is_array($response) ? $response : [];
        $found = ! empty($items) ? $items[0] : null;
        Log::info('[MikroTik] getPppoeSecretByName: OK', ['router' => $router->ip, 'name' => $name, 'found' => (bool) $found]);
        return $found;
    }

    /**
     * Añade un usuario PPPoE en el router.
     *
     * @param  string  $name  usuario (name en RouterOS)
     * @param  string  $password  contraseña
     * @param  string|null  $remoteAddress  IP asignada al cliente (remote-address)
     * @param  string|null  $profile  nombre del perfil PPPoE en RouterOS
     * @param  string|null  $localAddress  IP loopback del router (local-address)
     * @param  string|null  $comment  comentario (ej: nombre del cliente)
     */
    public function addPppoeSecret(Router $router, string $name, string $password, ?string $remoteAddress = null, ?string $profile = null, ?string $localAddress = null, ?string $comment = null, bool $disabled = false): array
    {
        Log::info('[MikroTik] addPppoeSecret: iniciando', ['router' => $router->ip, 'name' => $name, 'disabled' => $disabled]);
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/add'))
            ->equal('name', $name)
            ->equal('password', $password)
            ->equal('service', 'pppoe')
            ->equal('disabled', $disabled ? 'yes' : 'no');
        if ($remoteAddress !== null && $remoteAddress !== '') {
            $query->equal('remote-address', $remoteAddress);
        }
        if ($profile !== null && $profile !== '') {
            $query->equal('profile', $profile);
        }
        if ($localAddress !== null && $localAddress !== '') {
            $query->equal('local-address', $localAddress);
        }
        if ($comment !== null && $comment !== '') {
            $query->equal('comment', $comment);
        }
        Log::info('[MikroTik] addPppoeSecret: enviando add, esperando respuesta...', ['router' => $router->ip, 'name' => $name]);
        $response = $client->query($query)->read();
        Log::info('[MikroTik] addPppoeSecret: OK', ['router' => $router->ip, 'name' => $name]);
        $this->disconnect();
        return ['success' => true, 'response' => $response];
    }

    /**
     * Actualiza un secreto PPPoE por .id.
     */
    public function setPppoeSecret(Router $router, string $rosId, array $attributes): array
    {
        Log::info('[MikroTik] setPppoeSecret: iniciando', ['router' => $router->ip, 'ros_id' => $rosId]);
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/set'))->equal('.id', $rosId);
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $query->equal($key, (string) $value);
            }
        }
        Log::info('[MikroTik] setPppoeSecret: enviando set, esperando respuesta...', ['router' => $router->ip, 'ros_id' => $rosId]);
        $client->query($query)->read();
        Log::info('[MikroTik] setPppoeSecret: OK', ['router' => $router->ip, 'ros_id' => $rosId]);
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Elimina un secreto PPPoE por .id.
     */
    public function removePppoeSecret(Router $router, string $rosId): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/remove'))->equal('.id', $rosId);
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Elimina un secreto PPPoE por nombre de usuario (consulta puntual, sin listar todos los secretos).
     *
     * @return array{success: bool, removed?: bool, message?: string, error?: string}
     */
    public function removePppoeSecretByName(Router $router, string $name): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => true, 'removed' => false, 'message' => 'Sin nombre de usuario'];
        }

        try {
            $secret = $this->getPppoeSecretByName($router, $name);
            if (! $secret || empty($secret['.id'])) {
                Log::info('[MikroTik] removePppoeSecretByName: no existe en router', ['router' => $router->ip, 'name' => $name]);

                return ['success' => true, 'removed' => false, 'message' => 'Usuario no encontrado en el router'];
            }
            $this->removePppoeSecret($router, $secret['.id']);
            Log::info('[MikroTik] removePppoeSecretByName: eliminado', ['router' => $router->ip, 'name' => $name]);

            return ['success' => true, 'removed' => true];
        } catch (Throwable $e) {
            Log::warning('[MikroTik] removePppoeSecretByName failed', [
                'router' => $router->ip,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'removed' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Al eliminar un servicio (o antes de borrar los servicios de un cliente): quita el secreto PPPoE en MikroTik.
     * Si falla la API, registra operación pendiente para reintento (payload por router + usuario).
     *
     * @return array{success: bool, aviso: string|null} aviso texto para flash si hubo fallo de red/API
     */
    public function quitarPppoeAlBorrarServicio(Servicio $servicio, string $origen = 'servicios.destroy'): array
    {
        $servicio->loadMissing('pool.router');
        $usuario = trim((string) ($servicio->usuario_pppoe ?? ''));
        if ($usuario === '' || ! $servicio->pool?->router) {
            return ['success' => true, 'aviso' => null];
        }

        $router = $servicio->pool->router;
        $quitar = $this->removePppoeSecretByName($router, $usuario);
        if (! $quitar['success']) {
            MikrotikOperacionPendiente::registrarSiFallo(
                MikrotikOperacionPendiente::TIPO_REMOVE_PPPOE_SECRET,
                ['router_id' => $router->router_id, 'usuario_pppoe' => $usuario],
                $quitar['error'] ?? 'Error al eliminar secreto',
                $origen
            );

            return [
                'success' => false,
                'aviso' => 'No se pudo eliminar el usuario PPPoE «'.$usuario.'» en MikroTik: '.($quitar['error'] ?? 'error desconocido').'.',
            ];
        }

        return ['success' => true, 'aviso' => null];
    }

    /**
     * Sincroniza los perfiles PPPoE de la BD al router MikroTik (/ppp/profile).
     */
    public function syncProfilesToRouter(Router $router): array
    {
        $added = 0;
        $updated = 0;
        $errors = [];

        $perfiles = PerfilPppoe::orderBy('nombre')->get();
        $existingProfiles = $this->getPppProfiles($router);
        $profilesByName = [];
        foreach ($existingProfiles as $p) {
            $name = $p['name'] ?? null;
            if ($name) {
                $profilesByName[$name] = $p;
            }
        }

        foreach ($perfiles as $perfil) {
            $name = $perfil->nombre ?: ('perfil-' . $perfil->perfil_pppoe_id);
            $localAddress = $router->ip_loopback ?: null;
            $remoteAddress = null;
            $rateLimit = $perfil->rate_limit_tx_rx ?: null;

            try {
                if (isset($profilesByName[$name])) {
                    $attrs = [];
                    if ($localAddress !== null && $localAddress !== '') {
                        $attrs['local-address'] = $localAddress;
                    }
                    if ($rateLimit !== null) {
                        $attrs['rate-limit'] = $rateLimit;
                    }
                    if (! empty($attrs)) {
                        $this->setPppProfile($router, $profilesByName[$name]['.id'] ?? null, $attrs);
                        $updated++;
                    }
                } else {
                    $this->addPppProfile($router, $name, $localAddress, $remoteAddress, $rateLimit);
                    $added++;
                }
            } catch (Throwable $e) {
                $errors[] = $name . ': ' . $e->getMessage();
                Log::error('MikroTik sync profile error', ['perfil' => $name, 'error' => $e->getMessage()]);
            }
        }

        return [
            'success' => empty($errors),
            'added' => $added,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Añade un perfil PPP en el router.
     */
    public function addPppProfile(Router $router, string $name, ?string $localAddress, ?string $remoteAddress, ?string $rateLimit): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ppp/profile/add'))->equal('name', $name);
        if ($localAddress !== null && $localAddress !== '') {
            $query->equal('local-address', $localAddress);
        }
        if ($remoteAddress !== null && $remoteAddress !== '') {
            $query->equal('remote-address', $remoteAddress);
        }
        if ($rateLimit !== null && $rateLimit !== '') {
            $query->equal('rate-limit', $rateLimit);
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Actualiza un perfil PPP por .id.
     */
    public function setPppProfile(Router $router, ?string $rosId, array $attributes): array
    {
        if (! $rosId) {
            return ['success' => false, 'error' => 'ID de perfil no válido'];
        }
        $client = $this->connect($router);
        $query = (new Query('/ppp/profile/set'))->equal('.id', $rosId);
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $query->equal($key, (string) $value);
            }
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Sincroniza usuarios PPPoE desde la base de datos al router: servicios activos
     * y suspendidos del router (por pool) se añaden o actualizan; los suspendidos
     * quedan con disabled=yes. Usuarios que ya no están en BD se pueden eliminar
     * opcionalmente.
     */
    public function syncPppoeFromDatabase(Router $router, bool $removeOrphans = false): array
    {
        $added = 0;
        $updated = 0;
        $removed = 0;
        $errors = [];

        $servicios = $this->serviciosPppoeDelRouter($router);
        $usernamesFromDb = [];

        Log::info('[MikroTik] syncPppoeFromDatabase: iniciando', [
            'router' => $router->ip,
            'router_id' => $router->router_id,
            'servicios_count' => $servicios->count(),
            'activos' => $servicios->where('estado', Servicio::ESTADO_ACTIVO)->count(),
            'suspendidos' => $servicios->where('estado', Servicio::ESTADO_SUSPENDIDO)->count(),
        ]);

        foreach ($servicios as $servicio) {
            $usuario = trim((string) $servicio->usuario_pppoe);
            if ($usuario === '') {
                continue;
            }

            $usernamesFromDb[] = $usuario;

            $result = $this->syncPppoeServicioEnRouter($servicio, $router, true);
            if ($result['success'] ?? false) {
                if (($result['action'] ?? '') === 'added') {
                    $added++;
                } else {
                    $updated++;
                }

                continue;
            }

            $errors[] = $usuario.': '.($result['error'] ?? 'error desconocido');
        }

        if ($removeOrphans) {
            try {
                foreach ($this->getPppoeSecrets($router) as $s) {
                    $name = $s['name'] ?? null;
                    if ($name && ! in_array($name, $usernamesFromDb, true)) {
                        try {
                            $this->removePppoeSecret($router, $s['.id']);
                            $removed++;
                        } catch (Throwable $e) {
                            $errors[] = "remove {$name}: ".$e->getMessage();
                        }
                    }
                }
            } catch (Throwable $e) {
                $errors[] = 'listar secretos: '.$e->getMessage();
            }
        }

        $message = null;
        if ($servicios->isEmpty()) {
            $poolCount = $router->routerIpPools()->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })->count();
            $message = $poolCount === 0
                ? 'Este router no tiene pools de IP activos. Creá pools en Sistema → Pools de IP.'
                : 'No hay servicios activos/suspendidos con usuario PPPoE en los pools de este router.';
        }

        return [
            'success' => empty($errors),
            'servicios_total' => $servicios->count(),
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * Sincroniza un solo servicio PPPoE al router (añadir o actualizar).
     */
    public function syncPppoeServicio(Servicio $servicio): array
    {
        if (! $servicio->usuario_pppoe || ! $servicio->pool?->router) {
            return ['success' => false, 'error' => 'Servicio sin usuario PPPoE o sin router asociado.'];
        }

        return $this->syncPppoeServicioEnRouter($servicio, $servicio->pool->router);
    }

    /**
     * Sincroniza credenciales PPPoE del servicio en un router concreto (sin cambiar pool en BD).
     */
    public function syncPppoeServicioEnRouter(Servicio $servicio, Router $router, bool $actualizarEstadoServicio = true): array
    {
        if (! $servicio->usuario_pppoe) {
            return ['success' => false, 'error' => 'Servicio sin usuario PPPoE.'];
        }

        $servicio->loadMissing(['plan.perfilPppoe', 'cliente']);
        $profileName = $servicio->plan?->perfilPppoe?->nombre ?? $servicio->plan?->nombre ?? 'default';
        $password = $servicio->password_pppoe ?? '';
        $remoteAddress = $servicio->ip ?: null;
        $localAddress = $router->ip_loopback ?: null;
        $nombreCliente = trim(($servicio->cliente?->nombre ?? '') . ' ' . ($servicio->cliente?->apellido ?? ''));
        $disabled = $servicio->estado === Servicio::ESTADO_SUSPENDIDO
            || $servicio->estado === Servicio::ESTADO_CORTADO;

        Log::info('[MikroTik] syncPppoeServicio: iniciando', [
            'router' => $router->ip,
            'usuario' => $servicio->usuario_pppoe,
            'estado' => $servicio->estado,
            'disabled' => $disabled,
        ]);

        try {
            $existing = $this->getPppoeSecretByName($router, $servicio->usuario_pppoe);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            Log::error('[MikroTik] syncPppoeServicio: error en getPppoeSecretByName', [
                'router' => $router->ip,
                'usuario' => $servicio->usuario_pppoe,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            if (str_contains($msg, 'Error reading') || str_contains($msg, 'StreamException') || str_contains($msg, 'Stream timed out') || str_contains($msg, 'Connection')) {
                $msg = 'No se pudo conectar al router MikroTik. Verifica IP, puerto, SSL y que el router esté accesible en la red.';
            }
            return ['success' => false, 'error' => $msg];
        }
        Log::info('[MikroTik] syncPppoeServicio: consulta OK, procediendo a add/set', [
            'router' => $router->ip,
            'usuario' => $servicio->usuario_pppoe,
            'accion' => $existing ? 'set (actualizar)' : 'add (crear)',
        ]);

        try {
            if ($existing) {
                $attrs = [
                    'profile' => $profileName,
                    'service' => 'pppoe',
                    'disabled' => $disabled ? 'yes' : 'no',
                ];
                if ($localAddress !== null && $localAddress !== '') {
                    $attrs['local-address'] = $localAddress;
                }
                if ($remoteAddress !== null) {
                    $attrs['remote-address'] = $remoteAddress;
                }
                if ($password !== '') {
                    $attrs['password'] = $password;
                }
                if ($nombreCliente !== '') {
                    $attrs['comment'] = $nombreCliente;
                }
                $this->setPppoeSecret($router, $existing['.id'], $attrs);
            } else {
                $this->addPppoeSecret(
                    $router,
                    $servicio->usuario_pppoe,
                    $password,
                    $remoteAddress,
                    $profileName,
                    $localAddress,
                    $nombreCliente ?: null,
                    $disabled,
                );
            }
            if ($actualizarEstadoServicio) {
                $servicio->update(['pppoe_synced' => now(), 'pppoe_status' => 'synced']);
            }
            Log::info('[MikroTik] syncPppoeServicio: completado OK', [
                'router' => $router->ip,
                'usuario' => $servicio->usuario_pppoe,
                'disabled' => $disabled,
            ]);

            return ['success' => true, 'action' => $existing ? 'updated' : 'added'];
        } catch (Throwable $e) {
            Log::error('[MikroTik] syncPppoeServicio: error en add/set', [
                'router' => $router->ip,
                'usuario' => $servicio->usuario_pppoe,
                'accion' => $existing !== null ? 'set' : 'add',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lista los usuarios hotspot en el router (/ip/hotspot/user).
     */
    public function getHotspotUsers(Router $router, ?string $server = null): array
    {
        $client = $this->connect($router);
        $query = new Query('/ip/hotspot/user/print');
        if ($server !== null && $server !== '') {
            $query->where('server', $server);
        }
        $response = $client->query($query)->read();
        $this->disconnect();
        return is_array($response) ? $response : [];
    }

    /**
     * Lista los hosts activos en hotspot.
     * Intenta /ip/hotspot/active/print (usuarios autenticados) y si falla usa /ip/hotspot/host/print.
     */
    public function getHotspotActiveHosts(Router $router, ?string $server = null): array
    {
        $client = $this->connect($router);
        try {
            $query = new Query('/ip/hotspot/active/print');
            if ($server !== null && $server !== '') {
                $query->where('server', $server);
            }
            $response = $client->query($query)->read();
        } catch (Throwable $e) {
            try {
                $query = new Query('/ip/hotspot/host/print');
                if ($server !== null && $server !== '') {
                    $query->where('server', $server);
                }
                $response = $client->query($query)->read();
            } catch (Throwable $e2) {
                $this->disconnect();
                throw $e;
            }
        }
        $this->disconnect();
        return is_array($response) ? $response : [];
    }

    /**
     * Añade un usuario hotspot.
     */
    public function addHotspotUser(Router $router, string $name, string $password, ?string $profile = null, ?string $comment = null, ?string $server = null): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ip/hotspot/user/add'))
            ->equal('name', $name)
            ->equal('password', $password);
        if ($profile !== null && $profile !== '') {
            $query->equal('profile', $profile);
        }
        if ($comment !== null && $comment !== '') {
            $query->equal('comment', $comment);
        }
        if ($server !== null && $server !== '') {
            $query->equal('server', $server);
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Actualiza un usuario hotspot por .id.
     */
    public function setHotspotUser(Router $router, string $rosId, array $attributes): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ip/hotspot/user/set'))->equal('.id', $rosId);
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $query->equal($key, (string) $value);
            }
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Elimina un usuario hotspot por .id.
     */
    public function removeHotspotUser(Router $router, string $rosId): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ip/hotspot/user/remove'))->equal('.id', $rosId);
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Lista los perfiles de usuario hotspot (/ip/hotspot/user/profile).
     */
    public function getHotspotUserProfiles(Router $router): array
    {
        $client = $this->connect($router);
        $query = new Query('/ip/hotspot/user/profile/print');
        $response = $client->query($query)->read();
        $this->disconnect();
        return is_array($response) ? $response : [];
    }

    /**
     * Añade un perfil de usuario hotspot.
     */
    public function addHotspotUserProfile(Router $router, string $name, ?string $rateLimit = null, ?string $sharedUsers = null): array
    {
        $client = $this->connect($router);
        $query = (new Query('/ip/hotspot/user/profile/add'))->equal('name', $name);
        if ($rateLimit !== null && $rateLimit !== '') {
            $query->equal('rate-limit', $rateLimit);
        }
        if ($sharedUsers !== null && $sharedUsers !== '') {
            $query->equal('shared-users', $sharedUsers);
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Actualiza un perfil de usuario hotspot por .id.
     * Mapeo de atributos: rate_limit -> rate-limit, shared_users -> shared-users, etc.
     */
    public function setHotspotUserProfile(Router $router, string $rosId, array $attributes): array
    {
        $map = ['rate_limit' => 'rate-limit', 'shared_users' => 'shared-users', 'idle_timeout' => 'idle-timeout', 'session_timeout' => 'session-timeout'];
        $client = $this->connect($router);
        $query = (new Query('/ip/hotspot/user/profile/set'))->equal('.id', $rosId);
        foreach ($attributes as $key => $value) {
            if ($value !== null && $value !== '') {
                $rosKey = $map[$key] ?? str_replace('_', '-', $key);
                $query->equal($rosKey, (string) $value);
            }
        }
        $client->query($query)->read();
        $this->disconnect();
        return ['success' => true];
    }

    /**
     * Sincroniza un ServicioHotspot al router MikroTik.
     */
    public function syncHotspotServicio(ServicioHotspot $sh): array
    {
        $router = $sh->router;
        $server = $router->hotspot_servidor;
        $profileName = $sh->hotspotPerfil?->nombre ?? 'default';
        $users = $this->getHotspotUsers($router, $server);
        $existing = collect($users)->firstWhere('name', $sh->username);

        try {
            if ($existing) {
                $this->setHotspotUser($router, $existing['.id'], [
                    'password' => $sh->password,
                    'profile' => $profileName,
                    'comment' => $sh->comment ?? '',
                ]);
                $sh->update(['ros_id' => $existing['.id'], 'last_synced' => now()]);
            } else {
                $this->addHotspotUser($router, $sh->username, $sh->password, $profileName, $sh->comment, $server);
                $users = $this->getHotspotUsers($router, $server);
                $newUser = collect($users)->firstWhere('name', $sh->username);
                $sh->update(['ros_id' => $newUser['.id'] ?? null, 'last_synced' => now()]);
            }
            return ['success' => true];
        } catch (Throwable $e) {
            Log::error('[MikroTik] syncHotspotServicio error', ['servicio' => $sh->servicio_id, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Deshabilita o habilita el usuario PPPoE en el router.
     * Si se pide habilitar y el secreto no existe, sincroniza (crea) el usuario desde la BD.
     */
    public function setPppoeDisabledEnRouter(Servicio $servicio, bool $disabled): array
    {
        if (! $servicio->usuario_pppoe || ! $servicio->pool?->router) {
            return ['success' => false, 'error' => 'Servicio sin usuario PPPoE o sin router asociado.'];
        }

        $router = $servicio->pool->router;

        try {
            $existing = $this->getPppoeSecretByName($router, $servicio->usuario_pppoe);
            if ($existing && isset($existing['.id'])) {
                $this->setPppoeSecret($router, $existing['.id'], ['disabled' => $disabled ? 'yes' : 'no']);

                return ['success' => true];
            }

            // Reactivación (cobro, activar manual, promesa): crear/sincronizar si falta el secreto.
            if (! $disabled) {
                Log::info('[MikroTik] setPppoeDisabledEnRouter: usuario ausente, sincronizando PPPoE', [
                    'servicio' => $servicio->servicio_id,
                    'usuario' => $servicio->usuario_pppoe,
                    'router' => $router->router_id,
                ]);

                return $this->syncPppoeServicio($servicio);
            }

            // Deshabilitar y no está en el router: ya queda “cortado” de facto.
            return ['success' => true, 'message' => 'Usuario no encontrado en el router (nada que deshabilitar).'];
        } catch (Throwable $e) {
            Log::error('[MikroTik] setPppoeDisabledEnRouter error', [
                'servicio' => $servicio->servicio_id,
                'usuario' => $servicio->usuario_pppoe,
                'router' => $router->router_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $this->mensajeErrorConexion($e, $router)];
        }
    }

    /**
     * Mensaje legible cuando el router no responde (apagado, sin red, credenciales, etc.).
     */
    public function mensajeErrorConexion(Throwable $e, ?Router $router = null): string
    {
        $detalle = trim($e->getMessage());
        $routerEtiqueta = $router ? ($router->nombre ?: $router->ip) : 'router';

        if ($detalle === '') {
            return "No se pudo conectar al router «{$routerEtiqueta}». Verificá que esté encendido y accesible por API.";
        }

        $lower = strtolower($detalle);
        if (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
            return "El router «{$routerEtiqueta}» no respondió a tiempo (puede estar apagado o inaccesible).";
        }
        if (str_contains($lower, 'connection refused') || str_contains($lower, 'could not connect')) {
            return "No se pudo conectar al router «{$routerEtiqueta}» (conexión rechazada o sin respuesta).";
        }

        return "No se pudo conectar al router «{$routerEtiqueta}»: {$detalle}";
    }

    /**
     * Servicios activos con usuario PPPoE asociados a los pools del router.
     *
     * @param  list<int|string>  $poolIds
     * @return \Illuminate\Support\Collection<int, Servicio>
     */
    public function serviciosPppoeActivosDelRouter(Router $router, ?array $poolIds = null): \Illuminate\Support\Collection
    {
        return $this->serviciosPppoeDelRouter($router, $poolIds, [Servicio::ESTADO_ACTIVO]);
    }

    /**
     * Servicios PPPoE del router (por defecto activos + suspendidos).
     *
     * @param  list<int|string>|null  $poolIds
     * @param  list<string>|null  $estados
     * @return \Illuminate\Support\Collection<int, Servicio>
     */
    public function serviciosPppoeDelRouter(Router $router, ?array $poolIds = null, ?array $estados = null): \Illuminate\Support\Collection
    {
        $poolIds ??= $router->routerIpPools()
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->pluck('pool_id')
            ->all();

        if ($poolIds === []) {
            return collect();
        }

        $estados ??= [Servicio::ESTADO_ACTIVO, Servicio::ESTADO_SUSPENDIDO];

        return Servicio::with(['plan.perfilPppoe', 'pool', 'cliente'])
            ->whereIn('pool_id', $poolIds)
            ->whereIn('estado', $estados)
            ->whereNotNull('usuario_pppoe')
            ->where('usuario_pppoe', '!=', '')
            ->orderBy('usuario_pppoe')
            ->get();
    }

    /**
     * Genera comandos RouterOS con usuarios PPPoE activos del router.
     *
     * @param  bool  $paraConsola  true: listo para pegar en terminal (sin comentarios, remove tolerante a error)
     */
    public function generarScriptPppoeExport(Router $router, bool $paraConsola = true): string
    {
        $router->loadMissing('nodo');
        $servicios = $this->serviciosPppoeActivosDelRouter($router);
        $localAddress = $router->ip_loopback ?: null;
        $lineas = [];

        if (! $paraConsola) {
            $lineas = [
                '# Infinity ISP - Export PPPoE',
                '# Router: '.($router->nombre ?? '—').' ('.($router->ip ?? '—').')',
                '# Nodo: '.($router->nodo?->descripcion ?? '—'),
                '# Generado: '.now()->format('Y-m-d H:i:s'),
                '# Usuarios: '.$servicios->count(),
                '#',
                '# Importar: /import file-name.rsc',
                '',
            ];
        }

        foreach ($servicios as $servicio) {
            $usuario = trim((string) $servicio->usuario_pppoe);
            if ($usuario === '') {
                continue;
            }

            $profileName = $servicio->plan?->perfilPppoe?->nombre ?? $servicio->plan?->nombre ?? 'default';
            $password = (string) ($servicio->password_pppoe ?? '');
            $remoteAddress = $servicio->ip ?: null;
            $nombreCliente = trim(($servicio->cliente?->nombre ?? '').' '.($servicio->cliente?->apellido ?? ''));

            if ($paraConsola) {
                $lineas[] = ':do { /ppp secret remove [find where name='.$this->escapeRouterOsValue($usuario).'] } on-error={}';
            } else {
                $lineas[] = '/ppp secret remove [find where name='.$this->escapeRouterOsValue($usuario).']';
            }

            $lineas[] = $this->construirComandoPppoeSecretAdd(
                $usuario,
                $password,
                $profileName,
                $remoteAddress,
                $localAddress,
                $nombreCliente !== '' ? $nombreCliente : null
            );

            if (! $paraConsola) {
                $lineas[] = '';
            }
        }

        return implode("\n", $lineas);
    }

    /**
     * Consulta MAC y tráfico del cliente en el router (ARP / PPP active / DHCP / queue).
     *
     * @return array{
     *   success: bool,
     *   message: string,
     *   router?: string,
     *   mac?: string|null,
     *   mac_fuente?: string|null,
     *   online?: bool,
     *   bytes_in?: int|null,
     *   bytes_out?: int|null,
     *   download_humano?: string|null,
     *   upload_humano?: string|null,
     *   trafico_fuente?: string|null,
     *   uptime?: string|null,
     *   detalle?: array<string, mixed>
     * }
     */
    public function consultarClienteRed(Router $router, ?string $ip, ?string $usuarioPppoe = null): array
    {
        $ip = trim((string) $ip);
        $usuarioPppoe = trim((string) $usuarioPppoe);

        if ($ip === '' && $usuarioPppoe === '') {
            return [
                'success' => false,
                'message' => 'El servicio no tiene IP ni usuario PPPoE para consultar en MikroTik.',
            ];
        }

        try {
            $client = $this->connect($router);

            $mac = null;
            $macFuente = null;
            $online = false;
            $bytesIn = null;
            $bytesOut = null;
            $traficoFuente = null;
            $uptime = null;
            $detalle = [];

            if ($usuarioPppoe !== '') {
                $query = (new Query('/ppp/active/print'))->where('name', $usuarioPppoe);
                $activos = $client->query($query)->read();
                $activo = is_array($activos) && $activos !== [] ? $activos[0] : null;

                if (is_array($activo)) {
                    $online = true;
                    $callerId = trim((string) ($activo['caller-id'] ?? ''));
                    if ($callerId !== '' && $this->pareceMac($callerId)) {
                        $mac = $this->normalizarMac($callerId);
                        $macFuente = 'ppp/active (caller-id)';
                    }
                    $bytesIn = $this->parseBytes($activo['bytes-in'] ?? $activo['bytes_in'] ?? null);
                    $bytesOut = $this->parseBytes($activo['bytes-out'] ?? $activo['bytes_out'] ?? null);
                    if ($bytesIn !== null || $bytesOut !== null) {
                        $traficoFuente = 'ppp/active (sesión actual)';
                    }
                    $uptime = (string) ($activo['uptime'] ?? '') ?: null;
                    $detalle['ppp_active'] = [
                        'name' => $activo['name'] ?? $usuarioPppoe,
                        'address' => $activo['address'] ?? null,
                        'caller-id' => $activo['caller-id'] ?? null,
                        'service' => $activo['service'] ?? null,
                        'uptime' => $uptime,
                        'bytes-in' => $activo['bytes-in'] ?? null,
                        'bytes-out' => $activo['bytes-out'] ?? null,
                    ];
                }
            }

            if ($ip !== '') {
                $queryArp = (new Query('/ip/arp/print'))->where('address', $ip);
                $arps = $client->query($queryArp)->read();
                $arp = is_array($arps) && $arps !== [] ? $arps[0] : null;
                if (is_array($arp)) {
                    $detalle['arp'] = [
                        'address' => $arp['address'] ?? $ip,
                        'mac-address' => $arp['mac-address'] ?? null,
                        'interface' => $arp['interface'] ?? null,
                        'complete' => $arp['complete'] ?? null,
                    ];
                    $macArp = trim((string) ($arp['mac-address'] ?? ''));
                    if ($mac === null && $macArp !== '' && $this->pareceMac($macArp)) {
                        $mac = $this->normalizarMac($macArp);
                        $macFuente = 'ip/arp';
                    }
                }

                try {
                    $queryDhcp = (new Query('/ip/dhcp-server/lease/print'))->where('address', $ip);
                    $leases = $client->query($queryDhcp)->read();
                    $lease = is_array($leases) && $leases !== [] ? $leases[0] : null;
                    if (is_array($lease)) {
                        $detalle['dhcp_lease'] = [
                            'address' => $lease['address'] ?? $ip,
                            'mac-address' => $lease['mac-address'] ?? null,
                            'status' => $lease['status'] ?? null,
                            'host-name' => $lease['host-name'] ?? null,
                        ];
                        $macDhcp = trim((string) ($lease['mac-address'] ?? ''));
                        if ($mac === null && $macDhcp !== '' && $this->pareceMac($macDhcp)) {
                            $mac = $this->normalizarMac($macDhcp);
                            $macFuente = 'dhcp-server/lease';
                        }
                    }
                } catch (Throwable $e) {
                    Log::debug('[MikroTik] dhcp lease omitido', ['error' => $e->getMessage()]);
                }
            }

            // Tráfico: colas dinámicas PPPoE se llaman <pppoe-USUARIO> (no por IP en target).
            if ($bytesIn === null && $bytesOut === null) {
                $trafico = $this->buscarTraficoCliente($client, $ip, $usuarioPppoe);
                if ($trafico !== null) {
                    $bytesIn = $trafico['bytes_in'];
                    $bytesOut = $trafico['bytes_out'];
                    $traficoFuente = $trafico['fuente'];
                    $detalle['queue'] = $trafico['detalle'];
                }
            }

            $this->disconnect();

            $partesMsg = [];
            if ($mac) {
                $partesMsg[] = 'MAC '.$mac;
            }
            if ($bytesOut !== null || $bytesIn !== null) {
                $partesMsg[] = 'tráfico leído';
            }
            if ($partesMsg === [] && ! $online) {
                return [
                    'success' => true,
                    'message' => 'No se encontró sesión activa ni ARP/DHCP para este cliente en el MikroTik.',
                    'router' => $router->ip,
                    'mac' => null,
                    'mac_fuente' => null,
                    'online' => false,
                    'bytes_in' => null,
                    'bytes_out' => null,
                    'download_humano' => null,
                    'upload_humano' => null,
                    'trafico_fuente' => null,
                    'uptime' => null,
                    'detalle' => $detalle,
                ];
            }

            return [
                'success' => true,
                'message' => $partesMsg !== []
                    ? 'Consulta MikroTik OK: '.implode(', ', $partesMsg).'.'
                    : 'Consulta MikroTik OK.',
                'router' => $router->ip,
                'mac' => $mac,
                'mac_fuente' => $macFuente,
                'online' => $online,
                'bytes_in' => $bytesIn,
                'bytes_out' => $bytesOut,
                // En PPP: bytes-out = hacia el cliente (download), bytes-in = desde el cliente (upload)
                'download_humano' => $bytesOut !== null ? $this->formatoBytes($bytesOut) : null,
                'upload_humano' => $bytesIn !== null ? $this->formatoBytes($bytesIn) : null,
                'trafico_fuente' => $traficoFuente,
                'uptime' => $uptime,
                'detalle' => $detalle,
            ];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('[MikroTik] consultarClienteRed failed', [
                'router' => $router->router_id,
                'ip' => $ip,
                'pppoe' => $usuarioPppoe,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error al consultar MikroTik: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Busca tráfico en queue/simple dinámica (<pppoe-USER>) o interface PPPoE.
     *
     * @return array{bytes_in: ?int, bytes_out: ?int, fuente: string, detalle: array<string, mixed>}|null
     */
    private function buscarTraficoCliente(Client $client, string $ip, string $usuarioPppoe): ?array
    {
        $nombresQueue = [];
        if ($usuarioPppoe !== '') {
            $nombresQueue[] = '<pppoe-'.$usuarioPppoe.'>';
            $nombresQueue[] = 'pppoe-'.$usuarioPppoe;
            $nombresQueue[] = '<'.$usuarioPppoe.'>';
            $nombresQueue[] = $usuarioPppoe;
        }

        try {
            $queues = $client->query(new Query('/queue/simple/print'))->read();
            if (is_array($queues)) {
                foreach ($queues as $q) {
                    $name = (string) ($q['name'] ?? '');
                    $target = (string) ($q['target'] ?? '');
                    $match = false;

                    foreach ($nombresQueue as $candidato) {
                        if ($name === $candidato || strcasecmp($name, $candidato) === 0) {
                            $match = true;
                            break;
                        }
                    }

                    // Match flexible: nombre contiene pppoe-USUARIO o el usuario
                    if (! $match && $usuarioPppoe !== '') {
                        $nameNorm = strtolower($name);
                        $userNorm = strtolower($usuarioPppoe);
                        if (str_contains($nameNorm, 'pppoe-'.$userNorm) || str_contains($nameNorm, '<pppoe-'.$userNorm)) {
                            $match = true;
                        }
                    }

                    if (! $match && $ip !== '' && ($target !== '' && str_contains($target, $ip))) {
                        $match = true;
                    }

                    if (! $match) {
                        continue;
                    }

                    $bytesRaw = (string) ($q['bytes'] ?? '');
                    $bytesIn = null;
                    $bytesOut = null;
                    if (str_contains($bytesRaw, '/')) {
                        // En queue/simple: bytes = upload/download (desde→hacia / hacia→cliente)
                        [$up, $down] = array_pad(explode('/', $bytesRaw, 2), 2, '0');
                        $bytesIn = $this->parseBytes($up);
                        $bytesOut = $this->parseBytes($down);
                    } else {
                        $bytesOut = $this->parseBytes($bytesRaw);
                    }

                    if ($bytesIn === null && $bytesOut === null) {
                        continue;
                    }

                    return [
                        'bytes_in' => $bytesIn,
                        'bytes_out' => $bytesOut,
                        'fuente' => 'queue/simple ('.$name.')',
                        'detalle' => [
                            'name' => $name,
                            'target' => $target !== '' ? $target : null,
                            'bytes' => $bytesRaw,
                            'max-limit' => $q['max-limit'] ?? null,
                            'dynamic' => $q['dynamic'] ?? null,
                        ],
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::debug('[MikroTik] queue simple omitido', ['error' => $e->getMessage()]);
        }

        // Respaldo: contadores de la interface dinámica <pppoe-USER>
        if ($usuarioPppoe !== '') {
            $ifNames = ['<pppoe-'.$usuarioPppoe.'>', 'pppoe-'.$usuarioPppoe];
            try {
                $ifs = $client->query(new Query('/interface/print'))->read();
                if (is_array($ifs)) {
                    foreach ($ifs as $iface) {
                        $ifName = (string) ($iface['name'] ?? '');
                        $ok = false;
                        foreach ($ifNames as $candidato) {
                            if ($ifName === $candidato || strcasecmp($ifName, $candidato) === 0) {
                                $ok = true;
                                break;
                            }
                        }
                        if (! $ok && str_contains(strtolower($ifName), 'pppoe-'.strtolower($usuarioPppoe))) {
                            $ok = true;
                        }
                        if (! $ok) {
                            continue;
                        }

                        // Interface: rx-byte = recibido del cliente (upload), tx-byte = enviado al cliente (download)
                        $bytesIn = $this->parseBytes($iface['rx-byte'] ?? $iface['rx-bytes'] ?? null);
                        $bytesOut = $this->parseBytes($iface['tx-byte'] ?? $iface['tx-bytes'] ?? null);
                        if ($bytesIn === null && $bytesOut === null) {
                            continue;
                        }

                        return [
                            'bytes_in' => $bytesIn,
                            'bytes_out' => $bytesOut,
                            'fuente' => 'interface ('.$ifName.')',
                            'detalle' => [
                                'name' => $ifName,
                                'type' => $iface['type'] ?? null,
                                'rx-byte' => $iface['rx-byte'] ?? null,
                                'tx-byte' => $iface['tx-byte'] ?? null,
                            ],
                        ];
                    }
                }
            } catch (Throwable $e) {
                Log::debug('[MikroTik] interface omitido', ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private function pareceMac(string $value): bool
    {
        return (bool) preg_match('/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/', trim($value));
    }

    private function normalizarMac(string $mac): string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac) ?? '');
        if (strlen($hex) !== 12) {
            return strtoupper(str_replace('-', ':', $mac));
        }

        return implode(':', str_split($hex, 2));
    }

    private function parseBytes(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }
        $s = trim((string) $value);
        if (preg_match('/^(\d+)/', $s, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function formatoBytes(int $bytes): string
    {
        $bytes = max(0, $bytes);
        $unidades = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($unidades) - 1) {
            $n /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $n : number_format($n, 2, '.', '')).' '.$unidades[$i];
    }

    private function construirComandoPppoeSecretAdd(
        string $name,
        string $password,
        string $profile,
        ?string $remoteAddress,
        ?string $localAddress,
        ?string $comment
    ): string {
        $partes = [
            '/ppp secret add',
            'name='.$this->escapeRouterOsValue($name),
            'password='.$this->escapeRouterOsValue($password),
            'service=pppoe',
            'profile='.$this->escapeRouterOsValue($profile),
        ];

        if ($remoteAddress !== null && $remoteAddress !== '') {
            $partes[] = 'remote-address='.$remoteAddress;
        }
        if ($localAddress !== null && $localAddress !== '') {
            $partes[] = 'local-address='.$localAddress;
        }
        if ($comment !== null && $comment !== '') {
            $partes[] = 'comment='.$this->escapeRouterOsValue($comment);
        }

        return implode(' ', $partes);
    }

    /**
     * Lista scripts del router (/system/script).
     *
     * @return list<array<string, mixed>>
     */
    public function getSystemScripts(Router $router): array
    {
        $client = $this->connect($router);
        $query = new Query('/system/script/print');
        // read(false): el parser default de la lib corta valores multilínea (source) en el primer \n
        $raw = $client->query($query)->read(false);
        $this->disconnect();

        return $this->parseApiReply(is_array($raw) ? $raw : []);
    }

    /**
     * Obtiene un script por nombre en el router.
     *
     * @return array<string, mixed>|null
     */
    public function getSystemScriptByName(Router $router, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $client = $this->connect($router);
        $query = (new Query('/system/script/print'))->where('name', $name);
        $raw = $client->query($query)->read(false);
        $this->disconnect();

        $items = $this->parseApiReply(is_array($raw) ? $raw : []);

        return $items[0] ?? null;
    }

    /**
     * Crea o actualiza un script en el router por nombre.
     *
     * @return array{success: bool, action?: string, error?: string}
     */
    public function upsertSystemScript(
        Router $router,
        string $name,
        string $source,
        ?string $policy = null,
        ?string $owner = null,
        bool $dontRequirePermissions = false
    ): array {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Nombre de script vacío'];
        }

        try {
            $existing = $this->getSystemScriptByName($router, $name);
            $client = $this->connect($router);

            if ($existing && ! empty($existing['.id'])) {
                $query = (new Query('/system/script/set'))
                    ->equal('.id', $existing['.id'])
                    ->equal('source', $source)
                    ->equal('dont-require-permissions', $dontRequirePermissions ? 'yes' : 'no');
                if ($policy !== null && $policy !== '') {
                    $query->equal('policy', $policy);
                }
                // owner no se envía: en muchos RouterOS no es seteable por API
                $raw = $client->query($query)->read(false);
                $this->disconnect();
                if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                    return ['success' => false, 'error' => $trap];
                }

                return ['success' => true, 'action' => 'updated'];
            }

            $query = (new Query('/system/script/add'))
                ->equal('name', $name)
                ->equal('source', $source)
                ->equal('dont-require-permissions', $dontRequirePermissions ? 'yes' : 'no');
            if ($policy !== null && $policy !== '') {
                $query->equal('policy', $policy);
            }
            $raw = $client->query($query)->read(false);
            $this->disconnect();
            if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                return ['success' => false, 'error' => $trap];
            }

            return ['success' => true, 'action' => 'added'];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('[MikroTik] upsertSystemScript failed', [
                'router' => $router->router_id,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lee scripts del router y los guarda/actualiza en la BD (por nombre).
     *
     * @param  list<string>|null  $nombres  null = todos
     * @return array{success: bool, imported: int, updated: int, errors: list<string>, scripts: list<RouterScript>}
     */
    public function importSystemScriptsToDatabase(Router $router, ?array $nombres = null): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $scripts = [];

        try {
            $remotos = $this->getSystemScripts($router);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'imported' => 0,
                'updated' => 0,
                'errors' => [$e->getMessage()],
                'scripts' => [],
            ];
        }

        $filtro = null;
        if ($nombres !== null) {
            $filtro = array_fill_keys(array_map(
                static fn ($n) => mb_strtolower(trim((string) $n)),
                $nombres
            ), true);
        }

        foreach ($remotos as $remoto) {
            $nombre = trim((string) ($remoto['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            if ($filtro !== null && ! isset($filtro[mb_strtolower($nombre)])) {
                continue;
            }

            try {
                $source = (string) ($remoto['source'] ?? '');
                $policy = isset($remoto['policy']) ? (string) $remoto['policy'] : null;
                $owner = isset($remoto['owner']) ? (string) $remoto['owner'] : null;
                $dontReq = in_array(
                    strtolower((string) ($remoto['dont-require-permissions'] ?? 'no')),
                    ['yes', 'true', '1'],
                    true
                );

                $existente = RouterScript::where('nombre', $nombre)->first();
                $payload = [
                    'source' => $source,
                    'owner' => $owner,
                    'policy' => $policy,
                    'dont_require_permissions' => $dontReq,
                    'router_origen_id' => $router->router_id,
                    'leido_en' => now(),
                ];

                if ($existente) {
                    $existente->update($payload);
                    $scripts[] = $existente->fresh(['routerOrigen']);
                    $updated++;
                } else {
                    $scripts[] = RouterScript::create(array_merge($payload, ['nombre' => $nombre]))
                        ->load('routerOrigen');
                    $imported++;
                }
            } catch (Throwable $e) {
                $errors[] = $nombre.': '.$e->getMessage();
            }
        }

        return [
            'success' => $errors === [],
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'scripts' => $scripts,
        ];
    }

    /**
     * Escribe un script de la BD en el router destino.
     *
     * @return array{success: bool, action?: string, error?: string}
     */
    public function syncScriptToRouter(RouterScript $script, Router $routerDestino): array
    {
        $result = $this->upsertSystemScript(
            $routerDestino,
            $script->nombre,
            (string) $script->source,
            $script->policy,
            $script->owner,
            (bool) $script->dont_require_permissions
        );

        if ($result['success'] ?? false) {
            $script->update(['sincronizado_en' => now()]);
        }

        return $result;
    }

    /**
     * Lista schedulers del router (/system/scheduler).
     *
     * @return list<array<string, mixed>>
     */
    public function getSystemSchedulers(Router $router): array
    {
        $client = $this->connect($router);
        $query = new Query('/system/scheduler/print');
        $raw = $client->query($query)->read(false);
        $this->disconnect();

        return $this->parseApiReply(is_array($raw) ? $raw : []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSystemSchedulerByName(Router $router, string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $client = $this->connect($router);
        $query = (new Query('/system/scheduler/print'))->where('name', $name);
        $raw = $client->query($query)->read(false);
        $this->disconnect();

        $items = $this->parseApiReply(is_array($raw) ? $raw : []);

        return $items[0] ?? null;
    }

    /**
     * Crea o actualiza un scheduler en el router por nombre.
     *
     * @param  array{
     *   on_event?: ?string,
     *   start_date?: ?string,
     *   start_time?: ?string,
     *   interval?: ?string,
     *   owner?: ?string,
     *   policy?: ?string,
     *   disabled?: bool,
     *   comment?: ?string
     * }  $attrs
     * @return array{success: bool, action?: string, error?: string}
     */
    public function upsertSystemScheduler(Router $router, string $name, array $attrs): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'error' => 'Nombre de scheduler vacío'];
        }

        try {
            $existing = $this->getSystemSchedulerByName($router, $name);
            $client = $this->connect($router);

            $applyEquals = function (Query $query) use ($attrs): void {
                // owner NO se envía: RouterOS responde !trap "unknown parameter owner"
                $map = [
                    'on_event' => 'on-event',
                    'start_date' => 'start-date',
                    'start_time' => 'start-time',
                    'interval' => 'interval',
                    'policy' => 'policy',
                    'comment' => 'comment',
                ];
                foreach ($map as $key => $rosKey) {
                    if (! array_key_exists($key, $attrs)) {
                        continue;
                    }
                    $value = $attrs[$key];
                    if ($value === null || $value === '') {
                        continue;
                    }
                    $query->equal($rosKey, (string) $value);
                }
                if (array_key_exists('disabled', $attrs)) {
                    $query->equal('disabled', ! empty($attrs['disabled']) ? 'yes' : 'no');
                }
            };

            if ($existing && ! empty($existing['.id'])) {
                $query = (new Query('/system/scheduler/set'))->equal('.id', $existing['.id']);
                $applyEquals($query);
                $raw = $client->query($query)->read(false);
                $this->disconnect();
                if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                    return ['success' => false, 'error' => $trap];
                }

                return ['success' => true, 'action' => 'updated'];
            }

            $query = (new Query('/system/scheduler/add'))->equal('name', $name);
            $applyEquals($query);
            $raw = $client->query($query)->read(false);
            $this->disconnect();
            if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                return ['success' => false, 'error' => $trap];
            }

            return ['success' => true, 'action' => 'added'];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('[MikroTik] upsertSystemScheduler failed', [
                'router' => $router->router_id,
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Lee schedulers del router y los guarda/actualiza en la BD (por nombre).
     *
     * @param  list<string>|null  $nombres  null = todos
     * @return array{success: bool, imported: int, updated: int, errors: list<string>, schedulers: list<RouterScheduler>}
     */
    public function importSystemSchedulersToDatabase(Router $router, ?array $nombres = null): array
    {
        $imported = 0;
        $updated = 0;
        $errors = [];
        $schedulers = [];

        try {
            $remotos = $this->getSystemSchedulers($router);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'imported' => 0,
                'updated' => 0,
                'errors' => [$e->getMessage()],
                'schedulers' => [],
            ];
        }

        $filtro = null;
        if ($nombres !== null) {
            $filtro = array_fill_keys(array_map(
                static fn ($n) => mb_strtolower(trim((string) $n)),
                $nombres
            ), true);
        }

        foreach ($remotos as $remoto) {
            $nombre = trim((string) ($remoto['name'] ?? ''));
            if ($nombre === '') {
                continue;
            }
            if ($filtro !== null && ! isset($filtro[mb_strtolower($nombre)])) {
                continue;
            }

            try {
                $disabled = in_array(
                    strtolower((string) ($remoto['disabled'] ?? 'no')),
                    ['yes', 'true', '1'],
                    true
                );

                $payload = [
                    'on_event' => isset($remoto['on-event']) ? (string) $remoto['on-event'] : null,
                    'start_date' => isset($remoto['start-date']) ? (string) $remoto['start-date'] : null,
                    'start_time' => isset($remoto['start-time']) ? (string) $remoto['start-time'] : null,
                    'interval' => isset($remoto['interval']) ? (string) $remoto['interval'] : null,
                    'owner' => isset($remoto['owner']) ? (string) $remoto['owner'] : null,
                    'policy' => isset($remoto['policy']) ? (string) $remoto['policy'] : null,
                    'disabled' => $disabled,
                    'comment' => isset($remoto['comment']) ? (string) $remoto['comment'] : null,
                    'router_origen_id' => $router->router_id,
                    'leido_en' => now(),
                ];

                $existente = RouterScheduler::where('nombre', $nombre)->first();
                if ($existente) {
                    $existente->update($payload);
                    $schedulers[] = $existente->fresh(['routerOrigen']);
                    $updated++;
                } else {
                    $schedulers[] = RouterScheduler::create(array_merge($payload, ['nombre' => $nombre]))
                        ->load('routerOrigen');
                    $imported++;
                }
            } catch (Throwable $e) {
                $errors[] = $nombre.': '.$e->getMessage();
            }
        }

        return [
            'success' => $errors === [],
            'imported' => $imported,
            'updated' => $updated,
            'errors' => $errors,
            'schedulers' => $schedulers,
        ];
    }

    /**
     * Escribe un scheduler de la BD en el router destino.
     *
     * @return array{success: bool, action?: string, error?: string}
     */
    public function syncSchedulerToRouter(RouterScheduler $scheduler, Router $routerDestino): array
    {
        $result = $this->upsertSystemScheduler($routerDestino, $scheduler->nombre, [
            'on_event' => $scheduler->on_event,
            'start_date' => $scheduler->start_date,
            'start_time' => $scheduler->start_time,
            'interval' => $scheduler->interval,
            'owner' => $scheduler->owner,
            'policy' => $scheduler->policy,
            'disabled' => (bool) $scheduler->disabled,
            'comment' => $scheduler->comment,
        ]);

        if ($result['success'] ?? false) {
            $scheduler->update(['sincronizado_en' => now()]);
        }

        return $result;
    }

    /**
     * Ping ICMP desde el router (API /ping).
     * RouterOS 7.23 no admite ping por interface: solo src-address.
     *
     * @return array{ok: bool, latency_ms: int|null, received: int, sent: int, error: string|null, replies: list<array<string, string>>}
     */
    public function pingFromRouter(
        Router $router,
        string $host,
        int $count = 2,
        ?string $srcAddress = null
    ): array {
        $host = trim($host);
        $count = max(1, min(10, $count));
        $empty = [
            'ok' => false,
            'latency_ms' => null,
            'received' => 0,
            'sent' => 0,
            'error' => null,
            'replies' => [],
        ];

        if ($host === '' || ! filter_var($host, FILTER_VALIDATE_IP)) {
            $empty['error'] = 'Host de ping inválido';

            return $empty;
        }

        $src = $srcAddress !== null ? trim($srcAddress) : '';
        if ($src === '' || ! filter_var($src, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $empty['error'] = 'Falta src-address IPv4 (RouterOS 7 no permite ping por interface)';

            return $empty;
        }

        try {
            $client = $this->connect($router);
            $q = (new Query('/ping'))
                ->equal('address', $host)
                ->equal('count', (string) $count)
                ->equal('src-address', $src);

            $raw = $client->query($q)->read(false);
            $this->disconnect();

            $items = $this->parseApiReply(is_array($raw) ? $raw : []);
            if ($items === [] && is_array($raw) && isset($raw[0]) && is_array($raw[0])) {
                $items = $raw;
            }

            $received = 0;
            $sent = 0;
            $latency = null;
            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $status = strtolower(trim((string) ($row['status'] ?? '')));
                if (isset($row['received']) && is_numeric($row['received'])) {
                    $received = max($received, (int) $row['received']);
                }
                if (isset($row['sent']) && is_numeric($row['sent'])) {
                    $sent = max($sent, (int) $row['sent']);
                }
                $timeRaw = (string) ($row['time'] ?? $row['avg-rtt'] ?? '');
                if ($timeRaw !== '' && $status !== 'timeout' && ! str_contains($status, 'unreachable')) {
                    if (preg_match('/(\d+)/', $timeRaw, $m)) {
                        $ms = (int) $m[1];
                        $latency = $latency === null ? $ms : (int) round(($latency + $ms) / 2);
                    }
                    $received = max($received, 1);
                }
            }

            $ok = $received > 0;
            if ($sent === 0) {
                $sent = $count;
            }

            return [
                'ok' => $ok,
                'latency_ms' => $ok ? $latency : null,
                'received' => $received,
                'sent' => $sent,
                'error' => $ok ? null : 'Sin respuesta (timeout)',
                'replies' => $items,
            ];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('[MikroTik] pingFromRouter failed', [
                'router' => $router->router_id,
                'host' => $host,
                'src' => $src,
                'error' => $e->getMessage(),
            ]);

            $empty['error'] = $e->getMessage();

            return $empty;
        }
    }

    /**
     * IPv4 en una interfaz (incluye dinámicas, p.ej. PPPoE). Sin CIDR.
     */
    public function ipv4OnInterface(Router $router, string $interface): ?string
    {
        $want = strtolower(trim($interface));
        if ($want === '') {
            return null;
        }

        foreach ($this->queryApiPrint($router, '/ip/address/print') as $row) {
            if ($this->rosBool($row['disabled'] ?? 'no')) {
                continue;
            }
            $iface = strtolower(trim((string) ($row['interface'] ?? '')));
            if ($iface !== $want) {
                continue;
            }
            $addr = trim((string) ($row['address'] ?? ''));
            if (str_contains($addr, '/')) {
                $addr = explode('/', $addr, 2)[0];
            }
            if (filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $addr;
            }
        }

        return null;
    }

    /**
     * Direcciones IPv4 del router (estáticas y dinámicas).
     *
     * @return list<array{address: string, ip: string, interface: string, disabled: bool, dynamic: bool, comment: string}>
     */
    public function listIpv4Addresses(Router $router): array
    {
        $out = [];
        foreach ($this->queryApiPrint($router, '/ip/address/print') as $row) {
            $cidr = trim((string) ($row['address'] ?? ''));
            if ($cidr === '') {
                continue;
            }
            $ip = str_contains($cidr, '/') ? explode('/', $cidr, 2)[0] : $cidr;
            $out[] = [
                'address' => $cidr,
                'ip' => $ip,
                'interface' => (string) ($row['interface'] ?? ''),
                'disabled' => $this->rosBool($row['disabled'] ?? 'no'),
                'dynamic' => $this->rosBool($row['dynamic'] ?? 'no'),
                'comment' => (string) ($row['comment'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Todas las rutas IPv4 (incluye default 0.0.0.0/0).
     *
     * @return list<array<string, mixed>>
     */
    public function getIpv4Routes(Router $router): array
    {
        return $this->queryApiPrint($router, '/ip/route/print');
    }

    /**
     * Activa o desactiva una ruta IPv4 por .id de RouterOS.
     *
     * @return array{success: bool, error?: string}
     */
    public function setIpv4RouteDisabled(Router $router, string $rosId, bool $disabled): array
    {
        $rosId = trim($rosId);
        if ($rosId === '') {
            return ['success' => false, 'error' => 'Falta .id de la ruta'];
        }

        try {
            $client = $this->connect($router);
            $q = (new Query('/ip/route/set'))
                ->equal('.id', $rosId)
                ->equal('disabled', $disabled ? 'yes' : 'no');
            $raw = $client->query($q)->read(false);
            $this->disconnect();

            if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                return ['success' => false, 'error' => $trap];
            }

            return ['success' => true];
        } catch (Throwable $e) {
            $this->disconnect();
            Log::warning('[MikroTik] setIpv4RouteDisabled failed', [
                'router' => $router->router_id,
                'id' => $rosId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Direcciones IPv4 estáticas (/ip/address, sin dynamic).
     *
     * @return list<array<string, mixed>>
     */
    public function getStaticIpv4Addresses(Router $router): array
    {
        return $this->filterStaticAddresses(
            $this->queryApiPrint($router, '/ip/address/print'),
            'ipv4'
        );
    }

    /**
     * Direcciones IPv6 estáticas (/ipv6/address, sin dynamic).
     *
     * @return list<array<string, mixed>>
     */
    public function getStaticIpv6Addresses(Router $router): array
    {
        return $this->filterStaticAddresses(
            $this->queryApiPrint($router, '/ipv6/address/print'),
            'ipv6'
        );
    }

    /**
     * Rutas IPv4 estáticas (/ip/route).
     *
     * @return list<array<string, mixed>>
     */
    public function getStaticIpv4Routes(Router $router): array
    {
        return $this->filterStaticRoutes(
            $this->queryApiPrint($router, '/ip/route/print'),
            'ipv4'
        );
    }

    /**
     * Rutas IPv6 estáticas (/ipv6/route).
     *
     * @return list<array<string, mixed>>
     */
    public function getStaticIpv6Routes(Router $router): array
    {
        return $this->filterStaticRoutes(
            $this->queryApiPrint($router, '/ipv6/route/print'),
            'ipv6'
        );
    }

    /**
     * Lee IPs/rutas estáticas del router y guarda un backup en BD.
     *
     * @return array{success: bool, backup?: RouterNetworkBackup, error?: string}
     */
    public function importNetworkBackupFromRouter(Router $router, ?string $nombre = null, ?string $notas = null): array
    {
        try {
            $ipv4 = $this->getStaticIpv4Addresses($router);
            $ipv6 = $this->getStaticIpv6Addresses($router);
            $rutasV4 = $this->getStaticIpv4Routes($router);
            $rutasV6 = $this->getStaticIpv6Routes($router);
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        try {
            $backup = DB::transaction(function () use ($router, $nombre, $notas, $ipv4, $ipv6, $rutasV4, $rutasV6) {
                $backup = RouterNetworkBackup::create([
                    'router_origen_id' => $router->router_id,
                    'nombre' => $nombre ?: ('Backup '.$router->nombre.' '.now()->format('Y-m-d H:i')),
                    'notas' => $notas,
                    'cant_ipv4' => count($ipv4),
                    'cant_ipv6' => count($ipv6),
                    'cant_rutas_v4' => count($rutasV4),
                    'cant_rutas_v6' => count($rutasV6),
                    'leido_en' => now(),
                ]);

                foreach (array_merge(
                    array_map(fn ($a) => ['familia' => 'ipv4', 'row' => $a], $ipv4),
                    array_map(fn ($a) => ['familia' => 'ipv6', 'row' => $a], $ipv6),
                ) as $item) {
                    $row = $item['row'];
                    RouterNetworkBackupAddress::create([
                        'router_network_backup_id' => $backup->router_network_backup_id,
                        'familia' => $item['familia'],
                        'address' => (string) ($row['address'] ?? ''),
                        'network' => isset($row['network']) ? (string) $row['network'] : null,
                        'interface' => isset($row['interface']) ? (string) $row['interface'] : null,
                        'disabled' => $this->rosBool($row['disabled'] ?? 'no'),
                        'comment' => isset($row['comment']) ? (string) $row['comment'] : null,
                        'extra' => $this->networkExtra($row, ['address', 'network', 'interface', 'disabled', 'comment', '.id', '.nextid']),
                    ]);
                }

                foreach (array_merge(
                    array_map(fn ($a) => ['familia' => 'ipv4', 'row' => $a], $rutasV4),
                    array_map(fn ($a) => ['familia' => 'ipv6', 'row' => $a], $rutasV6),
                ) as $item) {
                    $row = $item['row'];
                    RouterNetworkBackupRoute::create([
                        'router_network_backup_id' => $backup->router_network_backup_id,
                        'familia' => $item['familia'],
                        'dst_address' => (string) ($row['dst-address'] ?? ''),
                        'gateway' => isset($row['gateway']) ? (string) $row['gateway'] : null,
                        'distance' => isset($row['distance']) && is_numeric($row['distance']) ? (int) $row['distance'] : null,
                        'routing_table' => isset($row['routing-table']) ? (string) $row['routing-table'] : null,
                        'scope' => isset($row['scope']) ? (string) $row['scope'] : null,
                        'target_scope' => isset($row['target-scope']) ? (string) $row['target-scope'] : null,
                        'pref_src' => isset($row['pref-src']) ? (string) $row['pref-src'] : null,
                        'check_gateway' => isset($row['check-gateway']) ? (string) $row['check-gateway'] : null,
                        'disabled' => $this->rosBool($row['disabled'] ?? 'no'),
                        'comment' => isset($row['comment']) ? (string) $row['comment'] : null,
                        'extra' => $this->networkExtra($row, [
                            'dst-address', 'gateway', 'distance', 'routing-table', 'scope', 'target-scope',
                            'pref-src', 'check-gateway', 'disabled', 'comment', '.id', '.nextid',
                            'dynamic', 'static', 'connect', 'active', 'ecmp',
                        ]),
                    ]);
                }

                return $backup->load(['routerOrigen', 'addresses', 'routes']);
            });
        } catch (Throwable $e) {
            Log::warning('[MikroTik] importNetworkBackupFromRouter failed', [
                'router' => $router->router_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'backup' => $backup];
    }

    /**
     * Restaura un backup de red en el router destino (solo estáticas).
     *
     * @return array{success: bool, added_addresses: int, added_routes: int, errors: list<string>}
     */
    public function syncNetworkBackupToRouter(RouterNetworkBackup $backup, Router $routerDestino): array
    {
        $backup->loadMissing(['addresses', 'routes']);
        $addedAddresses = 0;
        $addedRoutes = 0;
        $errors = [];

        try {
            $client = $this->connect($routerDestino);

            foreach ($backup->addresses as $addr) {
                if (trim((string) $addr->address) === '' || trim((string) $addr->interface) === '') {
                    continue;
                }
                $path = $addr->familia === 'ipv6' ? '/ipv6/address/add' : '/ip/address/add';
                try {
                    $q = (new Query($path))
                        ->equal('address', (string) $addr->address)
                        ->equal('interface', (string) $addr->interface)
                        ->equal('disabled', $addr->disabled ? 'yes' : 'no');
                    if ($addr->familia === 'ipv4' && $addr->network) {
                        $q->equal('network', (string) $addr->network);
                    }
                    if ($addr->comment) {
                        $q->equal('comment', (string) $addr->comment);
                    }
                    $raw = $client->query($q)->read(false);
                    if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                        // Si ya existe, no es error fatal
                        if (! str_contains(mb_strtolower($trap), 'already have') && ! str_contains(mb_strtolower($trap), 'exists')) {
                            $errors[] = "IP {$addr->address}: {$trap}";
                            continue;
                        }
                    }
                    $addedAddresses++;
                } catch (Throwable $e) {
                    $errors[] = "IP {$addr->address}: ".$e->getMessage();
                }
            }

            foreach ($backup->routes as $route) {
                if (trim((string) $route->dst_address) === '') {
                    continue;
                }
                $path = $route->familia === 'ipv6' ? '/ipv6/route/add' : '/ip/route/add';
                try {
                    $q = (new Query($path))
                        ->equal('dst-address', (string) $route->dst_address)
                        ->equal('disabled', $route->disabled ? 'yes' : 'no');
                    if ($route->gateway) {
                        $q->equal('gateway', (string) $route->gateway);
                    }
                    if ($route->distance !== null) {
                        $q->equal('distance', (string) $route->distance);
                    }
                    if ($route->routing_table && $route->routing_table !== 'main') {
                        $q->equal('routing-table', (string) $route->routing_table);
                    }
                    if ($route->scope) {
                        $q->equal('scope', (string) $route->scope);
                    }
                    if ($route->target_scope) {
                        $q->equal('target-scope', (string) $route->target_scope);
                    }
                    if ($route->pref_src) {
                        $q->equal('pref-src', (string) $route->pref_src);
                    }
                    if ($route->check_gateway) {
                        $q->equal('check-gateway', (string) $route->check_gateway);
                    }
                    if ($route->comment) {
                        $q->equal('comment', (string) $route->comment);
                    }
                    $raw = $client->query($q)->read(false);
                    if ($trap = $this->apiTrapMessage(is_array($raw) ? $raw : [])) {
                        if (! str_contains(mb_strtolower($trap), 'already have') && ! str_contains(mb_strtolower($trap), 'exists')) {
                            $errors[] = "Ruta {$route->dst_address}: {$trap}";
                            continue;
                        }
                    }
                    $addedRoutes++;
                } catch (Throwable $e) {
                    $errors[] = "Ruta {$route->dst_address}: ".$e->getMessage();
                }
            }

            $this->disconnect();
        } catch (Throwable $e) {
            $this->disconnect();

            return [
                'success' => false,
                'added_addresses' => $addedAddresses,
                'added_routes' => $addedRoutes,
                'errors' => array_merge($errors, [$e->getMessage()]),
            ];
        }

        if ($errors === [] || ($addedAddresses + $addedRoutes) > 0) {
            $backup->update(['sincronizado_en' => now()]);
        }

        return [
            'success' => $errors === [],
            'added_addresses' => $addedAddresses,
            'added_routes' => $addedRoutes,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private function queryApiPrint(Router $router, string $path): array
    {
        $client = $this->connect($router);
        $raw = $client->query(new Query($path))->read(false);
        $this->disconnect();

        return $this->parseApiReply(is_array($raw) ? $raw : []);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterStaticAddresses(array $rows, string $familia): array
    {
        $out = [];
        foreach ($rows as $row) {
            if ($this->rosBool($row['dynamic'] ?? 'no')) {
                continue;
            }
            $address = trim((string) ($row['address'] ?? ''));
            if ($address === '') {
                continue;
            }
            $row['_familia'] = $familia;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function filterStaticRoutes(array $rows, string $familia): array
    {
        $out = [];
        foreach ($rows as $row) {
            $isStatic = $this->rosBool($row['static'] ?? 'no');
            $isDynamic = $this->rosBool($row['dynamic'] ?? 'no');
            $isConnect = $this->rosBool($row['connect'] ?? 'no');

            // Preferir flag static; si no viene, excluir dynamic/connect
            if ($isStatic) {
                // ok
            } elseif ($isDynamic || $isConnect) {
                continue;
            } else {
                // sin flags claros: solo si tiene gateway (ruta manual típica)
                if (trim((string) ($row['gateway'] ?? '')) === '') {
                    continue;
                }
            }

            $dst = trim((string) ($row['dst-address'] ?? ''));
            if ($dst === '') {
                continue;
            }
            $row['_familia'] = $familia;
            $out[] = $row;
        }

        return $out;
    }

    private function rosBool(mixed $value): bool
    {
        return in_array(strtolower((string) $value), ['yes', 'true', '1'], true);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $exclude
     * @return array<string, mixed>|null
     */
    private function networkExtra(array $row, array $exclude): ?array
    {
        $extra = [];
        foreach ($row as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            if (in_array($key, $exclude, true)) {
                continue;
            }
            $extra[$key] = $value;
        }

        return $extra === [] ? null : $extra;
    }

    /**
     * Extrae mensaje de !trap de una respuesta cruda del API.
     *
     * @param  list<string>  $raw
     */
    private function apiTrapMessage(array $raw): ?string
    {
        $inTrap = false;
        $message = null;
        $category = null;

        foreach ($raw as $word) {
            if ($word === '!trap') {
                $inTrap = true;
                continue;
            }
            if ($word === '!done' || $word === '!re' || $word === '!fatal' || $word === '') {
                if ($inTrap && ($message !== null || $category !== null)) {
                    break;
                }
                $inTrap = false;
                continue;
            }
            if (! $inTrap) {
                continue;
            }
            if (str_starts_with($word, '=message=')) {
                $message = substr($word, strlen('=message='));
            } elseif (str_starts_with($word, '=category=')) {
                $category = substr($word, strlen('=category='));
            }
        }

        if ($message === null && $category === null) {
            return null;
        }

        return $message ?: ('API trap'.($category !== null ? " (category {$category})" : ''));
    }

    /**
     * Parsea respuesta cruda del API RouterOS preservando valores multilínea
     * (p.ej. =source=... con saltos de línea). El parser de routeros-api-php
     * usa (.*) sin flag s y trunca en el primer \\n.
     *
     * @param  list<string>  $raw
     * @return list<array<string, string>>
     */
    private function parseApiReply(array $raw): array
    {
        $items = [];
        $current = null;

        foreach ($raw as $word) {
            if ($word === '!re') {
                if (is_array($current)) {
                    $items[] = $current;
                }
                $current = [];
                continue;
            }

            if ($word === '!done' || $word === '!fatal' || $word === '!trap') {
                if (is_array($current)) {
                    $items[] = $current;
                    $current = null;
                }
                continue;
            }

            if ($word === '' || ! is_array($current)) {
                continue;
            }

            // /s: el punto también coincide con saltos de línea dentro del valor
            if (preg_match('/^([=.])([.\w-]+)=(.*)$/s', $word, $m)) {
                $current[$m[2]] = $m[3];
            }
        }

        if (is_array($current)) {
            $items[] = $current;
        }

        return $items;
    }

    private function escapeRouterOsValue(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"'.$escaped.'"';
    }
}
