<?php

namespace App\Support;

use App\Models\Olt;

/**
 * Desvío del OLT-UA (N6 Unión Agrícola) por el enlace dedicado en MK-N6-R1 ether2.
 */
class OltN6DesvioConfig
{
    public const OLT_CODIGO = 'OLT-UA';

    public const ROUTER_N6 = 'MK-N6-R1';

    public const ROUTER_N2 = 'MK-N2-R2';

    public const IFACE = 'ether2_aux_olt';

    public const IFACE_LAN_N2 = 'bridge_lan';

    public const BRIDGE_LAN_N6 = 'bridge-lan';

    public const IP_ACTUAL = '172.16.30.200';

    public const IP_DESVIO = '172.16.30.254/32';

    public const GW_N6_BACKBONE = '172.16.0.60';

    public const IP_NUEVA = '172.16.60.200';

    public const MASCARA_NUEVA = '255.255.255.0';

    public const GW_NUEVA = '172.16.60.1';

    public const COMMENT_ADDR = 'desvio OLT-UA ether2';

    public const COMMENT_RUTA_N6 = 'OLT-UA ether2 dedicado';

    public const COMMENT_RUTA_N2 = 'OLT-UA via N6 dedicado';

    public const COMMENT_ARP_N2 = 'OLT-UA desvio publicado';

    public static function aplica(Olt $olt): bool
    {
        $codigo = strtoupper(trim((string) $olt->codigo));
        $ip = trim((string) $olt->ip);

        return $codigo === self::OLT_CODIGO
            || $ip === self::IP_ACTUAL
            || $ip === self::IP_NUEVA;
    }
}
