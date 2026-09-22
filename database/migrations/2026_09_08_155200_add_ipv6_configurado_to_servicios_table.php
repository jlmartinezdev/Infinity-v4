<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servicios', 'ipv6_configurado')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->boolean('ipv6_configurado')->default(false)->after('ip');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('servicios', 'ipv6_configurado')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('ipv6_configurado');
        });
    }
};
