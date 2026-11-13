<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_recorrido_ajustes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->date('fecha');
            $table->json('traza');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
            $table->unique(['usuario_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_recorrido_ajustes');
    }
};
