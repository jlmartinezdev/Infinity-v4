<?php

namespace App\Services\Sifen;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RuntimeException;

/**
 * Firma DE con facturacionelectronicapy-xmlsign (TIPS) vía Node.js.
 */
class SifenNodeXmlSigner
{
    public function __construct(
        private SifenCertificadoService $certificadoService,
    ) {}

    public function disponible(): bool
    {
        if (! config('sifen.firma_node', true)) {
            return false;
        }

        return is_file($this->rutaScriptFirma())
            && is_dir($this->rutaModuloXmlsign())
            && $this->resolverNodePath() !== null;
    }

    /**
     * @return array{xml: string, digest_value: string}
     */
    public function firmar(string $xml, string $cdc): array
    {
        $certPath = config('sifen.certificado.path');
        $password = $this->certificadoService->obtenerPassword();

        if (! $certPath || ! File::exists($certPath)) {
            throw new RuntimeException('Certificado SIFEN no encontrado.');
        }

        if ($password === null || $password === '') {
            throw new RuntimeException('Contraseña del certificado P12 no configurada.');
        }

        $xml = $this->asegurarIdDe($xml, $cdc);

        $dir = storage_path('sifen/tools');
        $inputTmp = tempnam($dir, 'sifen_sign_in_');
        $outputTmp = tempnam($dir, 'sifen_sign_out_');
        $errorTmp = tempnam($dir, 'sifen_sign_err_');

        if ($inputTmp === false || $outputTmp === false || $errorTmp === false) {
            throw new RuntimeException('No se pudieron crear archivos temporales para firmar.');
        }

        $outputFile = $outputTmp.'.xml';
        @unlink($outputTmp);
        @unlink($errorTmp);

        file_put_contents($inputTmp, $xml);

        $certTmpP12 = $this->materializarCertificadoTemporal($certPath);

        $node = $this->resolverNodePath();
        $script = $this->rutaScriptFirma();
        $cmd = sprintf(
            '%s %s %s %s %s node %s 2>%s',
            escapeshellarg($node),
            escapeshellarg($script),
            escapeshellarg($inputTmp),
            escapeshellarg($certTmpP12),
            escapeshellarg($password),
            escapeshellarg($outputFile),
            escapeshellarg($errorTmp)
        );

        $exitCode = 0;
        exec($cmd, $ignored, $exitCode);

        @unlink($inputTmp);
        @unlink($certTmpP12);

        if ($exitCode !== 0) {
            $detalle = is_file($errorTmp) ? trim((string) file_get_contents($errorTmp)) : '';
            @unlink($errorTmp);
            @unlink($outputFile);

            throw new RuntimeException(
                'Firma Node/TIPS falló'.($detalle !== '' ? ': '.$detalle : '.')
            );
        }

        @unlink($errorTmp);

        if (! is_file($outputFile)) {
            throw new RuntimeException('El firmador Node no generó el archivo de salida.');
        }

        $xmlFirmado = (string) file_get_contents($outputFile);
        @unlink($outputFile);

        $xmlFirmado = $this->sanitizarXmlFirmado($xmlFirmado);
        if ($xmlFirmado === '' || ! str_contains($xmlFirmado, '<Signature')) {
            throw new RuntimeException('El firmador Node no devolvió un XML firmado válido.');
        }

        $xmlFirmado = SifenXmlManipulator::compactar($xmlFirmado);
        SifenXmlManipulator::assertEstructuraFirmaValida($xmlFirmado);

        return [
            'xml' => $xmlFirmado,
            'digest_value' => $this->extraerDigestValue($xmlFirmado),
        ];
    }

    private function asegurarIdDe(string $xml, string $cdc): string
    {
        $xml = preg_replace('/<\?xml[^?]*\?>\s*/', '', $xml) ?? $xml;
        $xml = trim($xml);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (! $dom->loadXML($xml, LIBXML_NOBLANKS)) {
            throw new RuntimeException('XML inválido para firmar.');
        }

        $deNode = $dom->getElementsByTagName('DE')->item(0);
        if (! $deNode instanceof \DOMElement) {
            throw new RuntimeException('No se encontró el nodo DE en el XML.');
        }

        $deNode->setAttribute('Id', $cdc);

        $rde = $dom->documentElement;
        if (! $rde instanceof \DOMElement) {
            throw new RuntimeException('No se encontró el elemento rDE en el XML.');
        }

        return $dom->saveXML($rde) ?: $xml;
    }

    private function sanitizarXmlFirmado(string $raw): string
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = trim($raw);

        if (preg_match('/<rDE\b[\s\S]*<\/rDE>/', $raw, $matches)) {
            return $matches[0];
        }

        return $raw;
    }

    private function materializarCertificadoTemporal(string $certPath): string
    {
        $dir = storage_path('sifen/tools');
        $certTmp = tempnam($dir, 'sifen_cert_');
        if ($certTmp === false) {
            throw new RuntimeException('No se pudo crear archivo temporal para el certificado.');
        }

        $certTmpP12 = $certTmp.'.p12';
        @unlink($certTmp);

        if (@copy($certPath, $certTmpP12)) {
            return $certTmpP12;
        }

        $contenido = @file_get_contents($certPath);
        if ($contenido === false || $contenido === '') {
            throw new RuntimeException('No se pudo leer el certificado P12 para firmar.');
        }

        file_put_contents($certTmpP12, $contenido);

        return $certTmpP12;
    }

    private function extraerDigestValue(string $xml): string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('XML firmado inválido al extraer DigestValue.');
        }

        $xpath = new DOMXPath($dom);
        $valor = $xpath->evaluate(
            'string(//*[local-name()="DigestValue" and namespace-uri()="'.XMLSecurityDSig::XMLDSIGNS.'"])'
        );

        if (! is_string($valor) || $valor === '') {
            throw new RuntimeException('No se pudo obtener DigestValue de la firma.');
        }

        return $valor;
    }

    private function rutaScriptFirma(): string
    {
        return storage_path('sifen/tools/sign-de.js');
    }

    private function rutaModuloXmlsign(): string
    {
        return storage_path('sifen/tools/node_modules/facturacionelectronicapy-xmlsign');
    }

    private function resolverNodePath(): ?string
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
}
