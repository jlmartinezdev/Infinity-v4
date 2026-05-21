<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_interna_notas_credito', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_interna_id')->constrained('factura_internas')->cascadeOnDelete();
            $table->decimal('monto', 15, 2);
            $table->string('motivo', 500)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->timestamps();

            $table->index('factura_interna_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_interna_notas_credito');
    }
};
