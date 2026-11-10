<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fin_control_gasto_meses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('abierta');
            $table->foreignId('cuenta_id')->nullable()->constrained('fin_cuentas')->nullOnDelete();
            $table->decimal('importe', 15, 2)->nullable();
            $table->foreignId('fin_egreso_id')->nullable()->constrained('fin_egresos')->nullOnDelete();
            $table->unsignedInteger('abierto_por')->nullable();
            $table->timestamp('abierto_en')->nullable();
            $table->unsignedInteger('cerrado_por')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'mes']);
            $table->foreign('abierto_por')->references('usuario_id')->on('users')->nullOnDelete();
            $table->foreign('cerrado_por')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_control_gasto_meses');
    }
};
