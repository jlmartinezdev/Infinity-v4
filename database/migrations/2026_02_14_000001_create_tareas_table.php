<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente, en_progreso, completado
            $table->string('prioridad', 20)->nullable(); // baja, media, alta
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedInteger('usuario_id')->nullable()->comment('Usuario que crea la tarea');
            $table->unsignedInteger('asignado_id')->nullable()->comment('Usuario asignado');
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->foreign('asignado_id')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};
