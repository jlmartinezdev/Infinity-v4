<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasTable('tv_cuenta_asignaciones')) {
            $idx = DB::select("SHOW INDEX FROM tv_cuenta_asignaciones WHERE Key_name = 'tv_cuenta_servicio_unico'");
            if ($idx !== []) {
                Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
                    $table->dropUnique('tv_cuenta_servicio_unico');
                });
            }
        }

        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('tv_cuenta_asignaciones', 'es_promo')) {
                $table->boolean('es_promo')->default(false)->after('fecha_activacion');
            }
            if (! Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
                $table->decimal('precio_aplicado', 10, 2)->nullable()->after('es_promo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')) {
                $table->dropColumn('precio_aplicado');
            }
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'es_promo')) {
                $table->dropColumn('es_promo');
            }
        });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true) && Schema::hasTable('tv_cuenta_asignaciones')) {
            $idx = DB::select("SHOW INDEX FROM tv_cuenta_asignaciones WHERE Key_name = 'tv_cuenta_servicio_unico'");
            if ($idx === []) {
                Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
                    $table->unique(['tv_cuenta_id', 'servicio_id'], 'tv_cuenta_servicio_unico');
                });
            }
        }
    }
};
