<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_recorrido_puntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->unsignedBigInteger('visita_id')->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
            $table->foreign('visita_id')->references('id')->on('tickets')->nullOnDelete();
            $table->index(['usuario_id', 'reported_at']);
            $table->index('reported_at');
        });

        Schema::create('staff_recorrido_resumenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->date('fecha');
            $table->unsignedInteger('puntos_n')->default(0);
            $table->decimal('distancia_metros', 12, 1)->default(0);
            $table->unsignedInteger('visitas')->default(0);
            $table->timestamp('inicio_at')->nullable();
            $table->timestamp('fin_at')->nullable();
            $table->decimal('lat_inicio', 10, 7)->nullable();
            $table->decimal('lng_inicio', 10, 7)->nullable();
            $table->decimal('lat_fin', 10, 7)->nullable();
            $table->decimal('lng_fin', 10, 7)->nullable();
            $table->json('traza');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
            $table->unique(['usuario_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_recorrido_resumenes');
        Schema::dropIfExists('staff_recorrido_puntos');
    }
};
