<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_operaciones_pendientes', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 64);
            $table->json('payload')->nullable();
            $table->text('error_ultimo')->nullable();
            $table->string('origen', 128)->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_operaciones_pendientes');
    }
};
