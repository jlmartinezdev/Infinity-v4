<?php

namespace App\Support;

use App\Models\Factura;

/**
 * Formato de cédula/RUC paraguayo admitido al enviar el DE a SIFEN.
 * Solo: 1234567, 1234567-8 o 12345678-9. Sin puntos ni otros caracteres.
 */
final class DocumentoParaguaySifen
{
    public static function esValido(?string $documento): bool
    {
        $doc = trim((string) $documento);

        return $doc !== '' && preg_match('/^(\d{7}|\d{7}-\d|\d{8}-\d)$/', $doc) === 1;
    }

    /**
     * @return array{ok: bool, documento: string, message: ?string}
     */
    public static function evaluar(?string $documento): array
    {
        $doc = trim((string) $documento);
        if (self::esValido($doc)) {
            return [
                'ok' => true,
                'documento' => $doc,
                'message' => null,
            ];
        }

        $mostrado = $doc === '' ? '(vacío)' : '«'.$doc.'»';

        return [
            'ok' => false,
            'documento' => $doc,
            'message' => 'No se puede enviar a SIFEN: el documento '.$mostrado
                .' no es válido. Use XXXXXXX, XXXXXXX-X o XXXXXXXX-X, sin puntos ni otros caracteres.',
        ];
    }

    /**
     * @return array{ok: bool, documento: string, message: ?string}
     */
    public static function evaluarFactura(Factura $factura): array
    {
        $factura->loadMissing('cliente');

        return self::evaluar($factura->receptorDocumentoEfectivo());
    }
}
