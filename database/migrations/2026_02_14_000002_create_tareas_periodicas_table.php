<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas_periodicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('accion', 100)->comment('Identificador de la acción a ejecutar');
            $table->text('resultado')->nullable()->comment('Último resultado de la ejecución');
            $table->string('estado', 30)->default('activo')->comment('activo, pausado, error');
            $table->timestamp('ultima_aplicacion')->nullable();
            $table->unsignedInteger('total_veces_aplicada')->default(0);
            $table->unsignedInteger('nodo_id')->nullable()->comment('Nodo asociado si la tarea es por nodo');
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas_periodicas');
    }
};
