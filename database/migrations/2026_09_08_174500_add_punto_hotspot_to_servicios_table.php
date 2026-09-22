<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servicios', 'punto_hotspot')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->boolean('punto_hotspot')->default(false)->after('ipv6_configurado');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('servicios', 'punto_hotspot')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('punto_hotspot');
        });
    }
};
