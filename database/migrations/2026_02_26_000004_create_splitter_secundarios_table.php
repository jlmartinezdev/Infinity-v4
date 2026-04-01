<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('splitter_secundarios', function (Blueprint $table) {
            $table->increments('splitter_secundario_id');
            $table->unsignedInteger('caja_nap_id');
            $table->unsignedInteger('splitter_primario_id');
            $table->string('codigo', 50);
            $table->string('ratio', 20)->default('1:4'); // 1:4, 1:8
            $table->unsignedTinyInteger('puerto_entrada')->default(1);
            $table->string('estado', 20)->default('activo');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('caja_nap_id')->references('caja_nap_id')->on('caja_naps')->onDelete('cascade');
            $table->foreign('splitter_primario_id')->references('splitter_primario_id')->on('splitter_primarios')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('splitter_secundarios');
    }
};
