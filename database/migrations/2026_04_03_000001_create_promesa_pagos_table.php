<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promesa_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_interna_id')->constrained('factura_internas')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->dateTime('vencimiento_at');
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('factura_interna_id');
            $table->index(['cliente_id', 'vencimiento_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promesa_pagos');
    }
};
