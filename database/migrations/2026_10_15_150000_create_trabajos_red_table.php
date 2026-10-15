<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajos_red', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->string('titulo', 180);
            $table->text('descripcion')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->string('prioridad', 20)->default('media');
            $table->unsignedInteger('nodo_id');
            $table->string('activo_tipo', 30);
            $table->unsignedInteger('activo_id');
            $table->unsignedInteger('usuario_id')->nullable();
            $table->unsignedInteger('asignado_id')->nullable();
            $table->unsignedInteger('actualizado_por_id')->nullable();
            $table->string('imagen', 255)->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos')->restrictOnDelete();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->foreign('asignado_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->foreign('actualizado_por_id')->references('usuario_id')->on('users')->nullOnDelete();

            $table->index('estado');
            $table->index('prioridad');
            $table->index(['activo_tipo', 'activo_id']);
            $table->index('asignado_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_red');
    }
};
