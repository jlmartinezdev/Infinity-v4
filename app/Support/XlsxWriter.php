<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Libro xlsx mínimo: texto, enteros, montos y fechas, sin dependencias externas.
 */
class XlsxWriter
{
    private string $dir;

    /** @var list<array{nombre: string, archivo: string}> */
    private array $hojas = [];

    public function __construct()
    {
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'xlsx-'.bin2hex(random_bytes(6));
        if (! mkdir($this->dir, 0755, true) && ! is_dir($this->dir)) {
            throw new RuntimeException('No se pudo crear la carpeta temporal del Excel.');
        }
    }

    public function __destruct()
    {
        if (is_dir($this->dir)) {
            $this->limpiar();
        }
    }

    /**
     * @param  list<string>  $encabezados
     * @param  list<'s'|'i'|'n'|'d'>  $tipos
     */
    public function hoja(string $nombre, array $encabezados, array $tipos): XlsxHoja
    {
        $indice = count($this->hojas) + 1;
        $archivo = $this->dir.DIRECTORY_SEPARATOR.'sheet'.$indice.'.xml';
        $this->hojas[] = [
            'nombre' => $this->nombreHoja($nombre),
            'archivo' => $archivo,
        ];

        return new XlsxHoja($archivo, $encabezados, $tipos);
    }

    public function guardar(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta: '.$dir);
        }

        $this->escribirEstilos();
        $this->escribirLibro();

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo '.$path);
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->relsRaiz());
        $zip->addFile($this->dir.DIRECTORY_SEPARATOR.'workbook.xml', 'xl/workbook.xml');
        $zip->addFile($this->dir.DIRECTORY_SEPARATOR.'styles.xml', 'xl/styles.xml');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsLibro());

        foreach ($this->hojas as $i => $hoja) {
            $zip->addFile($hoja['archivo'], 'xl/worksheets/sheet'.($i + 1).'.xml');
        }

        $zip->close();
        $this->limpiar();
    }

    private function escribirEstilos(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="1">
    <numFmt numFmtId="164" formatCode="yyyy-mm-dd"/>
  </numFmts>
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/></patternFill></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="5">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
    <xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="1" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML;
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'styles.xml', $xml);
    }

    private function escribirLibro(): void
    {
        $sheets = '';
        foreach ($this->hojas as $i => $hoja) {
            $nombre = htmlspecialchars($hoja['nombre'], ENT_XML1);
            $id = $i + 1;
            $sheets .= '<sheet name="'.$nombre.'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
        file_put_contents($this->dir.DIRECTORY_SEPARATOR.'workbook.xml', $xml);
    }

    private function contentTypes(): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->hojas as $i => $hoja) {
            $n = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .$overrides
            .'</Types>';
    }

    private function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function relsLibro(): string
    {
        $rels = '';
        foreach ($this->hojas as $i => $hoja) {
            $id = $i + 1;
            $rels .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
        }
        $estilo = count($this->hojas) + 1;
        $rels .= '<Relationship Id="rId'.$estilo.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function nombreHoja(string $nombre): string
    {
        $nombre = str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $nombre);
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = 'Hoja';
        }

        return mb_substr($nombre, 0, 31);
    }

    private function limpiar(): void
    {
        foreach (glob($this->dir.DIRECTORY_SEPARATOR.'*') ?: [] as $archivo) {
            if (is_file($archivo)) {
                unlink($archivo);
            }
        }
        @rmdir($this->dir);
    }
}
