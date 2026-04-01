<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'clave' => 'dia_fecha_cobro',
                'valor' => '1',
                'descripcion' => 'Día del mes para fecha de cobro (1-31)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clave' => 'dia_vencimiento',
                'valor' => '5',
                'descripcion' => 'Día del mes de vencimiento de la factura interna (1-31)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clave' => 'dia_corte',
                'valor' => '6',
                'descripcion' => 'Día del mes para ejecutar corte automático (1-31)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($params as $p) {
            DB::table('facturacion_parametros')->updateOrInsert(
                ['clave' => $p['clave']],
                $p
            );
        }
    }

    public function down(): void
    {
        DB::table('facturacion_parametros')->whereIn('clave', [
            'dia_fecha_cobro',
            'dia_vencimiento',
            'dia_corte',
        ])->delete();
    }
};
