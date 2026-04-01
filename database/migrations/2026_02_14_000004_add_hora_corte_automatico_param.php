<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('facturacion_parametros')->updateOrInsert(
            ['clave' => 'hora_corte_automatico'],
            [
                'valor' => '00:01',
                'descripcion' => 'Hora del día para ejecutar corte automático por falta de pago (HH:MM)',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('facturacion_parametros')->where('clave', 'hora_corte_automatico')->delete();
    }
};
