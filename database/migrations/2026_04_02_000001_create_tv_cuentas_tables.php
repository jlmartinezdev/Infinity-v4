<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->nullable()->comment('Etiqueta interna');
            $table->string('usuario_app', 255);
            $table->text('password');
            $table->date('vencimiento_pago');
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('tv_cuenta_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tv_cuenta_id')->constrained('tv_cuentas')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->unique(['tv_cuenta_id', 'cliente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_cuenta_asignaciones');
        Schema::dropIfExists('tv_cuentas');
    }
};
