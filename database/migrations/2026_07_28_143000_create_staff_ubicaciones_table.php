<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id')->unique();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->boolean('en_turno')->default(true);
            $table->unsignedBigInteger('visita_id')->nullable();
            $table->timestamp('reported_at');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
            $table->foreign('visita_id')->references('id')->on('tickets')->nullOnDelete();
            $table->index(['en_turno', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_ubicaciones');
    }
};
