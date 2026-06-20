<?php

namespace App\Services\Sifen;

use DOMDocument;
use RuntimeException;

class SifenSoapClient
{
    private const NS_SIFEN = 'http://ekuatia.set.gov.py/sifen/xsd';

    private const NS_SOAP = 'http://www.w3.org/2003/05/soap-envelope';

    public function __construct(
        private SifenCertificadoService $certificadoService,
    ) {}

    public function enviarDocumento(string $xmlFirmado, ?int $dId = null): string
    {
        if (! $this->certificadoService->disponible()) {
            throw new RuntimeException('Certificado SIFEN no configurado para envío.');
        }

        $xmlFirmado = $this->limpiarXmlEntrada($xmlFirmado);

        SifenXmlManipulator::assertEstructuraFirmaValida($xmlFirmado);

        $dId = $dId ?? (int) now()->format('YmdHis');
        $envelope = $this->construirEnvelope($dId, $xmlFirmado);
        $this->guardarSoapDepuracion($envelope);
        $endpoint = $this->endpointRecepcion();

        $certPass = $this->certificadoService->obtenerPassword();
        if ($certPass === null || $certPass === '') {
            throw new RuntimeException('Contraseña del certificado P12 no configurada (configuración SIFEN o SIFEN_CERT_PASSWORD).');
        }

        $p12Path = $this->certificadoService->materializarP12Temporal();

        $intentos = [];

        if (config('sifen.envio_node', true) && $this->certificadoService->nodeDisponible()) {
            $resultado = $this->enviarConTips($endpoint, $envelope, $dId, $xmlFirmado, $p12Path, $certPass);
            $intentos[] = 'setapi-tips: HTTP '.$resultado['http_code']
                .($resultado['redirect_url'] !== '' ? ' -> '.$resultado['redirect_url'] : '');
            if ($this->esRespuestaUtil($resultado)) {
                $this->guardarSoapRespuesta($resultado['body'], $resultado['http_code'], $endpoint, 'setapi-tips', $intentos);

                return $resultado['body'];
            }

            $resultado = $this->enviarConNode($endpoint, $envelope, $p12Path, $certPass);
            $intentos[] = 'node-forge-chain: HTTP '.$resultado['http_code']
                .($resultado['redirect_url'] !== '' ? ' -> '.$resultado['redirect_url'] : '');
            if ($this->esRespuestaUtil($resultado)) {
                $this->guardarSoapRespuesta($resultado['body'], $resultado['http_code'], $endpoint, 'node-forge-chain', $intentos);

                return $resultado['body'];
            }
        }

        $resultado = $this->enviarConCurl($endpoint, $envelope, $p12Path, $certPass);
        $intentos[] = 'curl-p12-temp: HTTP '.$resultado['http_code']
            .($resultado['redirect_url'] !== '' ? ' -> '.$resultado['redirect_url'] : '');
        if ($resultado['curl_error'] !== '') {
            $intentos[count($intentos) - 1] .= ' ('.$resultado['curl_error'].')';
        }

        $this->guardarSoapRespuesta(
            $resultado['body'],
            $resultado['http_code'],
            $endpoint,
            $resultado['estrategia'] ?? 'curl-p12-temp',
            $intentos
        );

        return $this->validarRespuesta($resultado, $endpoint);
    }

