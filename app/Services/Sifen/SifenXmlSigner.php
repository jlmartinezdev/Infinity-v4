<?php

namespace App\Services\Sifen;

use DOMDocument;
use DOMElement;
use RuntimeException;

class SifenXmlSigner
{
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    public function __construct(
        private SifenCertificadoService $certificadoService,
    ) {}

    /**
     * @return array{xml: string, digest_value: string}
     */
    public function firmar(string $xml, string $cdc): array
    {
        $material = $this->certificadoService->cargarDesdeP12();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido para firmar.');
        }

        $deNode = $dom->getElementsByTagName('DE')->item(0);
        if (! $deNode instanceof DOMElement) {
            throw new RuntimeException('No se encontró el nodo DE en el XML.');
        }

        $deNode->setAttribute('Id', $cdc);
        $deContenido = $deNode->C14N(true, false);
        $digestValue = base64_encode(hash('sha256', $deContenido, true));

        $privateKey = openssl_pkey_get_private($material['pkey']);
        if ($privateKey === false) {
            throw new RuntimeException('Clave privada inválida en el certificado.');
        }

        if (! openssl_sign($deContenido, $signatureRaw, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Error al firmar el documento electrónico.');
        }

        $root = $dom->documentElement;
        $signature = $dom->createElementNS(self::NS_DSIG, 'Signature');
        $root->appendChild($signature);

        $signedInfo = $this->appendChildNs($dom, $signature, 'SignedInfo');
        $canonicalizationMethod = $this->appendChildNs($dom, $signedInfo, 'CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');

        $signatureMethod = $this->appendChildNs($dom, $signedInfo, 'SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');

        $reference = $this->appendChildNs($dom, $signedInfo, 'Reference');
        $reference->setAttribute('URI', '#'.$cdc);

        $transforms = $this->appendChildNs($dom, $reference, 'Transforms');
        $transform1 = $this->appendChildNs($dom, $transforms, 'Transform');
        $transform1->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transform2 = $this->appendChildNs($dom, $transforms, 'Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');

        $digestMethod = $this->appendChildNs($dom, $reference, 'DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');

        $this->appendChildNs($dom, $reference, 'DigestValue', $digestValue);

        $this->appendChildNs($dom, $signature, 'SignatureValue', base64_encode($signatureRaw));

        $keyInfo = $this->appendChildNs($dom, $signature, 'KeyInfo');
        $x509Data = $this->appendChildNs($dom, $keyInfo, 'X509Data');
        $this->appendChildNs($dom, $x509Data, 'X509Certificate', $material['x509_clean']);

        return [
            'xml' => $dom->saveXML(),
            'digest_value' => $digestValue,
        ];
    }

    private function appendChildNs(DOMDocument $dom, DOMElement $parent, string $name, ?string $value = null): DOMElement
    {
        $element = $dom->createElementNS(self::NS_DSIG, $name);
        if ($value !== null) {
            $element->appendChild($dom->createTextNode($value));
        }
        $parent->appendChild($element);

        return $element;
    }
}
