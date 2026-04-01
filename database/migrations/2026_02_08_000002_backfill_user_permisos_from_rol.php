<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rellena el campo permisos de usuarios que lo tengan vacío con los permisos del rol.
     * Así el menú y las comprobaciones usan los checkboxes (permisos del usuario).
     */
    public function up(): void
    {
        $users = DB::table('users')->whereNotNull('rol_id')->get();
        foreach ($users as $user) {
            $permisosJson = $user->permisos ?? '';
            $permisosArray = $permisosJson ? (json_decode($permisosJson, true) ?: []) : [];
            if ($permisosArray !== [] && !empty($permisosArray)) {
                continue; // ya tiene permisos definidos
            }
            $codigos = DB::table('rol_permiso')
                ->join('permisos', 'permisos.id', '=', 'rol_permiso.permiso_id')
                ->where('rol_permiso.rol_id', $user->rol_id)
                ->pluck('permisos.codigo')
                ->toArray();
            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'permisos' => json_encode($codigos),
            ]);
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        // No revertir: no sabemos qué usuarios tenían permisos vacíos antes
    }
};
