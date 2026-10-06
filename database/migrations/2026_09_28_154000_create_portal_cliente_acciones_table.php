<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_cliente_acciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('servicio_id')->nullable();
            $table->string('tipo', 40);
            $table->string('origen', 20)->default('app');
            $table->string('source', 32)->nullable();
            $table->string('titulo', 160);
            $table->string('ssid', 32)->nullable();
            $table->string('wifi_id', 64)->nullable();
            $table->text('wifi_password')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['cliente_id', 'created_at']);
            $table->index(['servicio_id', 'created_at']);
            $table->foreign('cliente_id')
                ->references('cliente_id')
                ->on('clientes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_cliente_acciones');
    }
};
