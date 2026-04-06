<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ajustes_generales', function (Blueprint $table) {
            $table->string('recibo_modo', 20)->default('con_grafico');
        });
    }

    public function down(): void
    {
        Schema::table('ajustes_generales', function (Blueprint $table) {
            $table->dropColumn('recibo_modo');
        });
    }
};
