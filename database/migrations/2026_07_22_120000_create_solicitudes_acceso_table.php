<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_acceso', function (Blueprint $table) {
            $table->id();
            $table->string('cedula', 20)->index();
            $table->string('nombre', 200);
            $table->string('whatsapp', 30)->nullable();
            $table->text('direccion')->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('frente_path', 500)->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente')->index();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->unsignedInteger('aprobado_por')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
            $table->foreign('aprobado_por')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_acceso');
    }
};
