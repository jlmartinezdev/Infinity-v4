<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_internas', function (Blueprint $table) {
            $table->decimal('descuento', 15, 2)->default(0)->after('total');
            $table->date('fecha_pago')->nullable()->after('fecha_vencimiento');
        });
    }

    public function down(): void
    {
        Schema::table('factura_internas', function (Blueprint $table) {
            $table->dropColumn(['descuento', 'fecha_pago']);
        });
    }
};
