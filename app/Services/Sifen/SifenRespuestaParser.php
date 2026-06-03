<?php

namespace App\Services\Sifen;

class SifenRespuestaParser
{
    /**
     * @return array{
     *   codigo: ?string,
     *   mensaje: ?string,
     *   estado: ?string,
     *   cdc: ?string,
     *   protocolo: ?string,
     *   aprobado: bool,
     *   raw: string,
     * }
     */
    public function parsear(string $xmlRespuesta): array
    {
        $resultado = [
            'codigo' => null,
            'mensaje' => null,
            'estado' => null,
            'cdc' => null,
            'protocolo' => null,
            'aprobado' => false,
            'raw' => $xmlRespuesta,
        ];

        if (trim($xmlRespuesta) === '') {
            return $resultado;
        }

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($xmlRespuesta)) {
            return $resultado;
        }

        $resultado['codigo'] = $this->texto($dom, 'dCodRes');
        $resultado['mensaje'] = $this->texto($dom, 'dMsgRes');
        $resultado['estado'] = $this->texto($dom, 'dEstRes');
        $resultado['cdc'] = $this->texto($dom, 'dCDC') ?? $this->atributo($dom, 'Id');
        $resultado['protocolo'] = $this->texto($dom, 'dProtAut');

        $codigo = $resultado['codigo'];
        $resultado['aprobado'] = in_array($codigo, ['0260', '0261', '0300'], true)
            || str_contains(strtolower((string) $resultado['estado']), 'aprobado');

        return $resultado;
    }

    private function texto(\DOMDocument $dom, string $tag): ?string
    {
        $nodes = $dom->getElementsByTagName($tag);

        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function atributo(\DOMDocument $dom, string $name): ?string
    {
        foreach ($dom->getElementsByTagName('DE') as $node) {
            if ($node->hasAttribute($name)) {
                return $node->getAttribute($name);
            }
        }

        return null;
    }
}
