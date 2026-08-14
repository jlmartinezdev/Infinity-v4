<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_aviso_notificaciones', function (Blueprint $table) {
            $table->dropForeign(['tv_cuenta_id']);
        });

        Schema::table('tv_aviso_notificaciones', function (Blueprint $table) {
            $table->dropUnique('tv_aviso_cuenta_venc_unique');
            $table->index('tv_cuenta_id');
            $table->foreign('tv_cuenta_id')
                ->references('id')
                ->on('tv_cuentas')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tv_aviso_notificaciones', function (Blueprint $table) {
            $table->dropForeign(['tv_cuenta_id']);
        });

        Schema::table('tv_aviso_notificaciones', function (Blueprint $table) {
            $table->dropIndex(['tv_cuenta_id']);
            $table->unique(['tv_cuenta_id', 'fecha_vencimiento'], 'tv_aviso_cuenta_venc_unique');
            $table->foreign('tv_cuenta_id')
                ->references('id')
                ->on('tv_cuentas')
                ->cascadeOnDelete();
        });
    }
};
