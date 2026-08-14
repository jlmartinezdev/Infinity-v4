<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_app_config', function (Blueprint $table) {
            $table->json('metodos_pago')->nullable()->after('pago_online');
        });
    }

    public function down(): void
    {
        Schema::table('portal_app_config', function (Blueprint $table) {
            $table->dropColumn('metodos_pago');
        });
    }
};
