<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de permisos y relación rol-permisos para control de acceso.
     */
    public function up(): void
    {
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique()->comment('Ej: clientes.ver, pedidos.crear');
            $table->string('nombre', 120);
            $table->string('categoria', 60)->default('General')->comment('Agrupa en la UI');
            $table->unsignedTinyInteger('orden')->default(0)->comment('Orden en listado');
            $table->timestamps();
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->unsignedInteger('rol_id');
            $table->unsignedBigInteger('permiso_id');
            $table->primary(['rol_id', 'permiso_id']);
            $table->foreign('rol_id')->references('rol_id')->on('roles')->cascadeOnDelete();
            $table->foreign('permiso_id')->references('id')->on('permisos')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rol_permiso');
        Schema::dropIfExists('permisos');
    }
};
