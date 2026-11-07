<?php

namespace App\Support;

use DateTimeImmutable;
use RuntimeException;

class XlsxHoja
{
    /** @var resource */
    private $out;

    private int $fila = 1;

    private int $filas = 0;

    private bool $cerrada = false;

    private string $ultimaColumna = 'A';

    /**
     * @param  list<string>  $encabezados
     * @param  list<'s'|'i'|'n'|'d'>  $tipos
     */
    public function __construct(
        string $archivo,
        array $encabezados,
        private array $tipos,
    ) {
        $out = fopen($archivo, 'wb');
        if ($out === false) {
            throw new RuntimeException('No se pudo escribir la hoja de Excel.');
        }
        $this->out = $out;

        $cols = count($encabezados);
        $this->ultimaColumna = self::columna($cols);
        fwrite($this->out, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        fwrite($this->out, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        fwrite($this->out, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        fwrite($this->out, '<cols><col min="1" max="'.$cols.'" width="18" customWidth="1"/></cols>');
        fwrite($this->out, '<sheetData>');
        $this->escribirFila($encabezados, true);
    }

    /**
     * @param  list<mixed>  $valores
     */
    public function agregar(array $valores): void
    {
        $this->escribirFila($valores, false);
        $this->filas++;
    }

    public function cerrar(): int
    {
        if ($this->cerrada) {
            return $this->filas;
        }

        $ultimaFila = $this->filas + 1;
        fwrite($this->out, '</sheetData>');
        fwrite($this->out, '<autoFilter ref="A1:'.$this->ultimaColumna.$ultimaFila.'"/>');
        fwrite($this->out, '</worksheet>');
        fclose($this->out);
        $this->cerrada = true;

        return $this->filas;
    }

    /**
     * @param  list<mixed>  $valores
     */
    private function escribirFila(array $valores, bool $encabezado): void
    {
        $n = $this->fila;
        fwrite($this->out, '<row r="'.$n.'">');
        foreach ($valores as $i => $valor) {
            $ref = self::columna($i + 1).$n;
            if ($encabezado) {
                fwrite($this->out, '<c r="'.$ref.'" t="inlineStr" s="1"><is><t>'.self::texto((string) $valor).'</t></is></c>');
                continue;
            }

            $tipo = $this->tipos[$i] ?? 's';
            if ($valor === null || $valor === '') {
                continue;
            }

            if ($tipo === 'i') {
                fwrite($this->out, '<c r="'.$ref.'" s="4"><v>'.(int) $valor.'</v></c>');
            } elseif ($tipo === 'n') {
                fwrite($this->out, '<c r="'.$ref.'" s="2"><v>'.self::numero($valor).'</v></c>');
            } elseif ($tipo === 'd') {
                $serial = self::serialFecha($valor);
                if ($serial === null) {
                    fwrite($this->out, '<c r="'.$ref.'" t="inlineStr"><is><t>'.self::texto((string) $valor).'</t></is></c>');
                } else {
                    fwrite($this->out, '<c r="'.$ref.'" s="3"><v>'.$serial.'</v></c>');
                }
            } else {
                fwrite($this->out, '<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'.self::texto((string) $valor).'</t></is></c>');
            }
        }
        fwrite($this->out, '</row>');
        $this->fila++;
    }

    private static function columna(int $indice): string
    {
        $texto = '';
        while ($indice > 0) {
            $indice--;
            $texto = chr(65 + ($indice % 26)).$texto;
            $indice = intdiv($indice, 26);
        }

        return $texto;
    }

    private static function texto(string $valor): string
    {
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $valor) ?? '';

        return htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function numero(mixed $valor): string
    {
        $numero = (float) $valor;
        if (! is_finite($numero)) {
            return '0';
        }

        $texto = rtrim(rtrim(number_format($numero, 4, '.', ''), '0'), '.');

        return $texto === '' || $texto === '-' ? '0' : $texto;
    }

    private static function serialFecha(mixed $valor): ?int
    {
        $texto = substr((string) $valor, 0, 10);
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $texto);
        if ($fecha === false) {
            return null;
        }

        $base = new DateTimeImmutable('1899-12-30');

        return (int) $base->diff($fecha)->format('%r%a');
    }
}
