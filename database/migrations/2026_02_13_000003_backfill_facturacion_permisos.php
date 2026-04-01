<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Agrega los nuevos permisos de facturación a usuarios que tenían facturas.ver.
     */
    public function up(): void
    {
        $users = DB::table('users')->whereNotNull('permisos')->get();

        foreach ($users as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }

            $agregar = [];

            if (in_array('facturas.ver', $permisos, true)) {
                $agregar[] = 'facturacion.ver';
                $agregar[] = 'factura-interna.ver';
                $agregar[] = 'cobros.ver';
                $agregar[] = 'pagos-pendientes.ver';
            }
            if (in_array('facturas.crear', $permisos, true)) {
                $agregar[] = 'cobros.crear';
                $agregar[] = 'factura-interna.crear';
            }
            if (in_array('facturas.eliminar', $permisos, true)) {
                $agregar[] = 'cobros.eliminar';
                $agregar[] = 'factura-interna.eliminar';
            }

            $agregar = array_unique($agregar);
            $merged = array_unique(array_merge($permisos, $agregar));

            if ($merged !== $permisos) {
                DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                    'permisos' => json_encode(array_values($merged)),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revertir - no podemos saber qué usuarios tenían los permisos antes
    }
};
