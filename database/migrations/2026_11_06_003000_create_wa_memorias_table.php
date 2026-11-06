<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_memorias', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 160);
            $table->string('tipo', 40)->default('instruccion'); // instruccion|tv|producto|politica|banco
            $table->string('palabras', 255)->nullable();
            $table->text('cuerpo');
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('consumido_at')->nullable();
            $table->timestamps();

            $table->index(['activo', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_memorias');
    }
};
