<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fibra_colores', function (Blueprint $table) {
            $table->increments('fibra_color_id');
            $table->string('nombre', 50);
            $table->string('codigo_hex', 7)->nullable(); // ej: #FF0000
            $table->string('codigo', 20)->nullable(); // ej: ROJO, AZUL
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fibra_colores');
    }
};
