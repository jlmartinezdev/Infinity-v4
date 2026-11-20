<?php

namespace App\Support;

use App\Models\FacturacionParametro;

/**
 * Salida del dedicado 250M (10.200.4.2 ↔ 200.26.179.93).
 */
class DedicadoSalidaConfig
{
    public const MODO_TIGO = 'tigo';

    public const MODO_UFINET = 'ufinet';

    public const PRIVADA = '10.200.4.2';

    public const PUBLICA = '200.26.179.93';

    public const TABLA_TIGO = 'to_tigo';

    public const NAT_SALIDA = 'NAT 1:1 Dedicado 250M - SALIDA';

    public const NAT_ENTRADA = 'NAT 1:1 Dedicado 250M - ENTRADA';

    public const RULE_COMMENT = 'Dedicado 250M 1:1 Tigo';

    public static function modo(): string
    {
        $raw = trim((string) FacturacionParametro::obtener('dedicado_salida_modo', self::MODO_TIGO));

        return $raw === self::MODO_UFINET ? self::MODO_UFINET : self::MODO_TIGO;
    }

    public static function guardarModo(string $modo): void
    {
        FacturacionParametro::establecer(
            'dedicado_salida_modo',
            $modo === self::MODO_UFINET ? self::MODO_UFINET : self::MODO_TIGO,
            'Salida del dedicado 250M: tigo (NAT 1:1) o ufinet (sin IP pública)'
        );
    }
}
