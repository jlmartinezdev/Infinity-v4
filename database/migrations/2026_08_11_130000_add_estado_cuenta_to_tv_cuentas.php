<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->string('estado_cuenta', 20)->default('activa')->after('aplicacion')
                ->comment('activa | inactiva | baja');
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->dropColumn('estado_cuenta');
        });
    }
};
