<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_internas', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_internas', 'tipo_factura')) {
                $table->string('tipo_factura', 30)->default('servicio')->after('cliente_id')
                    ->comment('servicio = factura por período; servicio_especial = sin período ni vencimiento');
            }
        });

        if (Schema::hasColumn('factura_internas', 'periodo_desde')) {
            DB::statement('ALTER TABLE factura_internas MODIFY periodo_desde DATE NULL');
        }
        if (Schema::hasColumn('factura_internas', 'periodo_hasta')) {
            DB::statement('ALTER TABLE factura_internas MODIFY periodo_hasta DATE NULL');
        }
    }

    public function down(): void
    {
        Schema::table('factura_internas', function (Blueprint $table) {
            if (Schema::hasColumn('factura_internas', 'tipo_factura')) {
                $table->dropColumn('tipo_factura');
            }
        });
    }
};
