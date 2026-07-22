<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoreo_ping_servicios', function (Blueprint $table) {
            $table->unsignedInteger('servicio_id')->primary();
            $table->unsignedInteger('cliente_id')->index();
            $table->string('ip', 15);
            $table->boolean('en_linea')->default(false);
            $table->unsignedSmallInteger('latencia_ms')->nullable();
            $table->timestamp('verificado_at')->nullable()->index();
            $table->string('error', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoreo_ping_servicios');
    }
};
