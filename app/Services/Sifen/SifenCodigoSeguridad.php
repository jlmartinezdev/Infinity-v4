<?php

namespace App\Services\Sifen;

class SifenCodigoSeguridad
{
    /**
     * Genera dCodSeg: entero aleatorio de 9 dígitos (000000001–999999999).
     */
    public function generar(?int $numeroDocumento = null): int
    {
        do {
            $codigo = random_int(1, 999_999_999);
        } while (
            $numeroDocumento !== null
            && $codigo === $numeroDocumento
        );

        return $codigo;
    }
}
