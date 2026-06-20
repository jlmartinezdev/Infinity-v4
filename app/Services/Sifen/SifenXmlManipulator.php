<?php

namespace App\Services\Sifen;

use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Manipulación de XML SIFEN sin re-serializar por DOM (evita prefijos default: en la firma).
 * Manual Técnico v150 §7.2.2.1 — namespace en <Signature>, sin prefijos.
 */
class SifenXmlManipulator
{
    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    public static function insertarDespuesDeFirma(string $xml, string $fragmento): string
    {
        foreach (['</Signature>', '</ds:Signature>'] as $marcador) {
            $pos = stripos($xml, $marcador);
            if ($pos !== false) {
                $offset = $pos + strlen($marcador);

                return substr($xml, 0, $offset).$fragmento.substr($xml, $offset);
            }
        }

        throw new RuntimeException('No se encontró el cierre </Signature> en el XML firmado.');
    }

    /**
     * Prepara el rDE para insertarlo en xDE del sobre SOAP.
     * SIFEN exige xsi:schemaLocation en el rDE embebido (error 0160 si falta).
     */
    public static function extraerRdeParaSoap(string $xml): string
    {
        $xml = preg_replace('/<\?xml[^?]*\?>\s*/', '', $xml) ?? $xml;
        $xml = trim($xml);

        if (! preg_match('/<rDE\b.*?<\/rDE>/s', $xml, $matches)) {
            throw new RuntimeException('No se encontró el elemento rDE en el XML firmado.');
        }

        $rde = $matches[0];

        if (! str_contains($rde, 'schemaLocation')) {
            throw new RuntimeException('El rDE no declara xsi:schemaLocation requerido por SIFEN.');
        }

        return self::compactar($rde);
    }

    public static function escaparTextoXml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function normalizarPrefijosFirma(string $xml): string
    {
        $xml = preg_replace('/<(\/?)default:([\w-]+)/', '<$1$2', $xml) ?? $xml;
        $xml = preg_replace('/<(\/?)ds:([\w-]+)/', '<$1$2', $xml) ?? $xml;
        $xml = preg_replace('/ xmlns:default="[^"]*"/', '', $xml) ?? $xml;
        $xml = preg_replace('/ xmlns:ds="[^"]*"/', '', $xml) ?? $xml;

        return $xml;
    }

    /**
     * Elimina prefijos default:/ds: en el subárbol de firma dentro del DOM.
     * Debe ejecutarse antes de calcular SignatureValue (no después).
     */
    public static function reescribirFirmaSinPrefijoEnDom(DOMElement $nodo): DOMElement
    {
        $hijos = [];
        foreach ($nodo->childNodes as $hijo) {
            if ($hijo instanceof DOMElement) {
                $hijos[] = $hijo;
            }
        }

        foreach ($hijos as $hijo) {
            self::reescribirFirmaSinPrefijoEnDom($hijo);
        }

        if ($nodo->namespaceURI !== self::NS_DSIG) {
            return $nodo;
        }

        if ($nodo->prefix === null || $nodo->prefix === '') {
            if ($nodo->localName === 'Signature' && ! $nodo->hasAttribute('xmlns')) {
                $nodo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', self::NS_DSIG);
            }

            return $nodo;
        }

        $padre = $nodo->parentNode;
        if (! $padre instanceof DOMElement) {
            return $nodo;
        }

        $doc = $nodo->ownerDocument;
        if (! $doc instanceof DOMDocument) {
            return $nodo;
        }

        $nuevo = $doc->createElementNS(self::NS_DSIG, $nodo->localName);

        if ($nodo->localName === 'Signature') {
            $nuevo->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', self::NS_DSIG);
        }

        if ($nodo->hasAttributes()) {
            foreach ($nodo->attributes as $attr) {
                if ($attr->namespaceURI === 'http://www.w3.org/2000/xmlns/') {
                    continue;
                }

                if ($attr->namespaceURI) {
                    $nuevo->setAttributeNS($attr->namespaceURI, $attr->nodeName, $attr->value);
                } else {
                    $nuevo->setAttribute($attr->nodeName, $attr->value);
                }
            }
        }

        while ($nodo->firstChild) {
            $nuevo->appendChild($nodo->firstChild);
        }

        $padre->replaceChild($nuevo, $nodo);

        return $nuevo;
    }

    public static function assertEstructuraFirmaValida(string $xml): void
    {
        $xml = self::normalizarPrefijosFirma($xml);

        if (str_contains($xml, 'default:Signature') || str_contains($xml, '<ds:Signature')) {
            throw new RuntimeException(
                'La firma digital tiene prefijos inválidos (default: o ds:). Regenerá el DE.'
            );
        }

        if (! preg_match(
            '/<Signature\b[^>]*\sxmlns="'.preg_quote(self::NS_DSIG, '/').'"/',
            $xml
        )) {
            throw new RuntimeException(
                'La firma debe incluir xmlns="'.self::NS_DSIG.'" en <Signature> según MT SIFEN §7.2.2.1.'
            );
        }

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;
        if (! @$dom->loadXML($xml, LIBXML_NOBLANKS)) {
            $errores = libxml_get_errors();
            libxml_clear_errors();
            $detalle = isset($errores[0]) ? trim($errores[0]->message) : 'sin detalle';

            throw new RuntimeException('El XML firmado no es well-formed. '.$detalle);
        }
    }

    public static function compactar(string $xml): string
    {
        $xml = preg_replace('/\r\n|\r|\n|\t/', '', $xml) ?? $xml;

        return preg_replace('/>\s+</', '><', $xml) ?? $xml;
    }

    /**
     * Actualiza dFecFirma y dFeEmiDE antes de firmar (evita código SIFEN 1004).
     */
    public static function actualizarFechasDe(string $xml, \Carbon\CarbonInterface $fecha): string
    {
        $valor = $fecha->format('Y-m-d\TH:i:s');
        $etiqueta = self::escaparTextoXml($valor);

        foreach (['dFecFirma', 'dFeEmiDE'] as $campo) {
            $reemplazado = preg_replace(
                '/<'.$campo.'>[^<]*<\/'.$campo.'>/',
                '<'.$campo.'>'.$etiqueta.'</'.$campo.'>',
                $xml,
                1,
                $count
            );

            if ($reemplazado === null || $count < 1) {
                throw new RuntimeException('No se encontró '.$campo.' en el XML para actualizar la fecha.');
            }

            $xml = $reemplazado;
        }

        return $xml;
    }
}
