<?php

namespace App\Services;

use App\Models\MikrotikOperacionPendiente;
use App\Models\PerfilPppoe;
use App\Models\Router;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
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
        $port = $router->api_port ?: config('mikrotik.port', 8728);

        Log::info('[MikroTik] Conectando', ['router' => $router->ip, 'port' => $port]);

        $config = new Config([
            'host' => $router->ip,
            'user' => $router->usuario,
            'pass' => $router->password ?? '',
            'port' => $port,
            'timeout' => $this->timeout,
            'socket_timeout' => $this->socketTimeout,
            'ssl' => $this->ssl,
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
     * Lista los secretos PPPoE en el router (/ppp/secret con service=pppoe).
     */
    public function getPppoeSecrets(Router $router): array
    {
        Log::info('[MikroTik] getPppoeSecrets: iniciando', ['router' => $router->ip]);
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/print'))->where('service', 'pppoe');
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
        $query = (new Query('/ppp/secret/print'))
            ->where('service', 'pppoe')
            ->where('name', $name);
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
    public function addPppoeSecret(Router $router, string $name, string $password, ?string $remoteAddress = null, ?string $profile = null, ?string $localAddress = null, ?string $comment = null): array
    {
        Log::info('[MikroTik] addPppoeSecret: iniciando', ['router' => $router->ip, 'name' => $name]);
        $client = $this->connect($router);
        $query = (new Query('/ppp/secret/add'))
            ->equal('name', $name)
            ->equal('password', $password)
            ->equal('service', 'pppoe');
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
     * del router (por pool) se añaden o actualizan; usuarios que ya no están en BD
     * se pueden eliminar opcionalmente.
     */
    public function syncPppoeFromDatabase(Router $router, bool $removeOrphans = false): array
    {
        $added = 0;
        $updated = 0;
        $removed = 0;
        $errors = [];

        $poolIds = $router->routerIpPools()->pluck('pool_id')->all();
        $servicios = Servicio::with(['plan.perfilPppoe', 'pool', 'cliente'])
            ->whereIn('pool_id', $poolIds)
            ->where('estado', 'A')
            ->whereNotNull('usuario_pppoe')
            ->where('usuario_pppoe', '!=', '')
            ->get();

        Log::info('[MikroTik] syncPppoeFromDatabase: iniciando', [
            'router' => $router->ip,
            'servicios_count' => $servicios->count(),
        ]);

        $usernamesFromDb = [];
        $secrets = $this->getPppoeSecrets($router);
        $secretsByName = [];
        foreach ($secrets as $s) {
            $name = $s['name'] ?? null;
            if ($name) {
                $secretsByName[$name] = $s;
            }
        }

        $localAddress = $router->ip_loopback ?: null;

        foreach ($servicios as $servicio) {
            $usernamesFromDb[] = $servicio->usuario_pppoe;
            $profileName = $servicio->plan?->perfilPppoe?->nombre ?? $servicio->plan?->nombre ?? 'default';
            $password = $servicio->password_pppoe ?? '';
            $remoteAddress = $servicio->ip ?: null;
            $nombreCliente = trim(($servicio->cliente?->nombre ?? '') . ' ' . ($servicio->cliente?->apellido ?? ''));
            $existing = $secretsByName[$servicio->usuario_pppoe] ?? null;

            try {
                if ($existing) {
                    $attrs = [];
                    if ($localAddress !== null && $localAddress !== '') {
                        $attrs['local-address'] = $localAddress;
                    }
                    if ($remoteAddress !== null) {
                        $attrs['remote-address'] = $remoteAddress;
                    }
                    $attrs['profile'] = $profileName;
                    if ($password !== '') {
                        $attrs['password'] = $password;
                    }
                    if ($nombreCliente !== '') {
                        $attrs['comment'] = $nombreCliente;
                    }
                    if (! empty($attrs)) {
                        $this->setPppoeSecret($router, $existing['.id'], $attrs);
                        $updated++;
                    }
                    $servicio->update(['pppoe_synced' => now(), 'pppoe_status' => 'synced']);
                } else {
                    $this->addPppoeSecret($router, $servicio->usuario_pppoe, $password, $remoteAddress, $profileName, $localAddress, $nombreCliente ?: null);
                    $added++;
                    $servicio->update(['pppoe_synced' => now(), 'pppoe_status' => 'synced']);
                }
            } catch (Throwable $e) {
                $errors[] = $servicio->usuario_pppoe . ': ' . $e->getMessage();
                Log::error('[MikroTik] syncPppoeFromDatabase: error por servicio', [
                    'router' => $router->ip,
                    'servicio' => $servicio->usuario_pppoe,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($removeOrphans) {
            $secrets = $this->getPppoeSecrets($router);
            foreach ($secrets as $s) {
                $name = $s['name'] ?? null;
                if ($name && ! in_array($name, $usernamesFromDb, true)) {
                    try {
                        $this->removePppoeSecret($router, $s['.id']);
                        $removed++;
                    } catch (Throwable $e) {
                        $errors[] = "remove {$name}: " . $e->getMessage();
                    }
                }
            }
        }

        return [
            'success' => empty($errors),
            'added' => $added,
            'updated' => $updated,
            'removed' => $removed,
            'errors' => $errors,
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
        $router = $servicio->pool->router;
        $profileName = $servicio->plan?->perfilPppoe?->nombre ?? $servicio->plan?->nombre ?? 'default';
        $password = $servicio->password_pppoe ?? '';
        $remoteAddress = $servicio->ip ?: null;
        $localAddress = $router->ip_loopback ?: null;
        $nombreCliente = trim(($servicio->cliente?->nombre ?? '') . ' ' . ($servicio->cliente?->apellido ?? ''));

        Log::info('[MikroTik] syncPppoeServicio: iniciando', ['router' => $router->ip, 'usuario' => $servicio->usuario_pppoe]);

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
                $attrs = ['profile' => $profileName];
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
                $attrs['disabled'] = 'no';
                $this->setPppoeSecret($router, $existing['.id'], $attrs);
            } else {
                $this->addPppoeSecret($router, $servicio->usuario_pppoe, $password, $remoteAddress, $profileName, $localAddress, $nombreCliente ?: null);
            }
            $servicio->update(['pppoe_synced' => now(), 'pppoe_status' => 'synced']);
            Log::info('[MikroTik] syncPppoeServicio: completado OK', ['router' => $router->ip, 'usuario' => $servicio->usuario_pppoe]);
            return ['success' => true];
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
     */
    public function setPppoeDisabledEnRouter(Servicio $servicio, bool $disabled): array
    {
        if (! $servicio->usuario_pppoe || ! $servicio->pool?->router) {
            return ['success' => false, 'error' => 'Servicio sin usuario PPPoE o sin router asociado.'];
        }
        $router = $servicio->pool->router;
        $existing = $this->getPppoeSecretByName($router, $servicio->usuario_pppoe);
        if ($existing && isset($existing['.id'])) {
            try {
                $this->setPppoeSecret($router, $existing['.id'], ['disabled' => $disabled ? 'yes' : 'no']);
                return ['success' => true];
            } catch (Throwable $e) {
                Log::error('[MikroTik] setPppoeDisabled error', ['servicio' => $servicio->usuario_pppoe, 'error' => $e->getMessage()]);
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        return ['success' => false, 'error' => 'Usuario no encontrado en el router.'];
    }
}
