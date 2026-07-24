<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'fecha_otorgamiento')) {
                $table->timestamp('fecha_otorgamiento')->nullable()->after('fecha_activacion_app');
            }
            if (! Schema::hasColumn('clientes', 'aprobado_por')) {
                $table->string('aprobado_por', 120)->nullable()->after('fecha_otorgamiento');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'push_token')) {
                $table->string('push_token', 512)->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('users', 'device_type')) {
                $table->string('device_type', 40)->nullable()->after('push_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            foreach (['fecha_otorgamiento', 'aprobado_por'] as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['push_token', 'device_type'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
