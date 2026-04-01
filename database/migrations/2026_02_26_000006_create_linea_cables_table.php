<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linea_cables', function (Blueprint $table) {
            $table->increments('linea_cable_id');
            $table->unsignedInteger('fibra_color_id');
            $table->string('origen_tipo', 50); // nodo, caja_nap, splitter_primario, splitter_secundario, salida_pon
            $table->unsignedInteger('origen_id');
            $table->string('destino_tipo', 50);
            $table->unsignedInteger('destino_id');
            $table->decimal('longitud_metros', 8, 2)->nullable();
            $table->json('coordenadas')->nullable(); // [[lat,lon],[lat,lon],...] para dibujar polylines
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('fibra_color_id')->references('fibra_color_id')->on('fibra_colores');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linea_cables');
    }
};
