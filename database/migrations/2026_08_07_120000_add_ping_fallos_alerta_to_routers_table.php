<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (! Schema::hasColumn('routers', 'ping_fallos_seguidos')) {
                $table->unsignedTinyInteger('ping_fallos_seguidos')->default(0)->after('ping_at');
            }
            if (! Schema::hasColumn('routers', 'ping_alerta_enviada')) {
                $table->boolean('ping_alerta_enviada')->default(false)->after('ping_fallos_seguidos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (Schema::hasColumn('routers', 'ping_alerta_enviada')) {
                $table->dropColumn('ping_alerta_enviada');
            }
            if (Schema::hasColumn('routers', 'ping_fallos_seguidos')) {
                $table->dropColumn('ping_fallos_seguidos');
            }
        });
    }
};
