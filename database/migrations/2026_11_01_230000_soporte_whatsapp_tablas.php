<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_incidentes_zona', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('router_id')->nullable()->index();
            $table->string('host', 120)->nullable()->index();
            $table->string('origen', 32);
            $table->string('estado', 16)->default('abierto')->index();
            $table->string('resumen', 255)->nullable();
            $table->timestamp('abierto_at')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_casos', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->string('etiqueta', 40)->nullable()->index();
            $table->string('palabras', 255)->nullable();
            $table->text('texto');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('wa_oferta_envios', function (Blueprint $table) {
            $table->id();
            $table->string('telefono', 32)->index();
            $table->string('referencia', 80);
            $table->timestamps();
        });

        Schema::create('wa_impresora_reservas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->unsignedBigInteger('servicio_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('ip', 45);
            $table->string('mac', 32)->nullable();
            $table->string('hostname', 120)->nullable();
            $table->boolean('lease_fijo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_impresora_reservas');
        Schema::dropIfExists('wa_oferta_envios');
        Schema::dropIfExists('wa_casos');
        Schema::dropIfExists('wa_incidentes_zona');
    }
};
