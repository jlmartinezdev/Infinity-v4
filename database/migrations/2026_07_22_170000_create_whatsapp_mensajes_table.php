<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->enum('direccion', ['entrada', 'salida'])->index();
            $table->string('telefono', 20)->index();
            $table->string('tipo', 40)->default('text')->index();
            $table->text('cuerpo')->nullable();
            $table->string('template_name', 120)->nullable();
            $table->string('template_language', 20)->nullable();
            $table->string('wamid', 128)->nullable()->unique();
            $table->string('estado', 30)->default('pendiente')->index();
            $table->string('error_code', 40)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('cliente_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->string('contexto_tipo', 60)->nullable()->index();
            $table->unsignedBigInteger('contexto_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->index(['contexto_tipo', 'contexto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_mensajes');
    }
};
