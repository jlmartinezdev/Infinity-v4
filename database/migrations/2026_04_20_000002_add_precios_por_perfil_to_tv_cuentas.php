<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->decimal('precio_perfil_1', 10, 2)->nullable()->after('perfil_1');
            $table->decimal('precio_perfil_2', 10, 2)->nullable()->after('perfil_2');
            $table->decimal('precio_perfil_3', 10, 2)->nullable()->after('perfil_3');
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->dropColumn(['precio_perfil_1', 'precio_perfil_2', 'precio_perfil_3']);
        });
    }
};
