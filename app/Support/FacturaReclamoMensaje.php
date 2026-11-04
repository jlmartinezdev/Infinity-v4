<?php

namespace App\Support;

class FacturaReclamoMensaje
{
    public static function cuerpo(
        string $saludo,
        int $cantidad,
        string $vencimiento,
        string $saldoFmt,
        ?string $urlPublica = null,
    ): string {
        $texto = sprintf(
            "Hola %s, le recordamos que tiene factura(s) vencida(s) con Interplus.\nCantidad de facturas: %d\nVencimiento: %s (vencido)\nSaldo pendiente: Gs. %s\nAdjuntamos el resumen de su deuda. Regularice su pago para evitar la suspensión del servicio.",
            $saludo,
            max(1, $cantidad),
            $vencimiento,
            $saldoFmt
        );

        if (filled($urlPublica)) {
            $texto .= "\n\nDescargar resumen: ".$urlPublica;
        }

        return $texto;
    }
}
