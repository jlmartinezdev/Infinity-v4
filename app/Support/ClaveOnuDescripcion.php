<?php

namespace App\Support;

/**
 * Iguala un usuario PPPoE con la descripción de la ONU.
 * Da lo mismo si uno lleva guion, guion bajo o espacio y el otro no, o si hay tilde o ñ.
 * Un agregado final como -ESCUELA, _CASA o -Yuty tampoco separa al mismo cliente.
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
     * La clave del texto y, si trae una marca al final, también la clave sin esa marca.
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
        foreach (self::sinMarcaFinal($valor) as $variante) {
            $clave = self::de($variante);
            if (mb_strlen($clave) >= 10) {
                $claves[] = $clave;
            }
        }

        return array_values(array_unique(array_filter($claves)));
    }

    /**
     * @return list<string>
     */
    private static function sinMarcaFinal(string $valor): array
    {
        $variantes = [];
        $sinServicio = preg_replace('/(?:[_\-\s]+SERVICIO\s*2|[_\-\s]+2)$/iu', '', $valor) ?? $valor;
        if ($sinServicio !== $valor) {
            $variantes[] = $sinServicio;
        }

        $sinGuion = preg_replace('/\s*-\s*[A-Za-z0-9]+$/u', '', $valor) ?? $valor;
        if ($sinGuion !== $valor) {
            $variantes[] = $sinGuion;
        }

        $sinLugar = preg_replace('/(?:[_\-\s]+)(?:ESCUELA|CASA|YUTY)$/iu', '', $valor) ?? $valor;
        if ($sinLugar !== $valor) {
            $variantes[] = $sinLugar;
        }

        return $variantes;
    }
}
