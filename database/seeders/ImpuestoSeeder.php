<?php

namespace Database\Seeders;

use App\Models\Impuesto;
use Illuminate\Database\Seeder;

class ImpuestoSeeder extends Seeder
{
    /**
     * Impuestos típicos de Paraguay (IVA 10%, IVA 5%, Exento).
     */
    public function run(): void
    {
        $impuestos = [
            ['codigo' => 'IVA10', 'nombre' => 'IVA 10%', 'porcentaje' => 10, 'descripcion' => 'IVA general Paraguay'],
            ['codigo' => 'IVA5', 'nombre' => 'IVA 5%', 'porcentaje' => 5, 'descripcion' => 'IVA reducido'],
            ['codigo' => 'EXENTO', 'nombre' => 'Exento', 'porcentaje' => 0, 'descripcion' => 'Sin impuesto'],
        ];

        foreach ($impuestos as $row) {
            Impuesto::updateOrCreate(
                ['codigo' => $row['codigo']],
                array_merge($row, ['activo' => true])
            );
        }
    }
}
