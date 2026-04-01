<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olts', function (Blueprint $table) {
            $table->increments('olt_id');
            $table->unsignedInteger('nodo_id');
            $table->unsignedInteger('olt_marca_id');
            $table->string('modelo', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->unsignedSmallInteger('cantidad_puertos')->default(8);
            $table->string('tipo_pon', 20)->default('GPON'); // GPON, EPON, XG-PON
            $table->string('estado', 20)->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos')->onDelete('cascade');
            $table->foreign('olt_marca_id')->references('olt_marca_id')->on('olt_marcas')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
