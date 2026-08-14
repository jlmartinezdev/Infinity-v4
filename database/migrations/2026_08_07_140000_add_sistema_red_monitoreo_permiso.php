<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\PermisosCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (PermisosCatalogo::filasParaSeeder() as $p) {
            if (($p['codigo'] ?? '') !== 'sistema-red-monitoreo.ver') {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        $codigo = 'sistema-red-monitoreo.ver';
        $permisoId = Permiso::query()->where('codigo', $codigo)->value('id');

        $admin = Rol::whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
        if ($admin && $permisoId) {
            $admin->permisos()->syncWithoutDetaching([$permisoId]);
        }

        // Quienes ya veían routers conservan acceso al monitoreo.
        foreach (DB::table('users')->whereNotNull('permisos')->cursor() as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }
            if (! in_array('sistema-routers.ver', $permisos, true)) {
                continue;
            }
            if (in_array($codigo, $permisos, true)) {
                continue;
            }
            $permisos[] = $codigo;
            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'permisos' => json_encode(array_values($permisos)),
            ]);
        }
    }

    public function down(): void
    {
        // No quitar permisos de usuarios.
    }
};
