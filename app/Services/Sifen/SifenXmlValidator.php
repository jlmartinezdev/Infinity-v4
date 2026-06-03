<?php

namespace App\Services\Sifen;

use DOMDocument;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SifenXmlValidator
{
    public function validar(string $xml): array
    {
        $xsdPath = $this->resolverXsd('DE_v150_local.xsd');

        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = false;

        if (! @$dom->loadXML($xml)) {
            return [
                'valido' => false,
                'errores' => ['El contenido no es un XML válido.'],
            ];
        }

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
