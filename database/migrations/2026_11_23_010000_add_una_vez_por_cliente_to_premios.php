<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            $table->boolean('una_vez_por_cliente')->default(false)->after('tope_anual_por_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            $table->dropColumn('una_vez_por_cliente');
        });
    }
};
