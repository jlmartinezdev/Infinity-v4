<?php

namespace App\Support;

use App\Models\HotspotPerfil;

/**
 * Qué hay que hacer en /ip/hotspot/user/profile del MikroTik para que coincida con los perfiles de Infinity.
 * No toca el router: arma el plan que después ejecuta MikroTikService.
 */
final class HotspotPerfilesMikrotik
{
    /** Perfiles propios del router que no se informan como sobrantes. */
    public const PROPIOS_DEL_ROUTER = ['default', 'default-encryption'];

    /**
     * Atributos que el router tiene que tener para este perfil. Vacío en Infinity = sin tope / sin límite;
     * "usuarios a la vez" vacío no se envía para respetar el valor del router.
     *
     * @return array<string, string>
     */
    public static function atributos(HotspotPerfil $perfil): array
    {
        $atributos = [
            'rate-limit' => trim((string) $perfil->rate_limit),
            'idle-timeout' => trim((string) $perfil->idle_timeout) ?: 'none',
            'session-timeout' => trim((string) $perfil->session_timeout) ?: '0s',
        ];
        $shared = trim((string) $perfil->shared_users);
        if ($shared !== '') {
            $atributos['shared-users'] = $shared;
        }

        return $atributos;
    }

    /**
     * @param  iterable<HotspotPerfil>  $perfiles
     * @param  list<array<string, mixed>>  $existentes  Filas de /ip/hotspot/user/profile/print
     * @return array{
     *     perfiles: list<array{nombre: string, accion: string, id: ?string, atributos: array<string, string>, cambios: list<string>}>,
     *     sobrantes: list<string>
     * }
     */
    public static function plan(iterable $perfiles, array $existentes): array
    {
        $porNombre = [];
        foreach ($existentes as $fila) {
            if (is_array($fila) && isset($fila['name'])) {
                $porNombre[(string) $fila['name']] = $fila;
            }
        }

        $items = [];
        $nombres = [];
        foreach ($perfiles as $perfil) {
            $nombre = trim((string) $perfil->nombre);
            if ($nombre === '') {
                continue;
            }
            $nombres[$nombre] = true;
            $deseados = self::atributos($perfil);
            $actual = $porNombre[$nombre] ?? null;

            if ($actual === null) {
                $items[] = [
                    'nombre' => $nombre,
                    'accion' => 'crear',
                    'id' => null,
                    // Al crear, lo vacío y los valores por defecto del router no hace falta enviarlos.
                    'atributos' => array_filter($deseados, fn ($v, $k) => ! self::esPorDefecto($k, $v), ARRAY_FILTER_USE_BOTH),
                    'cambios' => [],
                ];

                continue;
            }

            $cambios = [];
            foreach ($deseados as $clave => $valor) {
                if (! self::iguales($clave, $valor, (string) ($actual[$clave] ?? ''))) {
                    $cambios[] = $clave;
                }
            }
            $items[] = [
                'nombre' => $nombre,
                'accion' => $cambios === [] ? 'igual' : 'actualizar',
                'id' => isset($actual['.id']) ? (string) $actual['.id'] : null,
                'atributos' => array_intersect_key($deseados, array_flip($cambios)),
                'cambios' => $cambios,
            ];
        }

        $sobrantes = array_values(array_filter(
            array_keys($porNombre),
            fn (string $n) => ! isset($nombres[$n]) && ! in_array(strtolower($n), self::PROPIOS_DEL_ROUTER, true)
        ));
        sort($sobrantes);

        return ['perfiles' => $items, 'sobrantes' => $sobrantes];
    }

    public static function etiquetaCambio(string $clave): string
    {
        return [
            'rate-limit' => 'velocidad',
            'idle-timeout' => 'inactividad',
            'session-timeout' => 'duración de sesión',
            'shared-users' => 'usuarios a la vez',
        ][$clave] ?? $clave;
    }

    private static function iguales(string $clave, string $deseado, string $actual): bool
    {
        if ($clave === 'idle-timeout' || $clave === 'session-timeout') {
            return self::segundos($deseado) === self::segundos($actual);
        }

        return strtolower(trim($deseado)) === strtolower(trim($actual));
    }

    private static function esPorDefecto(string $clave, string $valor): bool
    {
        return $valor === ''
            || ($clave === 'idle-timeout' && self::segundos($valor) === null)
            || ($clave === 'session-timeout' && self::segundos($valor) === null);
    }

    /** "none", "0s" y vacío cuentan como sin límite. */
    private static function segundos(string $valor): ?int
    {
        $s = HotspotDuracion::segundos($valor);

        return $s !== null && $s > 0 ? $s : null;
    }
}
