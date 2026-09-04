<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('routers') && ! Schema::hasColumn('routers', 'ping_caido_desde')) {
            Schema::table('routers', function (Blueprint $table) {
                $table->timestamp('ping_caido_desde')->nullable()->after('ping_at');
            });
            DB::table('routers')
                ->where('estado', 'desconectado')
                ->whereNotNull('ping_at')
                ->whereNull('ping_caido_desde')
                ->update(['ping_caido_desde' => DB::raw('ping_at')]);
        }

        if (Schema::hasTable('nodo_aps_wireless') && ! Schema::hasColumn('nodo_aps_wireless', 'ping_caido_desde')) {
            Schema::table('nodo_aps_wireless', function (Blueprint $table) {
                $table->timestamp('ping_caido_desde')->nullable()->after('ping_at');
            });
            DB::table('nodo_aps_wireless')
                ->where('ping_ok', 0)
                ->whereNotNull('ping_at')
                ->whereNull('ping_caido_desde')
                ->update(['ping_caido_desde' => DB::raw('ping_at')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('routers', 'ping_caido_desde')) {
            Schema::table('routers', function (Blueprint $table) {
                $table->dropColumn('ping_caido_desde');
            });
        }
        if (Schema::hasColumn('nodo_aps_wireless', 'ping_caido_desde')) {
            Schema::table('nodo_aps_wireless', function (Blueprint $table) {
                $table->dropColumn('ping_caido_desde');
            });
        }
    }
};
