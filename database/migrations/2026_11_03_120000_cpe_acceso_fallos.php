<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpe_acceso_fallos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('servicio_id');
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('via', 16);
            $table->string('ip', 45)->nullable();
            $table->string('error', 500);
            $table->timestamp('ultimo_at')->nullable();
            $table->timestamps();

            $table->unique(['servicio_id', 'via']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cpe_acceso_fallos');
    }
};
