<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_puertos', function (Blueprint $table) {
            $table->increments('olt_puerto_id');
            $table->unsignedInteger('olt_id');
            $table->unsignedSmallInteger('numero');
            $table->string('tipo_pon', 20)->default('GPON');
            $table->string('estado', 20)->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('olt_id')->references('olt_id')->on('olts')->onDelete('cascade');
            $table->unique(['olt_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_puertos');
    }
};
