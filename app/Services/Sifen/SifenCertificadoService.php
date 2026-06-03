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

        $x509Clean = preg_replace(
            '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
            '',
            $certs['cert']
        ) ?? '';

        return [
            'cert' => $certs['cert'],
            'pkey' => $certs['pkey'],
            'x509_clean' => $x509Clean,
        ];
    }

    public function disponible(): bool
    {
        $path = config('sifen.certificado.path');

        return $path
            && File::exists($path)
            && filled($this->resolverPassword());
    }

    private function resolverPassword(): ?string
    {
        $config = SifenConfiguracion::activa() ?? SifenConfiguracion::orderBy('id')->first();

        if ($config) {
            $fromDb = $config->passwordCertificado();
            if (filled($fromDb)) {
                return $fromDb;
            }
        }

        $env = config('sifen.certificado.password');

        return filled($env) ? (string) $env : null;
    }
}
