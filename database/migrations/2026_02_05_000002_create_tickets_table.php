<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->unsignedInteger('pedido_id')->nullable();
            $table->foreignId('ticket_asunto_id')->constrained('ticket_asuntos')->cascadeOnDelete();
            $table->text('descripcion')->nullable();
            $table->string('estado', 30)->default('pendiente'); // pendiente, en_proceso, resuelto, cerrado, cancelado
            $table->unsignedInteger('usuario_id')->nullable()->comment('Usuario que crea el ticket');
            $table->unsignedInteger('asignado_id')->nullable()->comment('Técnico asignado');
            $table->text('observaciones')->nullable();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
            $table->foreign('pedido_id')->references('pedido_id')->on('pedidos')->nullOnDelete();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->foreign('asignado_id')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
