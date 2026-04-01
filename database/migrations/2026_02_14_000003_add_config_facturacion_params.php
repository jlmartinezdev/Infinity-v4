<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'clave' => 'dia_creacion_factura_automatica',
                'valor' => '1',
                'descripcion' => 'Día del mes para creación automática de facturas internas (1-28)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clave' => 'notificacion_tipo_plataforma',
                'valor' => 'web',
                'descripcion' => 'Tipo de plataforma para notificaciones: web, email, ambas',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'clave' => 'notificacion_dias_antes',
                'valor' => '3',
                'descripcion' => 'Días antes del vencimiento para enviar recordatorio de pago',
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
            'dia_creacion_factura_automatica',
            'notificacion_tipo_plataforma',
            'notificacion_dias_antes',
        ])->delete();
    }
};
