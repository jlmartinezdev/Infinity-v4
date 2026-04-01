<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caja_naps', function (Blueprint $table) {
            $table->increments('caja_nap_id');
            $table->unsignedInteger('nodo_id');
            $table->string('codigo', 50)->unique();
            $table->string('descripcion', 255)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->enum('tipo', ['primaria', 'secundaria'])->default('secundaria');
            $table->string('estado', 20)->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_naps');
    }
};
