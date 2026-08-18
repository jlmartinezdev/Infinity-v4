<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodo_aps_wireless', function (Blueprint $table) {
            $table->increments('ap_id');
            $table->unsignedInteger('nodo_id');
            $table->string('nombre', 120);
            $table->string('ip', 45);
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->boolean('ping_ok')->nullable();
            $table->unsignedInteger('ping_latencia_ms')->nullable();
            $table->timestamp('ping_at')->nullable();
            $table->string('ping_error', 255)->nullable();
            $table->unsignedSmallInteger('ping_fallos_seguidos')->default(0);

            $table->string('hostname', 120)->nullable();
            $table->string('ssid', 120)->nullable();
            $table->string('modo', 40)->nullable();
            $table->string('frecuencia', 20)->nullable();
            $table->string('canal', 20)->nullable();
            $table->string('chanbw', 20)->nullable();
            $table->string('firmware', 80)->nullable();
            $table->string('modelo', 80)->nullable();
            $table->string('mac', 32)->nullable();
            $table->unsignedInteger('uptime_segundos')->nullable();
            $table->unsignedSmallInteger('estaciones')->nullable();
            $table->timestamp('ssh_at')->nullable();
            $table->string('ssh_error', 255)->nullable();
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->unique('ip');
            $table->index(['nodo_id', 'activo']);
            $table->foreign('nodo_id')->references('nodo_id')->on('nodos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodo_aps_wireless');
    }
};
