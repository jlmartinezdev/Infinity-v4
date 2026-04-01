<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aumenta el límite del campo concepto para mostrar descripciones completas
     * de varias facturas (ej. PLAN BASICO 10 Mbps - 100.000 Gs. - 17/02/2026 a 28/02/2026 | ...).
     */
    public function up(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->string('concepto', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->string('concepto', 100)->nullable()->change();
        });
    }
};
