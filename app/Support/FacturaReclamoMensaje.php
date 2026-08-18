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
            "Hola %s, te recordamos que tenés factura(s) vencida(s) con Interplus.\nCantidad de facturas: %d\nVencimiento: %s (vencido)\nSaldo pendiente: Gs. %s\nAdjuntamos el resumen de tu deuda. Regularizá tu pago para evitar la suspensión del servicio.",
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
