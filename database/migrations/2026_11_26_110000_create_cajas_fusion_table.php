<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas_fusion', function (Blueprint $table) {
            $table->increments('caja_fusion_id');
            $table->string('codigo', 60)->unique();
            $table->string('ubicacion', 200)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas_fusion');
    }
};
