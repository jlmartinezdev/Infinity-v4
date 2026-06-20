<?php

namespace App\Services\Sifen;

class SifenRespuestaParser
{
    private const NS_SIFEN = 'http://ekuatia.set.gov.py/sifen/xsd';

    /**
     * @return array{
     *   codigo: ?string,
     *   mensaje: ?string,
     *   estado: ?string,
     *   cdc: ?string,
     *   protocolo: ?string,
     *   aprobado: bool,
     *   detalles: array<int, array{codigo: ?string, mensaje: ?string}>,
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
            'detalles' => [],
            'raw' => $xmlRespuesta,
        ];

        $xmlRespuesta = trim($xmlRespuesta);
        if ($xmlRespuesta === '') {
            $resultado['mensaje'] = 'Respuesta vacía de SIFEN.';

            return $resultado;
        }

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($xmlRespuesta)) {
            $resultado['mensaje'] = 'La respuesta de SIFEN no es XML válido: '.$this->resumirTexto($xmlRespuesta);

            return $resultado;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('s', self::NS_SIFEN);

        $faultText = $this->xpathTexto($xpath, '//*[local-name()="Fault"]//*[local-name()="Text"]')
            ?? $this->xpathTexto($xpath, '//*[local-name()="faultstring"]');

        if ($faultText) {
            $resultado['mensaje'] = 'SOAP Fault: '.$faultText;

            return $resultado;
        }

        $resultado['estado'] = $this->xpathTexto($xpath, '//*[local-name()="dEstRes"]');
        $resultado['protocolo'] = $this->xpathTexto($xpath, '//*[local-name()="dProtAut"]');
        $resultado['cdc'] = $this->xpathTexto($xpath, '//*[local-name()="rProtDe"]/*[local-name()="Id"]')
            ?? $this->xpathTexto($xpath, '//*[local-name()="DE"]/@Id');

        foreach ($xpath->query('//*[local-name()="gResProc"]') as $grupo) {
            if (! $grupo instanceof \DOMElement) {
                continue;
            }

            $detalle = [
                'codigo' => $this->textoHijo($grupo, 'dCodRes'),
                'mensaje' => $this->textoHijo($grupo, 'dMsgRes'),
            ];

            if ($detalle['codigo'] || $detalle['mensaje']) {
                $resultado['detalles'][] = $detalle;
            }
        }

        if ($resultado['detalles'] === []) {
            $codigoSueltos = $this->xpathTodos($xpath, '//*[local-name()="dCodRes"]');
            $mensajeSueltos = $this->xpathTodos($xpath, '//*[local-name()="dMsgRes"]');

            foreach ($codigoSueltos as $i => $codigo) {
                $resultado['detalles'][] = [
                    'codigo' => $codigo,
                    'mensaje' => $mensajeSueltos[$i] ?? null,
                ];
            }
        }

        if ($resultado['detalles'] !== []) {
            $resultado['codigo'] = $resultado['detalles'][0]['codigo'];
            $partes = [];
            foreach ($resultado['detalles'] as $detalle) {
                $mensaje = trim((string) ($detalle['mensaje'] ?? ''));
                if ($mensaje === '') {
                    continue;
                }
                if ($detalle['codigo'] && str_starts_with($mensaje, '['.$detalle['codigo'].']')) {
                    $partes[] = $mensaje;
                } else {
                    $partes[] = trim(($detalle['codigo'] ? '['.$detalle['codigo'].'] ' : '').$mensaje);
                }
            }
            $resultado['mensaje'] = $partes !== [] ? implode(' · ', $partes) : null;
        }

        if (! $resultado['mensaje']) {
            $resultado['mensaje'] = $this->xpathTexto($xpath, '//*[local-name()="dMsgRes"]');
        }

        if (! $resultado['codigo']) {
            $resultado['codigo'] = $this->xpathTexto($xpath, '//*[local-name()="dCodRes"]');
        }

        $estado = strtolower((string) $resultado['estado']);
        $codigos = array_filter(array_column($resultado['detalles'], 'codigo'));

        $resultado['aprobado'] = in_array('0260', $codigos, true)
            || in_array('0261', $codigos, true)
            || ($resultado['codigo'] && in_array($resultado['codigo'], ['0260', '0261'], true))
            || str_contains($estado, 'aprobado');

        if (! $resultado['aprobado'] && ! $resultado['mensaje']) {
            $resultado['mensaje'] = $this->resumirTexto($xmlRespuesta);
        }

        return $resultado;
    }

    private function xpathTexto(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $valor = trim($nodes->item(0)?->textContent ?? '');

        return $valor !== '' ? $valor : null;
    }

    /**
     * @return array<int, string>
     */
    private function xpathTodos(\DOMXPath $xpath, string $query): array
    {
        $nodes = $xpath->query($query);
        if ($nodes === false || $nodes->length === 0) {
            return [];
        }

        $valores = [];
        foreach ($nodes as $node) {
            $texto = trim($node->textContent ?? '');
            if ($texto !== '') {
                $valores[] = $texto;
            }
        }

        return $valores;
    }

    private function textoHijo(\DOMElement $parent, string $localName): ?string
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === $localName) {
                $texto = trim($child->textContent ?? '');

                return $texto !== '' ? $texto : null;
            }
        }

        return null;
    }

    private function resumirTexto(string $texto): string
    {
        $limpio = trim(strip_tags($texto));
        $limpio = preg_replace('/\s+/', ' ', $limpio) ?? $limpio;

        return mb_substr($limpio, 0, 400).(mb_strlen($limpio) > 400 ? '…' : '');
    }
}
