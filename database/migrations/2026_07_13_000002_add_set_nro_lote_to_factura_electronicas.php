<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_electronicas', 'set_nro_lote')) {
                $table->string('set_nro_lote', 30)->nullable()->after('set_estado_envio');
            }
        });
    }

    public function down(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            if (Schema::hasColumn('factura_electronicas', 'set_nro_lote')) {
                $table->dropColumn('set_nro_lote');
            }
        });
    }
};
