<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_avisos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->string('cuerpo', 500);
            $table->string('tipo', 40)->default('aviso'); // aviso|promocion
            $table->string('destino', 20); // todos|seleccionados
            $table->json('cliente_ids')->nullable();
            $table->unsignedInteger('total_destinatarios')->default(0);
            $table->unsignedInteger('enviados')->default(0);
            $table->unsignedInteger('fallidos')->default(0);
            $table->unsignedInteger('omitidos')->default(0);
            $table->unsignedInteger('creado_por')->nullable();
            $table->timestamps();

            $table->foreign('creado_por')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_avisos');
    }
};
