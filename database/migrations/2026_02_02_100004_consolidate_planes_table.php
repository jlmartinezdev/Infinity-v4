<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración consolidada: tabla planes (solo añadir columna prioridad).
     * La tabla se crea en create_isp_tables. Reemplaza: add_prioridad_to_planes_table.
     */
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            if (!Schema::hasColumn('planes', 'prioridad')) {
                $table->unsignedTinyInteger('prioridad')->default(2)->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            if (Schema::hasColumn('planes', 'prioridad')) {
                $table->dropColumn('prioridad');
            }
        });
    }
};
