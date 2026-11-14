<?php

namespace App\Support;

/**
 * Iguala un usuario PPPoE con la descripción de la ONU.
 * Da lo mismo si uno lleva guion bajo y el otro no, o si hay tilde o ñ.
 */
class ClaveOnuDescripcion
{
    public static function de(string $valor): string
    {
        $valor = mb_strtoupper(trim($valor));
        $valor = strtr($valor, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        ]);

        return preg_replace('/[^A-Z0-9]+/u', '', $valor) ?? '';
    }

    /**
     * La clave del texto y, si trae un _2 o _SERVICIO2 al final, también la clave sin esa marca.
     *
     * @return list<string>
     */
    public static function claves(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return [];
        }

        $claves = [self::de($valor)];
        $sinMarca = preg_replace('/(?:[_\-\s]+SERVICIO\s*2|[_\-\s]+2)$/iu', '', $valor) ?? $valor;
        if ($sinMarca !== $valor) {
            $claves[] = self::de($sinMarca);
        }

        return array_values(array_unique(array_filter($claves)));
    }
}
