<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'ultimo_ingreso')) {
                $table->timestamp('ultimo_ingreso')->nullable()->after('calificacion_pago');
            }
            if (! Schema::hasColumn('clientes', 'dispositivo')) {
                $table->string('dispositivo', 120)->nullable()->after('ultimo_ingreso');
            }
            if (! Schema::hasColumn('clientes', 'app_version')) {
                $table->string('app_version', 40)->nullable()->after('dispositivo');
            }
            if (! Schema::hasColumn('clientes', 'app_activa')) {
                $table->boolean('app_activa')->default(false)->after('app_version');
            }
            if (! Schema::hasColumn('clientes', 'fecha_activacion_app')) {
                $table->timestamp('fecha_activacion_app')->nullable()->after('app_activa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            foreach (['ultimo_ingreso', 'dispositivo', 'app_version', 'app_activa', 'fecha_activacion_app'] as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
