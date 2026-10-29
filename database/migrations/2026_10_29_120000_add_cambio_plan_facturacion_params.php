<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'clave' => 'dia_cambio_plan',
                'valor' => '20',
                'descripcion' => 'Día desde el cual un cambio de plan revisa la factura del mes',
            ],
            [
                'clave' => 'cambio_plan_prorrateo_antes',
                'valor' => '1',
                'descripcion' => 'Antes del día de cambio de plan, generar factura prorrateada',
            ],
            [
                'clave' => 'cambio_plan_editar_impaga',
                'valor' => '1',
                'descripcion' => 'Desde el día de cambio de plan, editar la factura impaga del mes',
            ],
            [
                'clave' => 'cambio_plan_omitir_si_pagada',
                'valor' => '1',
                'descripcion' => 'Desde el día de cambio de plan, no generar factura si el mes ya está pagado',
            ],
        ];

        foreach ($params as $p) {
            $existe = DB::table('facturacion_parametros')->where('clave', $p['clave'])->exists();
            if ($existe) {
                continue;
            }
            DB::table('facturacion_parametros')->insert($p + [
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('facturacion_parametros')->whereIn('clave', [
            'dia_cambio_plan',
            'cambio_plan_prorrateo_antes',
            'cambio_plan_editar_impaga',
            'cambio_plan_omitir_si_pagada',
        ])->delete();
    }
};
