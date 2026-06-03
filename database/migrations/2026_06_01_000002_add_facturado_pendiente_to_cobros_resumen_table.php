<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cobros_resumen', function (Blueprint $table) {
            $table->decimal('total_facturado', 15, 2)->default(0)->after('mes');
            $table->decimal('total_pendiente', 15, 2)->default(0)->after('total_cobrado');
        });
    }

    public function down(): void
    {
        Schema::table('cobros_resumen', function (Blueprint $table) {
            $table->dropColumn(['total_facturado', 'total_pendiente']);
        });
    }
};
