<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tv_cuentas')->where('aplicacion', 'nebula')->update(['aplicacion' => 'suffixed']);

        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->string('aplicacion', 20)->default('suffixed')->comment('suffixed = 3 perfiles; lumix = 4 pantallas sin nombre de perfil')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tv_cuentas')->where('aplicacion', 'suffixed')->update(['aplicacion' => 'nebula']);

        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->string('aplicacion', 20)->default('nebula')->comment('nebula = 3 perfiles; lumix = 4 pantallas sin nombre de perfil')->change();
        });
    }
};
