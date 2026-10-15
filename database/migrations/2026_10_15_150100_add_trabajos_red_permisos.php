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
            'trabajos-red.ver',
            'trabajos-red.crear',
            'trabajos-red.editar',
            'trabajos-red.eliminar',
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

        $tecnico = Rol::whereRaw('LOWER(descripcion) = ?', ['técnico'])->first();
        $idsTecnico = Permiso::query()
            ->whereIn('codigo', ['trabajos-red.ver', 'trabajos-red.crear', 'trabajos-red.editar'])
            ->pluck('id')
            ->all();
        if ($tecnico && $idsTecnico !== []) {
            $tecnico->permisos()->syncWithoutDetaching($idsTecnico);
        }

        $paraStaff = ['trabajos-red.ver', 'trabajos-red.crear', 'trabajos-red.editar'];
        foreach (DB::table('users')->whereNotNull('permisos')->cursor() as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }
            $veRed = in_array('tickets.ver', $permisos, true) || in_array('ftth-olts.ver', $permisos, true);
            if (! $veRed) {
                continue;
            }
            $cambio = false;
            foreach ($paraStaff as $codigo) {
                if (! in_array($codigo, $permisos, true)) {
                    $permisos[] = $codigo;
                    $cambio = true;
                }
            }
            if ($cambio) {
                DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                    'permisos' => json_encode(array_values($permisos)),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No quitar permisos de usuarios.
    }
};
