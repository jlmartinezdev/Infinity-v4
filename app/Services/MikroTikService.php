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

        $servicios = $this->serviciosPppoeActivosDelRouter($router);
        $usernamesFromDb = [];

        Log::info('[MikroTik] syncPppoeFromDatabase: iniciando', [
            'router' => $router->ip,
            'router_id' => $router->router_id,
            'servicios_count' => $servicios->count(),
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
                : 'No hay servicios activos con usuario PPPoE en los pools de este router.';
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
                $attrs = ['profile' => $profileName, 'service' => 'pppoe', 'disabled' => 'no'];
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
                $this->addPppoeSecret($router, $servicio->usuario_pppoe, $password, $remoteAddress, $profileName, $localAddress, $nombreCliente ?: null);
            }
            if ($actualizarEstadoServicio) {
                $servicio->update(['pppoe_synced' => now(), 'pppoe_status' => 'synced']);
            }
            Log::info('[MikroTik] syncPppoeServicio: completado OK', ['router' => $router->ip, 'usuario' => $servicio->usuario_pppoe]);

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

            return ['success' => false, 'error' => 'Usuario no encontrado en el router.'];
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
        $poolIds ??= $router->routerIpPools()
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->pluck('pool_id')
            ->all();

        if ($poolIds === []) {
            return collect();
        }

        return Servicio::with(['plan.perfilPppoe', 'pool', 'cliente'])
            ->whereIn('pool_id', $poolIds)
            ->where('estado', Servicio::ESTADO_ACTIVO)
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

    private function escapeRouterOsValue(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"'.$escaped.'"';
    }
}
