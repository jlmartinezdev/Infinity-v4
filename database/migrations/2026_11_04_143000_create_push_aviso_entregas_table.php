<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_aviso_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('push_aviso_id')->constrained('push_avisos')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->string('estado', 20); // enviado | fallido | omitido
            $table->string('motivo', 180)->nullable();
            $table->timestamps();

            $table->index(['push_aviso_id', 'estado']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_aviso_entregas');
    }
};
