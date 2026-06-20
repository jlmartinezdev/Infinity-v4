<?php

namespace App\Services\Sifen;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SifenXmlValidator
{
    /**
     * @param  'borrador'|'firmado'  $etapa  borrador = DE sin firma/QR; firmado = rDE completo
     * @return array{valido: bool, errores: array<int, string>, aviso: ?string}
     */
    public function validar(string $xml, string $etapa = 'firmado'): array
    {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            return [
                'valido' => false,
                'errores' => ['El contenido no es un XML válido.'],
                'aviso' => null,
            ];
        }

        if ($etapa === 'borrador') {
            return $this->validarBorrador($dom);
        }

        return $this->validarFirmado($dom);
    }

    /**
     * @return array{valido: bool, errores: array<int, string>, aviso: ?string}
     */
    private function validarBorrador(DOMDocument $dom): array
    {
        $errores = [];
        $ns = config('sifen.namespace', 'http://ekuatia.set.gov.py/sifen/xsd');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', $ns);

        if ($xpath->query('/s:rDE')->length === 0) {
            $errores[] = 'El documento debe tener elemento raíz rDE.';
        }

        if ($xpath->query('/s:rDE/s:DE')->length === 0) {
            $errores[] = 'Falta el elemento DE dentro de rDE.';
        }

        if ($xpath->query('/s:rDE/s:DE/@Id')->length === 0) {
            $errores[] = 'El elemento DE debe tener atributo Id (CDC).';
        }

        return [
            'valido' => $errores === [],
            'errores' => $errores,
            'aviso' => $errores === []
                ? 'Validación estructural del borrador. La validación XSD completa se aplica al documento firmado.'
                : null,
        ];
    }

    /**
     * @return array{valido: bool, errores: array<int, string>, aviso: ?string}
     */
    private function validarFirmado(DOMDocument $dom): array
    {
        $xsdPath = $this->resolverXsd('siRecepDE_v150_local.xsd');

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $valido = @$dom->schemaValidate($xsdPath);
        $errores = [];

        foreach (libxml_get_errors() as $error) {
            $errores[] = trim($error->message);
        }

        libxml_clear_errors();

        return [
            'valido' => $valido,
            'errores' => $errores,
            'aviso' => null,
        ];
    }

    public function resolverXsd(string $nombre): string
    {
        $path = config('sifen.paths.xsd').DIRECTORY_SEPARATOR.$nombre;

        if (! File::exists($path)) {
            throw new RuntimeException("No se encontró el schema XSD: {$path}");
        }

        return $path;
    }
}
