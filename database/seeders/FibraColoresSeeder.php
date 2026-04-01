<?php

namespace Database\Seeders;

use App\Models\FibraColor;
use Illuminate\Database\Seeder;

class FibraColoresSeeder extends Seeder
{
    public function run(): void
    {
        $colores = [
            ['nombre' => 'Rojo', 'codigo' => 'ROJO', 'codigo_hex' => '#FF0000'],
            ['nombre' => 'Azul', 'codigo' => 'AZUL', 'codigo_hex' => '#0000FF'],
            ['nombre' => 'Verde', 'codigo' => 'VERDE', 'codigo_hex' => '#00FF00'],
            ['nombre' => 'Amarillo', 'codigo' => 'AMARILLO', 'codigo_hex' => '#FFFF00'],
            ['nombre' => 'Naranja', 'codigo' => 'NARANJA', 'codigo_hex' => '#FFA500'],
            ['nombre' => 'Marrón', 'codigo' => 'MARRON', 'codigo_hex' => '#8B4513'],
            ['nombre' => 'Violeta', 'codigo' => 'VIOLETA', 'codigo_hex' => '#8B008B'],
            ['nombre' => 'Rosa', 'codigo' => 'ROSA', 'codigo_hex' => '#FF69B4'],
            ['nombre' => 'Blanco', 'codigo' => 'BLANCO', 'codigo_hex' => '#FFFFFF'],
            ['nombre' => 'Negro', 'codigo' => 'NEGRO', 'codigo_hex' => '#000000'],
            ['nombre' => 'Gris', 'codigo' => 'GRIS', 'codigo_hex' => '#808080'],
            ['nombre' => 'Celeste', 'codigo' => 'CELESTE', 'codigo_hex' => '#00BFFF'],
        ];

        foreach ($colores as $c) {
            FibraColor::updateOrCreate(
                ['codigo' => $c['codigo']],
                array_merge($c, ['activo' => true])
            );
        }
    }
}
