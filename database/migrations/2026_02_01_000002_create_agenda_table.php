<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agenda', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pedido_id');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();
            $table->unsignedInteger('usuario_id')->nullable()->comment('Técnico asignado');
            $table->string('estado', 20)->default('programado')
                ->comment('programado, en_progreso, completado, cancelado, no_asistio');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos');
            $table->foreign('usuario_id')->references('usuario_id')->on('users');
            $table->index(['fecha', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda');
    }
};
