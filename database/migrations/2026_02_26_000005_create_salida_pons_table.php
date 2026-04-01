<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salida_pons', function (Blueprint $table) {
            $table->increments('salida_pon_id');
            $table->unsignedInteger('nodo_id');
            $table->unsignedInteger('caja_nap_id')->nullable(); // puede ser en nodo o en caja
            $table->string('codigo', 50); // ej: PON-1, PON-2
            $table->unsignedTinyInteger('puerto')->default(1);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('estado', 20)->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('nodo_id')->references('nodo_id')->on('nodos');
            $table->foreign('caja_nap_id')->references('caja_nap_id')->on('caja_naps')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salida_pons');
    }
};
