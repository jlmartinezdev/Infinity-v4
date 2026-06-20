<?php

namespace App\Services\Sifen;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use RuntimeException;

class SifenXmlSigner
{
    private const NS_SIFEN = 'http://ekuatia.set.gov.py/sifen/xsd';

    private const NS_DSIG = XMLSecurityDSig::XMLDSIGNS;

    private const CANON_SIGNED_INFO = XMLSecurityDSig::EXC_C14N;

    public function __construct(
        private SifenCertificadoService $certificadoService,
        private SifenNodeXmlSigner $nodeSigner,
    ) {}

    public function motorFirmaActivo(): string
    {
        if ($this->nodeSigner->disponible()) {
            return 'node-tips';
        }

        return 'php-xmlseclibs';
    }

    /**
     * @return array{xml: string, digest_value: string}
     */
    public function firmar(string $xml, string $cdc): array
    {
        if ($this->nodeSigner->disponible()) {
            return $this->nodeSigner->firmar($xml, $cdc);
        }

        return $this->firmarConPhp($xml, $cdc);
    }

    /**
     * @return array{xml: string, digest_value: string}
     */
    private function firmarConPhp(string $xml, string $cdc): array
    {
        $material = $this->certificadoService->cargarDesdeP12();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido para firmar.');
        }

        $rdeNode = $dom->documentElement;
        if (! $rdeNode instanceof DOMElement) {
            throw new RuntimeException('No se encontró el elemento rDE en el XML.');
        }

        $deNode = $dom->getElementsByTagNameNS(self::NS_SIFEN, 'DE')->item(0)
            ?? $dom->getElementsByTagName('DE')->item(0);
        if (! $deNode instanceof DOMElement) {
            throw new RuntimeException('No se encontró el nodo DE en el XML.');
        }

        $deNode->setAttribute('Id', $cdc);
        $deNode->setIdAttribute('Id', true);

        $dsig = new XMLSecurityDSig('');
        $dsig->sigNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', self::NS_DSIG);
        $dsig->setCanonicalMethod(self::CANON_SIGNED_INFO);
        $dsig->addReference(
            $deNode,
            XMLSecurityDSig::SHA256,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                'http://www.w3.org/2001/10/xml-exc-c14n#',
            ],
            ['overwrite' => false]
        );

        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $objKey->loadKey($material['pkey'], false, false);

        $dsig->appendSignature($rdeNode);
        $sigNode = $rdeNode->lastChild;
        if (! $sigNode instanceof DOMElement) {
            throw new RuntimeException('No se pudo insertar el bloque Signature en el XML.');
        }

        $sigNode = SifenXmlManipulator::reescribirFirmaSinPrefijoEnDom($sigNode);
        $dsig->sigNode = $sigNode;

        $dsig->sign($objKey);
        $dsig->add509Cert($material['cert'], true);

        $xmlFirmado = SifenXmlManipulator::compactar($dom->saveXML() ?: '');
        $xmlFirmado = preg_replace('/ xmlns:default="[^"]*"/', '', $xmlFirmado) ?? $xmlFirmado;

        self::verificarFirmaEstatica($xmlFirmado, $material['cert']);
        SifenXmlManipulator::assertEstructuraFirmaValida($xmlFirmado);

        return [
            'xml' => $xmlFirmado,
            'digest_value' => $this->extraerDigestValue($xmlFirmado),
        ];
    }

    private static function verificarFirmaEstatica(string $xml, string $certPem): void
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('XML firmado inválido para verificación.');
        }

        $objDSig = new XMLSecurityDSig('');
        if (! $objDSig->locateSignature($dom)) {
            throw new RuntimeException('No se encontró la firma en el XML.');
        }

        $objKey = $objDSig->locateKey($objDSig->sigNode);
        if (! $objKey) {
            throw new RuntimeException('No se pudo determinar el algoritmo de la firma.');
        }

        $objKey->loadKey($certPem, false, true);

        if ($objDSig->verify($objKey) !== 1) {
            throw new RuntimeException(
                'La firma digital no supera verificación local. Revise certificado P12, contraseña y que la clave corresponda al certificado.'
            );
        }
    }

    private function extraerDigestValue(string $xml): string
    {
        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            throw new RuntimeException('XML firmado inválido al extraer DigestValue.');
        }

        $xpath = new DOMXPath($dom);
        $valor = $xpath->evaluate(
            'string(//*[local-name()="DigestValue" and namespace-uri()="'.self::NS_DSIG.'"])'
        );

        if (! is_string($valor) || $valor === '') {
            throw new RuntimeException('No se pudo obtener DigestValue de la firma.');
        }

        return $valor;
    }
}
