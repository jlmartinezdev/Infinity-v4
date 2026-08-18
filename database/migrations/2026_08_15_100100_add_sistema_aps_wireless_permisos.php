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
        $codigos = [
            'sistema-aps-wireless.ver',
            'sistema-aps-wireless.crear',
            'sistema-aps-wireless.editar',
            'sistema-aps-wireless.eliminar',
        ];

        foreach (PermisosCatalogo::filasParaSeeder() as $p) {
            if (! in_array($p['codigo'] ?? '', $codigos, true)) {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        $ids = Permiso::query()->whereIn('codigo', $codigos)->pluck('id')->all();

        $admin = Rol::whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
        if ($admin && $ids !== []) {
            $admin->permisos()->syncWithoutDetaching($ids);
        }

        foreach (DB::table('users')->whereNotNull('permisos')->cursor() as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }
            if (! in_array('sistema-red-monitoreo.ver', $permisos, true)
                && ! in_array('sistema-routers.ver', $permisos, true)) {
                continue;
            }
            $changed = false;
            foreach ($codigos as $codigo) {
                if (! in_array($codigo, $permisos, true)) {
                    $permisos[] = $codigo;
                    $changed = true;
                }
            }
            if (! $changed) {
                continue;
            }
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
