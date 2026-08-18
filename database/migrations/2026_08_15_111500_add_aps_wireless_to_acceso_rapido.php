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
        $codigoAviso = 'sistema-aps-wireless-avisos.ver';

        foreach (PermisosCatalogo::filasParaSeeder() as $p) {
            if (($p['codigo'] ?? '') !== $codigoAviso) {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $p['codigo']], $p);
        }

        $permisoId = Permiso::query()->where('codigo', $codigoAviso)->value('id');
        $admin = Rol::whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
        if ($admin && $permisoId) {
            $admin->permisos()->syncWithoutDetaching([$permisoId]);
        }

        foreach (DB::table('users')->whereNotNull('permisos')->cursor() as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }
            if (! in_array('sistema-aps-wireless.ver', $permisos, true)
                && ! in_array('sistema-red-monitoreo.ver', $permisos, true)) {
                continue;
            }
            if (in_array($codigoAviso, $permisos, true)) {
                continue;
            }
            $permisos[] = $codigoAviso;
            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'permisos' => json_encode(array_values($permisos)),
            ]);
        }

        $agregar = 'rapido-aps-wireless';
        $despuesDe = 'rapido-red-monitoreo';

        foreach (DB::table('users')->whereNotNull('acceso_rapido')->cursor() as $user) {
            $items = json_decode($user->acceso_rapido, true);
            if (! is_array($items)) {
                continue;
            }
            if (in_array($agregar, $items, true)) {
                continue;
            }

            $pos = array_search($despuesDe, $items, true);
            if ($pos === false) {
                $items[] = $agregar;
            } else {
                array_splice($items, $pos + 1, 0, [$agregar]);
            }

            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'acceso_rapido' => json_encode(array_values($items)),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('users')->whereNotNull('acceso_rapido')->cursor() as $user) {
            $items = json_decode($user->acceso_rapido, true);
            if (! is_array($items)) {
                continue;
            }
            $filtrado = array_values(array_filter($items, fn ($n) => $n !== 'rapido-aps-wireless'));
            if ($filtrado === array_values($items)) {
                continue;
            }
            DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                'acceso_rapido' => json_encode($filtrado),
            ]);
        }
    }
};
