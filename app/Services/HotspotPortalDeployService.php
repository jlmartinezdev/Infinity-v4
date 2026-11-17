<?php

namespace App\Services;

use App\Models\Router;
use App\Support\HotspotLoginBy;
use Illuminate\Support\Facades\Log;
use RouterOS\Query;
use Throwable;

class HotspotPortalDeployService
{
    public function __construct(private readonly MikroTikService $mikrotik) {}

    /**
     * @return array{success: bool, subidos?: int, error?: string, archivos?: list<string>}
     */
    public function desplegar(Router $router, ?string $origen = null, string $trialUptime = '30m'): array
    {
        $origen = $origen ?: base_path('hotspot');
        if (! is_dir($origen)) {
            return ['success' => false, 'error' => 'No está la carpeta hotspot/ en el proyecto.'];
        }

        $archivos = $this->archivosASubir($origen);
        if ($archivos === []) {
            return ['success' => false, 'error' => 'La carpeta hotspot/ no tiene archivos del portal.'];
        }

        $permitidos = trim((string) (config('radius.host') ?: '10.200.1.2'));
        if ($permitidos !== '' && ! str_contains($permitidos, '/')) {
            $permitidos .= '/32';
        }

        $client = $this->mikrotik->connect($router);
        $ftpHabilitado = false;

        try {
            $this->setFtp($client, true, $permitidos);
            $ftpHabilitado = true;
            usleep(400000);

            $subidos = [];
            foreach ($archivos as $local => $remoto) {
                $this->subirPorFtp($router, $local, $remoto);
                $subidos[] = $remoto;
            }

            $this->aplicarPerfilPortal($client, $trialUptime);
            $api = $this->subirApiVenta($router);
            $jardin = $this->abrirJardinVenta($client);

            return [
                'success' => true,
                'subidos' => count($subidos),
                'archivos' => $subidos,
                'jardin' => trim($api.' '.$jardin),
            ];
        } catch (Throwable $e) {
            Log::error('[Hotspot] no se pudo cargar el portal cautivo', [
                'router' => $router->router_id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            if ($ftpHabilitado) {
                try {
                    $this->setFtp($client, false, '');
                } catch (Throwable $e) {
                    Log::warning('[Hotspot] no se pudo volver a apagar FTP', [
                        'router' => $router->router_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            $this->mikrotik->disconnect();
        }
    }

    /**
     * @return array<string, string> local path => remote path
     */
    protected function archivosASubir(string $origen): array
    {
        $origen = rtrim(str_replace('\\', '/', $origen), '/');
        $omitirNombre = [
            'clientes_db.json' => true,
        ];
        $out = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($origen, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            $full = str_replace('\\', '/', $file->getPathname());
            $rel = ltrim(substr($full, strlen($origen)), '/');
            $parts = explode('/', strtolower($rel));
            if (in_array('flash', $parts, true)) {
                continue;
            }
            $base = strtolower($file->getFilename());
            if (isset($omitirNombre[$base])) {
                continue;
            }
            $out[$file->getPathname()] = 'hotspot/'.$rel;
        }

        return $out;
    }

    protected function subirPorFtp(Router $router, string $local, string $remoto): void
    {
        $fp = fopen($local, 'rb');
        if ($fp === false) {
            throw new \RuntimeException('No se pudo leer '.$local);
        }

        $url = 'ftp://'.$router->ip.'/'.ltrim(str_replace('\\', '/', $remoto), '/');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => filesize($local),
            CURLOPT_USERPWD => $router->usuario.':'.($router->password ?? ''),
            CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR_RETRY,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_FTP_USE_EPSV => false,
        ]);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        fclose($fp);

        if ($ok === false) {
            throw new \RuntimeException('FTP '.$remoto.': '.($err !== '' ? $err : 'falló (HTTP '.$code.')'));
        }
    }

    protected function setFtp($client, bool $habilitar, string $address): void
    {
        $servicios = $client->query(new Query('/ip/service/print'))->read();
        foreach (is_array($servicios) ? $servicios : [] as $servicio) {
            if (! is_array($servicio) || ($servicio['name'] ?? '') !== 'ftp' || empty($servicio['.id'])) {
                continue;
            }
            $set = (new Query('/ip/service/set'))
                ->equal('.id', (string) $servicio['.id'])
                ->equal('disabled', $habilitar ? 'no' : 'yes')
                ->equal('address', $habilitar ? $address : '');
            $client->query($set)->read();

            return;
        }

        throw new \RuntimeException('El router no tiene el servicio FTP.');
    }

    /**
     * Hosts que el cliente puede abrir sin sesión: la API y el checkout de TPago.
     *
     * @return list<string>
     */
    public static function hostsJardinVenta(): array
    {
        $hosts = config('hotspot.venta_jardin_hosts', []);
        if (! is_array($hosts)) {
            $hosts = [];
        }
        $api = parse_url((string) config('hotspot.venta_api_url'), PHP_URL_HOST);
        if (is_string($api) && $api !== '') {
            $hosts[] = $api;
        }

        $out = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim((string) $host));
            if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
                continue;
            }
            $out[$host] = $host;
        }

        return array_values($out);
    }

    protected function subirApiVenta(Router $router): string
    {
        $url = rtrim((string) config('hotspot.venta_api_url'), '/');
        $js = 'window.HOTSPOT_VENTA_API='.json_encode($url, JSON_UNESCAPED_SLASHES).";\n";
        $tmp = tempnam(sys_get_temp_dir(), 'gigas');
        if ($tmp === false) {
            return 'No se pudo preparar la dirección de venta.';
        }

        try {
            file_put_contents($tmp, $js);
            $this->subirPorFtp($router, $tmp, 'hotspot/gigas-api.js');

            return $url !== '' ? 'Venta apuntando a '.$url.'.' : 'Venta sin URL de la API.';
        } catch (Throwable $e) {
            Log::warning('[Hotspot] no se pudo publicar gigas-api.js', [
                'router' => $router->router_id,
                'error' => $e->getMessage(),
            ]);

            return 'No se publicó la dirección de venta.';
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @param  mixed  $client
     */
    protected function abrirJardinVenta($client): string
    {
        $hosts = self::hostsJardinVenta();
        if ($hosts === []) {
            return 'Sin hosts para el pago.';
        }

        try {
            $http = $this->hostsYaAbiertos($client, '/ip/hotspot/walled-garden/print', 'dst-host');
            $ip = $this->hostsYaAbiertos($client, '/ip/hotspot/walled-garden/ip/print', 'dst-host');
            $ipAddress = $this->hostsYaAbiertos($client, '/ip/hotspot/walled-garden/ip/print', 'dst-address');

            foreach ($hosts as $host) {
                try {
                    $esIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
                    $esComodin = str_contains($host, '*');
                    if (! $esIp && ! isset($http[$host])) {
                        $this->agregarJardin($client, '/ip/hotspot/walled-garden/add', 'dst-host', $host, false);
                    }
                    if ($esIp && ! isset($ipAddress[$host])) {
                        $this->agregarJardin($client, '/ip/hotspot/walled-garden/ip/add', 'dst-address', $host, true);
                    } elseif (! $esIp && ! $esComodin && ! isset($ip[$host])) {
                        $this->agregarJardin($client, '/ip/hotspot/walled-garden/ip/add', 'dst-host', $host, true);
                    }
                } catch (Throwable $e) {
                    Log::warning('[Hotspot] host de pago no agregado', [
                        'host' => $host,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return 'Acceso de pago abierto.';
        } catch (Throwable $e) {
            Log::warning('[Hotspot] no se pudo abrir el acceso a TPago', [
                'error' => $e->getMessage(),
            ]);

            return 'No se pudo abrir el acceso a TPago.';
        }
    }

    /**
     * @return array<string, true>
     */
    protected function hostsYaAbiertos($client, string $path, string $campo): array
    {
        $filas = $client->query(new Query($path))->read();
        $out = [];
        foreach (is_array($filas) ? $filas : [] as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $valor = strtolower(trim((string) ($fila[$campo] ?? '')));
            if ($valor !== '') {
                $out[$valor] = true;
            }
        }

        return $out;
    }

    protected function agregarJardin($client, string $path, string $campo, string $host, bool $conAccion): void
    {
        $add = (new Query($path))
            ->equal($campo, $host)
            ->equal('comment', 'Infinity venta gigas');
        if ($conAccion) {
            $add->equal('action', 'accept');
        }
        $client->query($add)->read();
    }

    protected function aplicarPerfilPortal($client, string $trialUptime): void
    {
        $perfiles = $client->query(new Query('/ip/hotspot/profile/print'))->read();
        foreach (is_array($perfiles) ? $perfiles : [] as $perfil) {
            if (! is_array($perfil) || empty($perfil['.id'])) {
                continue;
            }
            $nombre = (string) ($perfil['name'] ?? '');
            $set = (new Query('/ip/hotspot/profile/set'))
                ->equal('.id', (string) $perfil['.id'])
                ->equal('html-directory', 'hotspot');
            $this->ejecutarSet($client, $set, $nombre);

            $loginActual = (string) ($perfil['login-by'] ?? 'http-chap');
            if ($nombre !== 'default' && $trialUptime !== '') {
                $metodos = array_values(array_filter(array_map(
                    static fn (string $metodo) => trim($metodo),
                    explode(',', $loginActual)
                ), static fn (string $metodo) => $metodo !== ''));
                if (! in_array('trial', $metodos, true)) {
                    $metodos[] = 'trial';
                }
                $loginActual = implode(',', $metodos);
                $this->ejecutarSet($client, (new Query('/ip/hotspot/profile/set'))
                    ->equal('.id', (string) $perfil['.id'])
                    ->equal('trial-uptime-limit', $trialUptime)
                    ->equal('trial-uptime-reset', '3d'), $nombre);
            }

            $this->ejecutarSet($client, (new Query('/ip/hotspot/profile/set'))
                ->equal('.id', (string) $perfil['.id'])
                ->equal('login-by', HotspotLoginBy::paraRoaming($loginActual)), $nombre);
        }
    }

    protected function ejecutarSet($client, Query $set, string $nombre): void
    {
        $res = $client->query($set)->read();
        foreach (is_array($res) ? $res : [] as $fila) {
            if (is_array($fila) && isset($fila['message'])) {
                throw new \RuntimeException('Perfil '.$nombre.': '.$fila['message']);
            }
        }
    }
}
