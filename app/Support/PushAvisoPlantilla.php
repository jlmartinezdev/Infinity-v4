<?php

namespace App\Support;

/**
 * Reemplazo de variables [token] en título y cuerpo de un aviso push.
 * El historial guarda la plantilla; el texto final se arma por cliente al enviar.
 */
class PushAvisoPlantilla
{
    /**
     * @return array<string, string> token => ayuda para el panel
     */
    public static function variables(): array
    {
        return [
            'nombre_completo' => 'Nombre y apellido',
            'nombre' => 'Nombre',
            'apellido' => 'Apellido',
            'saldo_pend' => 'Saldo pendiente total en guaraníes, sin Gs. (ej. 150.000)',
            'facturas' => 'Cantidad de facturas con saldo',
            'vencimiento' => 'Vencimiento más antiguo de las facturas con saldo (dd/mm/aaaa)',
            'cedula' => 'Cédula o RUC',
        ];
    }

    /**
     * @param  array<string, string|int|float|null>  $valores
     */
    public static function aplicar(string $texto, array $valores): string
    {
        $claves = array_keys(self::variables());
        usort($claves, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        $buscar = [];
        $reemplazar = [];
        foreach ($claves as $clave) {
            $buscar[] = '['.$clave.']';
            $reemplazar[] = (string) ($valores[$clave] ?? '');
        }

        return str_ireplace($buscar, $reemplazar, $texto);
    }
}
