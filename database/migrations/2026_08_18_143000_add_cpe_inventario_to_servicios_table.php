<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->string('cpe_acceso', 8)->nullable()->after('tr069_product_class');
            $table->string('cpe_onu', 32)->nullable()->after('cpe_acceso');
            $table->string('cpe_router', 32)->nullable()->after('cpe_onu');
            $table->string('cpe_antena', 32)->nullable()->after('cpe_router');
            $table->string('cpe_notas', 120)->nullable()->after('cpe_antena');
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['cpe_acceso', 'cpe_onu', 'cpe_router', 'cpe_antena', 'cpe_notas']);
        });
    }
};
