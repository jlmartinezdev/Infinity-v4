<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cobros_resumen', function (Blueprint $table) {
            $table->id();
            $table->date('mes')->unique()->comment('Primer día del mes de referencia del ciclo');
            $table->decimal('total_cobrado', 15, 2)->default(0);
            $table->decimal('pago_adelantado', 15, 2)->default(0);
            $table->decimal('pago_atrasado', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobros_resumen');
    }
};
