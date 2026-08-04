<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            if (! Schema::hasColumn('premios', 'tipo')) {
                $table->string('tipo', 30)->default('fisico')->after('descripcion')
                    ->comment('fisico|producto|retiro|descuento_factura');
            }
            if (! Schema::hasColumn('premios', 'descuento_porcentaje')) {
                $table->decimal('descuento_porcentaje', 5, 2)->nullable()->after('puntos_requeridos');
            }
            if (! Schema::hasColumn('premios', 'descuento_monto')) {
                $table->decimal('descuento_monto', 14, 2)->nullable()->after('descuento_porcentaje');
            }
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            if (Schema::hasColumn('premios', 'tipo')) {
                $table->dropIndex(['tipo']);
                $table->dropColumn('tipo');
            }
            if (Schema::hasColumn('premios', 'descuento_porcentaje')) {
                $table->dropColumn('descuento_porcentaje');
            }
            if (Schema::hasColumn('premios', 'descuento_monto')) {
                $table->dropColumn('descuento_monto');
            }
        });
    }
};
