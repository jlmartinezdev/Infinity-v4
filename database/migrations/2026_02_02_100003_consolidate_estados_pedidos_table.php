<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración consolidada: tabla estados_pedidos (solo añadir columnas).
     * La tabla se crea en create_isp_tables. Reemplaza: add_rol_id, add_parametro.
     */
    public function up(): void
    {
        Schema::table('estados_pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('estados_pedidos', 'rol_id')) {
                $table->unsignedInteger('rol_id')->nullable()->after('descripcion');
                $table->foreign('rol_id')->references('rol_id')->on('roles')->onDelete('set null');
            }
            if (!Schema::hasColumn('estados_pedidos', 'parametro')) {
                $table->text('parametro')->nullable()->after('rol_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estados_pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('estados_pedidos', 'rol_id')) {
                $table->dropForeign(['rol_id']);
                $table->dropColumn('rol_id');
            }
            if (Schema::hasColumn('estados_pedidos', 'parametro')) {
                $table->dropColumn('parametro');
            }
        });
    }
};
