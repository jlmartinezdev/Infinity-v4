<?php

namespace App\Services\Sifen;

use App\Models\SifenConfiguracion;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SifenCertificadoService
{
    /**
     * @return array{cert: string, pkey: string, x509_clean: string}
     */
    public function cargarDesdeP12(?string $path = null, ?string $password = null): array
    {
        $path = $path ?: config('sifen.certificado.path');
        $password = $password ?? $this->resolverPassword();

        if (! $path || ! File::exists($path)) {
            throw new RuntimeException('Certificado SIFEN no encontrado: '.($path ?: '(sin ruta)'));
        }

        if ($password === null || $password === '') {
            throw new RuntimeException('Configure SIFEN_CERT_PASSWORD en el archivo .env');
        }

        $contenido = File::get($path);
        $certs = [];
        if (! openssl_pkcs12_read($contenido, $certs, $password)) {
            throw new RuntimeException('No se pudo leer el certificado P12. Verifique la contraseña.');
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new RuntimeException('El archivo P12 no contiene certificado o clave privada.');
        }

        $cert = $this->resolverCertificadoParaClave($certs, $password);

        $x509Clean = preg_replace(
            '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
            '',
            $cert
        ) ?? '';

        return [
            'cert' => $cert,
            'pkey' => $certs['pkey'],
            'x509_clean' => $x509Clean,
        ];
    }

    /**
     * PEM para autenticación mTLS con SIFEN (clave exportada sin cifrar).
     *
     * @return array{
     *   cert_chain: string,
     *   key: string,
     *   combined: string,
     *   p12_path: string,
     *   key_passphrase: ?string,
     * }
     */
    public function exportarMaterialTls(?string $path = null, ?string $password = null): array
    {
        $path = $path ?: config('sifen.certificado.path');
        $password = $password ?? $this->resolverPassword();

        if (! $path || ! File::exists($path)) {
            throw new RuntimeException('Certificado SIFEN no encontrado: '.($path ?: '(sin ruta)'));
        }

        if ($password === null || $password === '') {
            throw new RuntimeException('Configure la contraseña del certificado P12.');
        }

        $contenido = File::get($path);
        $certs = [];
        if (! openssl_pkcs12_read($contenido, $certs, $password)) {
            throw new RuntimeException('No se pudo leer el certificado P12. Verifique la contraseña.');
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new RuntimeException('El archivo P12 no contiene certificado o clave privada.');
        }

        $leaf = $this->resolverCertificadoParaClave($certs, $password);
        $chain = trim($leaf);

        if (! empty($certs['extracerts']) && is_array($certs['extracerts'])) {
            foreach ($certs['extracerts'] as $extra) {
                if (! is_string($extra) || trim($extra) === '' || str_contains($chain, trim($extra))) {
                    continue;
                }
                $chain .= "\n".trim($extra);
            }
        }

        [$keyPem, $keyPassphrase] = $this->resolverClaveTlsPem($certs, $password);
        $p12RealPath = realpath($path);

        return [
            'cert_chain' => $chain,
            'key' => $keyPem,
            'combined' => $chain."\n".$keyPem,
            'p12_path' => $p12RealPath !== false ? $p12RealPath : $path,
            'key_passphrase' => $keyPassphrase,
        ];
    }

    /**
     * Exporta cert+key sin cifrar vía openssl pkcs12 -nodes (más fiable en Windows).
     */
    public function exportarPemNodesDesdeP12(string $p12Path, string $password, string $outputPem): bool
    {
        $openssl = $this->resolverBinarioOpenssl();
        if ($openssl === null) {
            return false;
        }

        $passFile = tempnam(sys_get_temp_dir(), 'sifen_pkcs12_pass_');
        if ($passFile === false) {
            return false;
        }

        file_put_contents($passFile, $password);

        $cmd = sprintf(
            '%s pkcs12 -in %s -out %s -nodes -passin file:%s 2>&1',
            escapeshellarg($openssl),
            escapeshellarg($p12Path),
            escapeshellarg($outputPem),
            escapeshellarg($passFile)
        );

        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);
        @unlink($passFile);

        return $exitCode === 0
            && is_file($outputPem)
            && filesize($outputPem) > 100;
    }

    private function resolverBinarioOpenssl(): ?string
    {
        $candidatos = array_filter([
            env('OPENSSL_BIN'),
            'C:/xampp/apache/bin/openssl.exe',
            'C:/xampp/php/extras/openssl/openssl.exe',
            'openssl',
        ]);

        foreach ($candidatos as $bin) {
            if ($bin === 'openssl') {
                $out = [];
                $code = 1;
                @exec('where openssl 2>nul', $out, $code);
                if ($code === 0 && isset($out[0]) && is_file(trim($out[0]))) {
                    return trim($out[0]);
                }
                continue;
            }

            if (is_file($bin)) {
                return $bin;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $certs
     * @return array{0: string, 1: ?string}
     */
    private function resolverClaveTlsPem(array $certs, string $password): array
    {
        $keyPem = trim((string) $certs['pkey']);

        if ($keyPem === '') {
            throw new RuntimeException('El P12 no contiene clave privada utilizable.');
        }

        if (! $this->clavePemEstaCifrada($keyPem)) {
            return [$keyPem, null];
        }

        $pkeyResource = openssl_pkey_get_private($keyPem, $password)
            ?: openssl_pkey_get_private($keyPem);

        if ($pkeyResource !== false) {
            $exportada = '';
            if (@openssl_pkey_export($pkeyResource, $exportada, null) && trim($exportada) !== '') {
                return [trim($exportada), null];
            }
        }

        // curl puede usar la clave cifrada del P12 con SSLKEYPASSWD
        return [$keyPem, $password];
    }

    private function clavePemEstaCifrada(string $pem): bool
    {
        return str_contains($pem, 'ENCRYPTED')
            || str_contains($pem, 'Proc-Type: 4,ENCRYPTED');
    }

    /**
     * @param  array<string, mixed>  $certs
     */
    private function resolverCertificadoParaClave(array $certs, ?string $password = null): string
    {
        $pkey = null;
        if ($password !== null && $password !== '') {
            $pkey = openssl_pkey_get_private($certs['pkey'], $password);
        }
        if ($pkey === false) {
            $pkey = openssl_pkey_get_private($certs['pkey']);
        }
        if ($pkey === false) {
            throw new RuntimeException('No se pudo leer la clave privada del certificado P12.');
        }

        $candidatos = [$certs['cert']];
        if (! empty($certs['extracerts']) && is_array($certs['extracerts'])) {
            $candidatos = array_merge($candidatos, $certs['extracerts']);
        }

        foreach ($candidatos as $pem) {
            if (! is_string($pem) || $pem === '') {
                continue;
            }

            $x509 = openssl_x509_read($pem);
            if ($x509 !== false && openssl_x509_check_private_key($x509, $pkey)) {
                return $pem;
            }
        }

        // P12 íntegro (openssl_pkcs12_read OK): usar certificado principal aunque
        // openssl_x509_check_private_key falle en algunos P12 de PSC paraguayos.
        if (is_string($certs['cert']) && trim($certs['cert']) !== '') {
            return trim($certs['cert']);
        }

        throw new RuntimeException(
            'La clave privada del P12 no corresponde a ningún certificado del archivo.'
        );
    }

    public function disponible(): bool
    {
        $path = config('sifen.certificado.path');

        return $path
            && File::exists($path)
            && filled($this->obtenerPassword());
    }

    public function obtenerPassword(): ?string
    {
        $config = SifenConfiguracion::activa() ?? SifenConfiguracion::orderBy('id')->first();

        if ($config) {
            $fromDb = $config->passwordCertificado();
            if (filled($fromDb)) {
                return trim((string) $fromDb);
            }
        }

        $env = config('sifen.certificado.password');

        return filled($env) ? trim((string) $env) : null;
    }

    /**
     * Copia el P12 a un archivo activo legible (mismo patrón que la firma Node).
     */
    public function materializarP12Temporal(?string $path = null): string
    {
        return $this->sincronizarP12Activo($path);
    }

    public function sincronizarP12Activo(?string $path = null): string
    {
        $path = $path ?: config('sifen.certificado.path');

        if (! $path || ! File::exists($path)) {
            throw new RuntimeException('Certificado SIFEN no encontrado.');
        }

        $dir = storage_path('sifen/tools');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $dest = $dir.DIRECTORY_SEPARATOR.'active_client.p12';
        $contenido = File::get($path);
        if ($contenido === '') {
            throw new RuntimeException('No se pudo leer el certificado P12.');
        }

        File::put($dest, $contenido);
        @chmod($dest, 0666);
        @chmod($path, 0666);

        return $dest;
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnosticarCertificado(?string $xmlFirmadoReferencia = null): array
    {
        $path = config('sifen.certificado.path');
        $password = $this->obtenerPassword();
        $resultado = [
            'ruta' => $path,
            'legible' => $path && is_readable($path),
            'password_configurada' => filled($password),
            'node_disponible' => $this->nodeDisponible(),
            'ambiente' => config('sifen.ambiente', 'test'),
        ];

        if (! $resultado['legible'] || ! filled($password)) {
            return $resultado;
        }

        try {
            $material = $this->cargarDesdeP12($path, $password);
            $info = openssl_x509_parse($material['cert']);
            $resultado['cn'] = $info['subject']['CN'] ?? null;
            $resultado['sha1'] = openssl_x509_fingerprint($material['cert'], 'sha1');
            $resultado['valido_hasta'] = isset($info['validTo_time_t'])
                ? date('Y-m-d', $info['validTo_time_t'])
                : null;
        } catch (\Throwable $e) {
            $resultado['error_p12'] = $e->getMessage();
        }

        if ($xmlFirmadoReferencia && is_file($xmlFirmadoReferencia)) {
            $ref = $this->huellaDesdeXmlFirmado($xmlFirmadoReferencia);
            $resultado['referencia_aprobada'] = $ref;
            if ($ref && isset($resultado['sha1'])) {
                $resultado['coincide_con_aprobado'] = strtolower($ref['sha1']) === strtolower($resultado['sha1']);
            }
        }

        return $resultado;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function huellaDesdeXmlFirmado(string $xmlPath): ?array
    {
        if (! is_file($xmlPath)) {
            return null;
        }

        $xml = file_get_contents($xmlPath);
        if (! is_string($xml) || ! preg_match('/<X509Certificate>(.*?)<\/X509Certificate>/s', $xml, $m)) {
            return null;
        }

        $pem = "-----BEGIN CERTIFICATE-----\n"
            .chunk_split(preg_replace('/\s+/', '', $m[1]), 64, "\n")
            ."-----END CERTIFICATE-----\n";
        $info = openssl_x509_parse($pem);

        return [
            'cn' => $info['subject']['CN'] ?? null,
            'sha1' => openssl_x509_fingerprint($pem, 'sha1'),
            'valido_hasta' => isset($info['validTo_time_t']) ? date('Y-m-d', $info['validTo_time_t']) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function probarConexionTls(?string $endpoint = null): array
    {
        $endpoint = $endpoint ?: config('sifen.ws.'.config('sifen.ambiente', 'test').'.recepcion_de_endpoint');
        $password = $this->obtenerPassword();

        if (! filled($password)) {
            throw new RuntimeException('Contraseña del certificado no configurada.');
        }

        $p12 = $this->materializarP12Temporal();
        $metaFile = tempnam(storage_path('sifen/tools'), 'sifen_probe_meta_').'.json';

        $node = $this->resolverNodePath();
        $script = storage_path('sifen/tools/probe-tls.js');
        if (! $node || ! is_file($script)) {
            throw new RuntimeException('Node.js o probe-tls.js no disponible.');
        }

        $cmd = sprintf(
            '%s %s %s %s %s %s 2>&1',
            escapeshellarg($node),
            escapeshellarg($script),
            escapeshellarg($endpoint),
            escapeshellarg($p12),
            escapeshellarg((string) $password),
            escapeshellarg($metaFile)
        );

        exec($cmd, $output, $exitCode);
        $meta = is_file($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : null;
        @unlink($metaFile);

        if (! is_array($meta)) {
            return [
                'ok' => false,
                'http_code' => 0,
                'error' => 'No se pudo ejecutar la prueba TLS.'.($output ? ' '.implode(' ', $output) : ''),
                'endpoint' => $endpoint,
            ];
        }

        $meta['endpoint'] = $endpoint;
        $meta['exit_code'] = $exitCode;

        return $meta;
    }

    public function resolverNodePath(): ?string
    {
        $candidatos = array_filter([
            config('sifen.node_path'),
            env('SIFEN_NODE_PATH'),
            'C:/Program Files/nodejs/node.exe',
            'C:/Program Files (x86)/nodejs/node.exe',
            'node',
        ]);

        foreach ($candidatos as $candidato) {
            if ($candidato === null || $candidato === '') {
                continue;
            }

            if ($candidato === 'node' || str_ends_with(strtolower($candidato), 'node.exe')) {
                if ($candidato !== 'node' && is_file($candidato)) {
                    return $candidato;
                }

                $output = [];
                $code = 0;
                @exec('where node 2>nul', $output, $code);
                if ($code === 0 && isset($output[0]) && is_file(trim($output[0]))) {
                    return trim($output[0]);
                }

                continue;
            }

            if (is_file($candidato)) {
                return $candidato;
            }
        }

        return null;
    }

    public function nodeDisponible(): bool
    {
        return is_file(storage_path('sifen/tools/send-de.js'))
            && $this->resolverNodePath() !== null;
    }

    private function resolverPassword(): ?string
    {
        return $this->obtenerPassword();
    }
}
