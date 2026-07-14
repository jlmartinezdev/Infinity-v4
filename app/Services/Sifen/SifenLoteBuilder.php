<?php

namespace App\Services\Sifen;

use RuntimeException;
use ZipArchive;

/**
 * Arma el contenedor rLoteDE, lo comprime en ZIP y lo codifica en Base64 (WS siRecepLoteDE).
 */
class SifenLoteBuilder
{
    private const NS = 'http://ekuatia.set.gov.py/sifen/xsd';

    /**
     * @param  array<int, string>  $xmlsFirmados  Cada ítem es un rDE firmado completo
     */
    public function construirBase64Zip(array $xmlsFirmados): string
    {
        if ($xmlsFirmados === []) {
            throw new RuntimeException('El lote debe contener al menos un DE firmado.');
        }

        if (count($xmlsFirmados) > 50) {
            throw new RuntimeException('SIFEN admite como máximo 50 DE por lote.');
        }

        $partes = [];
        foreach ($xmlsFirmados as $xml) {
            $xml = $this->normalizarRde($xml);
            if (! preg_match('/<rDE\b/i', $xml)) {
                throw new RuntimeException('Cada ítem del lote debe ser un rDE firmado.');
            }
            $partes[] = $xml;
        }

        $loteXml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rLoteDE xmlns="'.self::NS.'">'
            .implode('', $partes)
            .'</rLoteDE>';

        return $this->comprimirZipBase64($loteXml);
    }

    private function normalizarRde(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = trim($xml);
        $xml = preg_replace('/^\s*<\?xml[^?]*\?>\s*/i', '', $xml) ?? $xml;

        return trim($xml);
    }

    private function comprimirZipBase64(string $loteXml): string
    {
        $tmpZip = tempnam(sys_get_temp_dir(), 'sifen_lote_');
        if ($tmpZip === false) {
            throw new RuntimeException('No se pudo crear ZIP temporal para el lote SIFEN.');
        }

        $zipPath = $tmpZip.'.zip';
        @unlink($tmpZip);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir ZIP temporal para el lote SIFEN.');
        }

        $zip->addFromString('lote.xml', $loteXml);
        $zip->close();

        $binario = file_get_contents($zipPath);
        @unlink($zipPath);

        if ($binario === false || $binario === '') {
            throw new RuntimeException('ZIP del lote SIFEN vacío o ilegible.');
        }

        return base64_encode($binario);
    }
}
