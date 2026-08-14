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
                'nombre' => 'Bono de bienvenida',
                'descripcion' => 'La primera vez que abrís la app',
                'puntos' => 500,
                'evento' => LoyaltyRegla::EVENTO_BIENVENIDA,
                'frecuencia' => LoyaltyRegla::FRECUENCIA_UNICA,
                'orden' => 1,
                'fase' => 1,
                'activa' => true,
                'visible_portal' => true,
                'condiciones' => null,
            ],
            [
                'codigo' => 'pago_recibido',
                'nombre' => 'Pago puntual',
                'descripcion' => 'Según el día del mes en que pagás tu factura de servicio',
                'puntos' => 100,
                'evento' => LoyaltyRegla::EVENTO_PAGO,
                'frecuencia' => LoyaltyRegla::FRECUENCIA_MENSUAL,
                'orden' => 10,
                'fase' => 3,
                'activa' => true,
                'visible_portal' => true,
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
