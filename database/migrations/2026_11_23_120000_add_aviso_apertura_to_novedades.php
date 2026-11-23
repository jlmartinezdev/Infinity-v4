<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('novedades', function (Blueprint $table) {
            $table->boolean('mostrar_al_abrir')->default(false)->after('activa');
            $table->unsignedTinyInteger('segundos_al_abrir')->nullable()->after('mostrar_al_abrir');
        });
    }

    public function down(): void
    {
        Schema::table('novedades', function (Blueprint $table) {
            $table->dropColumn(['mostrar_al_abrir', 'segundos_al_abrir']);
        });
    }
};