    /**
     * @return array{body: string, http_code: int, redirect_url: string, curl_error: string, estrategia: string}
     */
    private function enviarConCurl(string $endpoint, string $envelope, string $p12Path, string $certPass): array
    {
        $ch = curl_init($endpoint);
        $opciones = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'User-Agent: facturaSend',
                'Content-Type: application/xml; charset=utf-8',
                'Accept: application/xml',
            ],
            CURLOPT_SSLCERT => $p12Path,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSLCERTPASSWD => $certPass,
            CURLOPT_KEYPASSWD => $certPass,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 120,
        ];

        if (defined('CURL_SSLVERSION_TLSv1_2')) {
            $opciones[CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;
        }

        curl_setopt_array($ch, $opciones);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirectUrl = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        return [
            'body' => is_string($body) ? $body : '',
            'http_code' => $httpCode,
            'redirect_url' => $redirectUrl,
            'curl_error' => $body === false ? $curlError : '',
            'estrategia' => 'curl-p12-temp',
        ];
    }

    /**
     * @return array{body: string, http_code: int, redirect_url: string}
     */
    private function enviarConTips(
        string $endpoint,
        string $envelope,
        int $dId,
        string $xmlFirmado,
        string $p12Path,
        string $certPass,
    ): array {
        $dir = storage_path('sifen/tools');
        $xmlTmp = tempnam($dir, 'sifen_de_in_');
        $responseTmp = tempnam($dir, 'sifen_soap_out_');
        $metaTmp = tempnam($dir, 'sifen_soap_meta_');
        $requestTmp = tempnam($dir, 'sifen_soap_req_');

        if ($xmlTmp === false || $responseTmp === false || $metaTmp === false || $requestTmp === false) {
            throw new RuntimeException('No se pudieron crear archivos temporales para envío TIPS.');
        }

        $responseFile = $responseTmp.'.xml';
        $metaFile = $metaTmp.'.json';
        $requestFile = $requestTmp.'.xml';
        @unlink($responseTmp);
        @unlink($metaTmp);
        @unlink($requestTmp);

        file_put_contents($xmlTmp, $xmlFirmado);

        $node = $this->certificadoService->resolverNodePath();
        $script = storage_path('sifen/tools/send-de-tips.js');
        $ambiente = config('sifen.ambiente', 'test') === 'production' ? 'prod' : 'test';

        $cmd = sprintf(
            '%s %s %s %s %s %s %s %s %s %s 2>&1',
            escapeshellarg((string) $node),
            escapeshellarg($script),
            escapeshellarg((string) $dId),
            escapeshellarg($ambiente),
            escapeshellarg($p12Path),
            escapeshellarg($certPass),
            escapeshellarg($xmlTmp),
            escapeshellarg($responseFile),
            escapeshellarg($metaFile),
            escapeshellarg($requestFile)
        );

        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        @unlink($xmlTmp);

        if (config('sifen.debug_soap', false) && is_file($requestFile)) {
            file_put_contents(
                storage_path('sifen/logs/last_soap_request.xml'),
                (string) file_get_contents($requestFile)
            );
        }

        $meta = ['httpCode' => 0, 'redirectUrl' => '', 'error' => ''];
        if (is_file($metaFile)) {
            $decoded = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $meta = array_merge($meta, $decoded);
            }
            @unlink($metaFile);
        }

        $body = is_file($responseFile) ? (string) file_get_contents($responseFile) : '';
        @unlink($responseFile);
        @unlink($requestFile);

        if ($exitCode !== 0 && $body === '' && ($meta['error'] ?? '') !== '') {
            throw new RuntimeException('Envío SIFEN vía TIPS falló: '.$meta['error']);
        }

        return [
            'body' => $body,
            'http_code' => (int) ($meta['httpCode'] ?? 0),
            'redirect_url' => (string) ($meta['redirectUrl'] ?? ''),
        ];
    }

    /**
     * @return array{body: string, http_code: int, redirect_url: string}
     */
    private function enviarConNode(string $endpoint, string $envelope, string $p12Path, string $certPass): array
    {
        $dir = storage_path('sifen/tools');
        $envelopeTmp = tempnam($dir, 'sifen_soap_in_');
        $responseTmp = tempnam($dir, 'sifen_soap_out_');
        $metaTmp = tempnam($dir, 'sifen_soap_meta_');

        if ($envelopeTmp === false || $responseTmp === false || $metaTmp === false) {
            throw new RuntimeException('No se pudieron crear archivos temporales para envío Node.');
        }

        $responseFile = $responseTmp.'.xml';
        $metaFile = $metaTmp.'.json';
        @unlink($responseTmp);
        @unlink($metaTmp);

        file_put_contents($envelopeTmp, $envelope);

        $node = $this->certificadoService->resolverNodePath();
        $script = storage_path('sifen/tools/send-de.js');
        $cmd = sprintf(
            '%s %s %s %s %s %s %s %s 2>&1',
            escapeshellarg((string) $node),
            escapeshellarg($script),
            escapeshellarg($endpoint),
            escapeshellarg($p12Path),
            escapeshellarg($certPass),
            escapeshellarg($envelopeTmp),
            escapeshellarg($responseFile),
            escapeshellarg($metaFile)
        );

        $output = [];
        $exitCode = 1;
        exec($cmd, $output, $exitCode);

        @unlink($envelopeTmp);

        $meta = ['httpCode' => 0, 'redirectUrl' => '', 'error' => ''];
        if (is_file($metaFile)) {
            $decoded = json_decode((string) file_get_contents($metaFile), true);
            if (is_array($decoded)) {
                $meta = array_merge($meta, $decoded);
            }
            @unlink($metaFile);
        }

        $body = is_file($responseFile) ? (string) file_get_contents($responseFile) : '';
        @unlink($responseFile);

        if ($exitCode !== 0 && $body === '' && ($meta['error'] ?? '') !== '') {
            throw new RuntimeException('Envío SIFEN vía Node falló: '.$meta['error']);
        }

        return [
            'body' => $body,
            'http_code' => (int) ($meta['httpCode'] ?? 0),
            'redirect_url' => (string) ($meta['redirectUrl'] ?? ''),
        ];
    }

    /**
     * @param  array{body: string, http_code: int, redirect_url: string, curl_error?: string}  $resultado
     */
    private function validarRespuesta(array $resultado, string $endpoint): string
    {
        $body = $resultado['body'];
        $httpCode = $resultado['http_code'];
        $redirectUrl = $resultado['redirect_url'];
        $curlError = $resultado['curl_error'] ?? '';

        if ($body === '' && $curlError !== '') {
            throw new RuntimeException('Error de conexión SIFEN: '.$curlError);
        }

        if (! $this->esRespuestaSifen($body)) {
            if ($httpCode >= 300 || trim($body) === '') {
                throw new RuntimeException($this->describirFalloHttp($httpCode, $redirectUrl, $endpoint));
            }

            if ($httpCode >= 400) {
                throw new RuntimeException("SIFEN respondió HTTP {$httpCode}: ".substr($body, 0, 500));
            }
        }

        return $body;
    }

    /**
     * @param  array{body: string, http_code: int, redirect_url: string}  $response
     */
    private function esRespuestaUtil(array $response): bool
    {
        if ($this->esRespuestaSifen($response['body'])) {
            return true;
        }

        if ($response['http_code'] === 200 && trim($response['body']) !== '') {
            return true;
        }

        if ($response['http_code'] >= 400) {
            return true;
        }

        return ! ($response['http_code'] === 302 && str_contains($response['redirect_url'], 'hangup'));
    }

    private function describirFalloHttp(int $httpCode, string $redirectUrl, string $endpoint): string
    {
        if ($httpCode === 302 && str_contains($redirectUrl, 'hangup')) {
            return 'SIFEN rechazó el certificado de cliente en mTLS (HTTP 302 hangup). '
                .'La firma del XML puede ser válida, pero el envío exige que el P12 esté habilitado en ambiente '
                .config('sifen.ambiente', 'test').' ante la SET (Marangatu / e-Kuatia). '
                .'Verifique habilitación del contribuyente y que el certificado tenga clientAuth.';
        }

        return "SIFEN respondió HTTP {$httpCode} sin respuesta válida. "
            .'Endpoint: '.$endpoint
            .($redirectUrl !== '' ? '. Redirección: '.$redirectUrl : '.')
            .'. Verifique certificado TLS y que el servicio e-Kuatia esté disponible.';
    }

    private function construirEnvelope(int $dId, string $xmlFirmado): string
    {
        $deXml = SifenXmlManipulator::extraerRdeParaSoap($xmlFirmado);

        if ($deXml === '' || ! str_contains($deXml, '<rDE')) {
            throw new RuntimeException('No se pudo serializar el documento rDE.');
        }

        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<env:Envelope xmlns:env="'.self::NS_SOAP.'">'
            .'<env:Header/>'
            .'<env:Body>'
            .'<rEnviDe xmlns="'.self::NS_SIFEN.'">'
            .'<dId>'.$dId.'</dId>'
            .'<xDE>'.$deXml.'</xDE>'
            .'</rEnviDe>'
            .'</env:Body>'
            .'</env:Envelope>';

        return SifenXmlManipulator::compactar($envelope);
    }

    private function esRespuestaSifen(string $response): bool
    {
        return str_contains($response, 'rRetEnviDe')
            || str_contains($response, 'rProtDe')
            || str_contains($response, 'dCodRes');
    }

    private function endpointRecepcion(): string
    {
        $ambiente = config('sifen.ambiente', 'test');

        // TIPS/setapi publica contra la URL .wsdl (no /recibe sin sufijo).
        return config("sifen.ws.{$ambiente}.recepcion_de")
            ?: config("sifen.ws.{$ambiente}.recepcion_de_endpoint");
    }

    private function limpiarXmlEntrada(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;

        return trim($xml);
    }

    /**
     * @param  array<int, string>  $intentos
     */
    private function guardarSoapRespuesta(
        string $response,
        int $httpCode,
        string $endpoint,
        ?string $estrategia = null,
        array $intentos = [],
    ): void {
        if (! config('sifen.debug_soap', false)) {
            return;
        }

        $dir = storage_path('sifen/logs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $meta = "endpoint: {$endpoint}\nhttp_code: {$httpCode}\nlength: ".strlen($response)."\n";
        if ($estrategia) {
            $meta .= "tls_strategy: {$estrategia}\n";
        }
        if ($intentos !== []) {
            $meta .= "tls_attempts:\n  - ".implode("\n  - ", $intentos)."\n";
        }

        file_put_contents(
            $dir.DIRECTORY_SEPARATOR.'last_soap_response.xml',
            "<!-- HTTP {$httpCode} endpoint: {$endpoint} tls: {$estrategia} -->\n".$response
        );

        file_put_contents($dir.DIRECTORY_SEPARATOR.'last_soap_response.meta.txt', $meta);
    }

    private function guardarSoapDepuracion(string $envelope): void
    {
        if (! config('sifen.debug_soap', false)) {
            return;
        }

        $dir = storage_path('sifen/logs');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.'last_soap_request.xml', $envelope);
    }
}
