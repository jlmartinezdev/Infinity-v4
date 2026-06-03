<?php

namespace App\Services\Sifen;

/**
 * Genera el CDC (Código de Control) de 44 dígitos según MT SIFEN v150.
 *
 * Estructura (43 dígitos + DV módulo 11):
 * Tipo DE(2) + RUC(8) + DV-RUC(1) + Est(3) + Punto(3) + Número(7)
 * + TipoCont(1) + Fecha(8) + TipoEmi(1) + CodSeg(9) + DV-CDC(1)
 */
class CdcGenerator
{
    public function generar(CdcParams $params): string
    {
        $base = $this->construirBase($params);
        $digitoVerificador = $this->calcularDigitoVerificador($base);

        return $base.$digitoVerificador;
    }

    public function construirBase(CdcParams $params): string
    {
        return sprintf(
            '%02d%s%d%s%s%s%d%s%d%s',
            $params->tipoDocumento,
            str_pad($params->ruc, 8, '0', STR_PAD_LEFT),
            $params->dvRuc,
            str_pad((string) $params->establecimiento, 3, '0', STR_PAD_LEFT),
            str_pad((string) $params->puntoExpedicion, 3, '0', STR_PAD_LEFT),
            str_pad((string) $params->numeroDocumento, 7, '0', STR_PAD_LEFT),
            $params->tipoContribuyente,
            $params->fechaEmision->format('Ymd'),
            $params->tipoEmision,
            str_pad((string) $params->codigoSeguridad, 9, '0', STR_PAD_LEFT),
        );
    }

    /**
     * Dígito verificador módulo 11 (Manual Técnico v150 §10.2).
     */
    public function calcularDigitoVerificador(string $base43): int
    {
        $cadena = preg_replace('/\s+/', '', $base43);
        $factor = 2;
        $suma = 0;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int) $cadena[$i] * $factor;
            $factor = $factor >= 11 ? 2 : $factor + 1;
        }

        $resto = $suma % 11;

        return $resto > 1 ? 11 - $resto : 0;
    }

    public function extraerDigitoVerificador(string $cdc): int
    {
        return (int) substr($cdc, -1);
    }
}
