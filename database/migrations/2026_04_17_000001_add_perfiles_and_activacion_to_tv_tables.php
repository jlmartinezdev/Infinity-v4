<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->string('perfil_1', 120)->nullable()->after('vencimiento_pago');
            $table->string('perfil_2', 120)->nullable()->after('perfil_1');
            $table->string('perfil_3', 120)->nullable()->after('perfil_2');
        });

        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            $table->unsignedTinyInteger('perfil_numero')->nullable()->after('cliente_id');
            $table->date('fecha_activacion')->nullable()->after('perfil_numero');
            $table->unique(['tv_cuenta_id', 'perfil_numero'], 'tv_cuenta_perfil_unico');
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            $table->dropUnique('tv_cuenta_perfil_unico');
            $table->dropColumn(['perfil_numero', 'fecha_activacion']);
        });

        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->dropColumn(['perfil_1', 'perfil_2', 'perfil_3']);
        });
    }
};
