<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (! Schema::hasColumn('routers', 'ping_latencia_ms')) {
                $table->unsignedInteger('ping_latencia_ms')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('routers', 'ping_at')) {
                $table->timestamp('ping_at')->nullable()->after('ping_latencia_ms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (Schema::hasColumn('routers', 'ping_at')) {
                $table->dropColumn('ping_at');
            }
            if (Schema::hasColumn('routers', 'ping_latencia_ms')) {
                $table->dropColumn('ping_latencia_ms');
            }
        });
    }
};
