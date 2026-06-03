<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sifen_configuracion', function (Blueprint $table) {
            $table->text('certificado_password')->nullable()->after('csc_token')
                ->comment('Contraseña del P12 cifrada (Crypt)');
        });
    }

    public function down(): void
    {
        Schema::table('sifen_configuracion', function (Blueprint $table) {
            $table->dropColumn('certificado_password');
        });
    }
};
