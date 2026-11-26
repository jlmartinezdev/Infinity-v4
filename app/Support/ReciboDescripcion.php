<?php

namespace App\Support;

use App\Models\Cobro;

/**
 * Descripción corta solo del pago adelantado (cobro sin factura):
 * nombre del plan, sin período ni importe. La app de TV queda como «Servicio especial».
 * Un cobro que paga facturas conserva el concepto guardado.
 */
class ReciboDescripcion
{
    public static function paraCobro(Cobro $cobro): string
    {
        $concepto = trim((string) ($cobro->concepto ?? ''));
        if ($concepto === '' || ! self::esPagoAdelantado($cobro)) {
            return $concepto;
        }

        return self::texto($concepto);
    }

    public static function esPagoAdelantado(Cobro $cobro): bool
    {
        if ($cobro->relationLoaded('facturaInternas') && $cobro->facturaInternas->isNotEmpty()) {
            return false;
        }
        if (! empty($cobro->factura_interna_id)) {
            return false;
        }
        if ($cobro->relationLoaded('facturaInternas') || ! $cobro->exists) {
            return true;
        }

        return ! $cobro->facturaInternas()->exists();
    }

    public static function texto(?string $concepto): string
    {
        $concepto = trim((string) $concepto);
        if ($concepto === '') {
            return '';
        }

        $partes = preg_split('/\s+\|\s+|\s+\/\s+/u', $concepto) ?: [];
        $limpios = [];
        foreach ($partes as $parte) {
            $linea = self::linea((string) $parte);
            if ($linea !== '' && ! in_array($linea, $limpios, true)) {
                $limpios[] = $linea;
            }
        }

        return implode(' / ', $limpios);
    }

    public static function linea(string $texto): string
    {
        $texto = trim($texto);
        if ($texto === '') {
            return '';
        }

        if (str_contains($texto, ' · ')) {
            $texto = trim((string) substr($texto, (int) strrpos($texto, ' · ') + strlen(' · ')));
        }

        if (preg_match('/^(.+?)\s+-\s+[\d.]+(?:,\d+)?\s*Gs\./iu', $texto, $coincide)) {
            $texto = trim($coincide[1]);
        }

        $texto = preg_replace('/\s+-\s+Desde\s+.+$/iu', '', $texto) ?? $texto;
        $texto = preg_replace('/\s+-\s+Per[ií]odo\s+.+$/iu', '', $texto) ?? $texto;
        $texto = trim($texto);

        if (preg_match('/^servicio especial$/iu', $texto)) {
            return 'Servicio especial';
        }

        return $texto;
    }
}
