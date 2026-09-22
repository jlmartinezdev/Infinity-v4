<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_electronica_listas', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 80);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->unique('nombre');
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::create('factura_electronica_lista_cliente', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('lista_id');
            $table->unsignedInteger('cliente_id');
            $table->timestamps();

            $table->unique(['lista_id', 'cliente_id']);
            $table->foreign('lista_id')
                ->references('id')
                ->on('factura_electronica_listas')
                ->cascadeOnDelete();
            $table->foreign('cliente_id')
                ->references('cliente_id')
                ->on('clientes')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_electronica_lista_cliente');
        Schema::dropIfExists('factura_electronica_listas');
    }
};
