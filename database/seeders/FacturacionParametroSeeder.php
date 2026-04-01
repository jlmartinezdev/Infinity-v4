<?php

namespace Database\Seeders;

use App\Models\FacturacionParametro;
use Illuminate\Database\Seeder;

class FacturacionParametroSeeder extends Seeder
{
    public function run(): void
    {
        $params = [
            [
                'clave' => 'dias_vencimiento_factura',
                'valor' => '10',
                'descripcion' => 'Días hasta vencimiento de la factura (desde fecha emisión)',
            ],
            [
                'clave' => 'dias_para_suspender',
                'valor' => '5',
                'descripcion' => 'Días después del vencimiento para suspender servicio por falta de pago',
            ],
        ];

        foreach ($params as $p) {
            FacturacionParametro::updateOrCreate(
                ['clave' => $p['clave']],
                ['valor' => $p['valor'], 'descripcion' => $p['descripcion']]
            );
        }
    }
}
