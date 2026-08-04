<?php

namespace Database\Seeders;

use App\Models\LoyaltyRegla;
use Illuminate\Database\Seeder;

class LoyaltyReglaSeeder extends Seeder
{
    public function run(): void
    {
        $puntosPorDia = [];
        for ($d = 1; $d <= LoyaltyRegla::DIAS_PAGO_CONFIGURABLES; $d++) {
            $puntosPorDia[(string) $d] = max(20, 120 - ($d * 20));
        }

        $reglas = [
            [
                'codigo' => 'bienvenida_app',
                'nombre' => 'Bienvenida app',
                'descripcion' => 'Puntos de bienvenida al primer acceso / alta portal',
                'puntos' => 100,
                'evento' => LoyaltyRegla::EVENTO_BIENVENIDA,
                'activa' => true,
                'condiciones' => null,
            ],
            [
                'codigo' => 'pago_recibido',
                'nombre' => 'Puntos por pago de servicio',
                'descripcion' => 'Solo factura tipo servicio. Puntos según día del mes de fecha_pago (configurable).',
                'puntos' => 100,
                'evento' => LoyaltyRegla::EVENTO_PAGO,
                'activa' => true,
                'condiciones' => LoyaltyRegla::condicionesPagoDesdeMapa($puntosPorDia),
            ],
        ];

        foreach ($reglas as $regla) {
            LoyaltyRegla::updateOrCreate(
                ['codigo' => $regla['codigo']],
                $regla
            );
        }
    }
}
