<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servicio_observaciones')) {
            return;
        }

        Schema::create('servicio_observaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('servicio_id');
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('motivo', 32);
            $table->string('equipo', 120)->nullable();
            $table->string('nota', 500)->nullable();
            $table->unsignedSmallInteger('horas');
            $table->timestamp('inicia_at');
            $table->timestamp('vence_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->string('resultado', 32)->nullable();
            $table->timestamps();

            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->index(['cerrada_at', 'vence_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_observaciones');
    }
};
