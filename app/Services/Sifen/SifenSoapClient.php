<?php

namespace App\Services\Sifen;

use RuntimeException;

class SifenSoapClient
{
    public function __construct(
        private SifenCertificadoService $certificadoService,
    ) {}

    public function enviarDocumento(string $xmlFirmado, ?int $dId = null): string
    {
        if (! $this->certificadoService->disponible()) {
            throw new RuntimeException('Certificado SIFEN no configurado para envío.');
        }

        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        if (! $dom->loadXML($xmlFirmado)) {
            throw new RuntimeException('XML firmado inválido.');
        }

        $contenidoDe = $dom->saveXML($dom->documentElement);
        $dId = $dId ?? (int) now()->format('YmdHis');
        $envelope = $this->construirEnvelope($dId, $contenidoDe);
        $endpoint = $this->endpointRecepcion();

        $certPath = config('sifen.certificado.path');
        $certPass = config('sifen.certificado.password');

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $envelope,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/xml; charset=UTF-8',
                'Accept: application/xml',
            ],
            CURLOPT_SSLCERT => $certPath,
            CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSLCERTPASSWD => $certPass,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 120,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Error de conexión SIFEN: '.$error);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("SIFEN respondió HTTP {$httpCode}: ".substr((string) $response, 0, 500));
        }

        return (string) $response;
    }

    private function construirEnvelope(int $dId, string $contenidoDe): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<env:Envelope xmlns:env="http://www.w3.org/2003/05/soap-envelope">'
            .'<env:Header/>'
            .'<env:Body>'
            .'<rEnviDe xmlns="http://ekuatia.set.gov.py/sifen/xsd">'
            .'<dId>'.$dId.'</dId>'
            .'<xDE>'.$contenidoDe.'</xDE>'
            .'</rEnviDe>'
            .'</env:Body>'
            .'</env:Envelope>';
    }

    private function endpointRecepcion(): string
    {
        $ambiente = config('sifen.ambiente', 'test');

        return config("sifen.ws.{$ambiente}.recepcion_de_endpoint");
    }
}
