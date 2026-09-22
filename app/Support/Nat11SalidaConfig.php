<?php

namespace App\Support;

use App\Models\FacturacionParametro;

/**
 * Perfiles NAT 1:1 con failover Tigo / Ufinet.
 */
class Nat11SalidaConfig
{
    public const MODO_TIGO = 'tigo';

    public const MODO_UFINET = 'ufinet';

    public const DEDICADO = 'dedicado';

    public const SERVER = 'server';

    /**
     * @return list<string>
     */
    public static function destinos(): array
    {
        return [self::DEDICADO, self::SERVER];
    }

    /**
     * @return array{
     *     clave: string,
     *     nombre: string,
     *     privada: string,
     *     publica: string,
     *     tabla_tigo: string,
     *     tabla_ufinet: string,
     *     nat_salida: string,
     *     nat_entrada: string,
     *     publica_ufinet: ?string,
     *     nat_salida_ufinet: ?string,
     *     nat_entrada_ufinet: ?string,
     *     rule_tigo: string,
     *     rule_ufinet: ?string,
     *     match_src: bool,
     *     param: string,
     *     lan: list<array{dst: string, comment: string}>
     * }
     */
    public static function perfil(string $clave): array
    {
        if ($clave === self::SERVER) {
            return [
                'clave' => self::SERVER,
                'nombre' => 'PC Server',
                'privada' => '10.200.1.2',
                'publica' => '200.26.179.94',
                'tabla_tigo' => 'to_tigo',
                'tabla_ufinet' => 'to_ufinet',
                'nat_salida' => 'NAT 1:1 Server - SALIDA (OPTIMIZADO)',
                'nat_entrada' => 'NAT 1:1 Server - ENTRADA (OPTIMIZADO)',
                'publica_ufinet' => trim((string) config('services.cloudflare.origin_ufinet', '186.33.34.14')),
                'nat_salida_ufinet' => 'NAT 1:1 Server Ufinet - SALIDA',
                'nat_entrada_ufinet' => 'NAT 1:1 Server Ufinet - ENTRADA',
                'rule_tigo' => 'Server 1:1 Tigo',
                'rule_ufinet' => 'Server 1:1 Ufinet',
                'match_src' => false,
                'param' => 'server_salida_modo',
                'lan' => [
                    ['dst' => '10.0.0.0/8', 'comment' => 'Server 1:1 LAN 10'],
                    ['dst' => '172.16.0.0/12', 'comment' => 'Server 1:1 LAN 172'],
                    ['dst' => '172.255.0.0/16', 'comment' => 'Server 1:1 LAN loopback'],
                    ['dst' => '192.168.0.0/16', 'comment' => 'Server 1:1 LAN 192'],
                ],
            ];
        }

        return [
            'clave' => self::DEDICADO,
            'nombre' => 'Dedicado N1',
            'privada' => DedicadoSalidaConfig::PRIVADA,
            'publica' => DedicadoSalidaConfig::PUBLICA,
            'tabla_tigo' => DedicadoSalidaConfig::TABLA_TIGO,
            'tabla_ufinet' => 'to_ufinet',
            'nat_salida' => DedicadoSalidaConfig::NAT_SALIDA,
            'nat_entrada' => DedicadoSalidaConfig::NAT_ENTRADA,
            'publica_ufinet' => null,
            'nat_salida_ufinet' => null,
            'nat_entrada_ufinet' => null,
            'rule_tigo' => DedicadoSalidaConfig::RULE_COMMENT,
            'rule_ufinet' => null,
            'match_src' => true,
            'param' => 'dedicado_salida_modo',
            'lan' => [],
        ];
    }

    public static function modo(string $clave): string
    {
        $perfil = self::perfil($clave);
        $raw = trim((string) FacturacionParametro::obtener($perfil['param'], self::MODO_TIGO));

        return $raw === self::MODO_UFINET ? self::MODO_UFINET : self::MODO_TIGO;
    }

    public static function guardarModo(string $clave, string $modo): void
    {
        $perfil = self::perfil($clave);
        FacturacionParametro::establecer(
            $perfil['param'],
            $modo === self::MODO_UFINET ? self::MODO_UFINET : self::MODO_TIGO,
            'Salida '.$perfil['nombre'].': tigo (NAT 1:1) o ufinet (sin IP pública)'
        );
    }
}
