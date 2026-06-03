<?php

use App\Support\PermisosCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\PermisoSeeder', '--force' => true]);

        foreach (DB::table('users')->whereNotNull('permisos')->cursor() as $user) {
            $permisos = json_decode($user->permisos, true);
            if (! is_array($permisos)) {
                continue;
            }
            $migrados = PermisosCatalogo::migrarPermisos($permisos);
            if ($migrados !== $permisos) {
                DB::table('users')->where('usuario_id', $user->usuario_id)->update([
                    'permisos' => json_encode(array_values($migrados)),
                ]);
            }
        }
    }

    public function down(): void
    {
        // No revertir permisos de usuarios
    }
};
