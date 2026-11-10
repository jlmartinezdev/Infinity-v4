<?php

namespace App\Support;

/**
 * Métodos de login del perfil hotspot MikroTik.
 * mac va primero para que el mismo celular, al pasar a otro hotspot
 * con el mismo nombre, entre sin volver a pedir documento y PIN.
 * La cookie se quita: wifi.interplus apunta a cada router por su lado
 * y la cookie de uno no sirve en el otro.
 */
class HotspotLoginBy
{
    public static function paraRoaming(string $actual): string
    {
        $metodos = array_values(array_unique(array_filter(array_map(
            static fn (string $metodo) => strtolower(trim($metodo)),
            explode(',', $actual !== '' ? $actual : 'http-chap')
        ), static fn (string $metodo) => $metodo !== '' && $metodo !== 'cookie')));

        foreach (['http-chap', 'http-pap'] as $metodo) {
            if (! in_array($metodo, $metodos, true)) {
                $metodos[] = $metodo;
            }
        }

        $resto = array_values(array_filter(
            $metodos,
            static fn (string $metodo) => $metodo !== 'mac'
        ));

        return implode(',', array_merge(['mac'], $resto));
    }
}
