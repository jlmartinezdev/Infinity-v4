<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('senal_lecturas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('inicio_at');
            $table->timestamp('fin_at')->nullable();
            $table->string('estado', 20)->default('corriendo');
            $table->boolean('parcial')->default(false);
            $table->unsignedInteger('fibra_objetivo')->default(0);
            $table->unsignedInteger('fibra_leidas')->default(0);
            $table->unsignedInteger('fibra_fallidas')->default(0);
            $table->unsignedInteger('antena_objetivo')->default(0);
            $table->unsignedInteger('antena_leidas')->default(0);
            $table->unsignedInteger('antena_fallidas')->default(0);
            $table->text('nota')->nullable();
            $table->timestamps();
        });

        Schema::create('senal_lectura_fallos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('senal_lectura_id')->constrained('senal_lecturas')->cascadeOnDelete();
            $table->unsignedBigInteger('servicio_id');
            $table->string('tipo', 16);
            $table->string('motivo', 40);
            $table->string('detalle', 500);
            $table->timestamps();

            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->cascadeOnDelete();
            $table->index(['senal_lectura_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('senal_lectura_fallos');
        Schema::dropIfExists('senal_lecturas');
    }
};
