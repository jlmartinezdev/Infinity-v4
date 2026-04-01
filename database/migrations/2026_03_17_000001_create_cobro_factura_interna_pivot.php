<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que un cobro se aplique a varias facturas internas.
     * Tabla pivot: cobro_id, factura_interna_id, monto (monto aplicado a esa factura).
     */
    public function up(): void
    {
        Schema::create('cobro_factura_interna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cobro_id')->constrained('cobros')->cascadeOnDelete();
            $table->foreignId('factura_interna_id')->constrained('factura_internas')->cascadeOnDelete();
            $table->decimal('monto', 15, 2)->comment('Monto de este cobro aplicado a esta factura');
            $table->timestamps();

            $table->unique(['cobro_id', 'factura_interna_id']);
        });

        // Migrar cobros existentes: los que tienen factura_interna_id pasan al pivot
        if (Schema::hasColumn('cobros', 'factura_interna_id')) {
            $cobros = DB::table('cobros')
                ->whereNotNull('factura_interna_id')
                ->get(['id', 'factura_interna_id', 'monto']);

            foreach ($cobros as $c) {
                DB::table('cobro_factura_interna')->insert([
                    'cobro_id' => $c->id,
                    'factura_interna_id' => $c->factura_interna_id,
                    'monto' => $c->monto,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobro_factura_interna');
    }
};
