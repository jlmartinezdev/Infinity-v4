<?php

namespace Database\Seeders;

use App\Models\CategoriaGasto;
use App\Models\CategoriaProducto;
use Illuminate\Database\Seeder;

class InventarioSeeder extends Seeder
{
    public function run(): void
    {
        $catProductos = [
            ['nombre' => 'Routers', 'descripcion' => 'Routers y equipos de red'],
            ['nombre' => 'Antenas', 'descripcion' => 'Antenas y accesorios'],
            ['nombre' => 'Cables', 'descripcion' => 'Cables y conectores'],
            ['nombre' => 'Otros', 'descripcion' => 'Otros equipos'],
        ];
        foreach ($catProductos as $c) {
            CategoriaProducto::firstOrCreate(['nombre' => $c['nombre']], $c);
        }

        $catGastos = [
            ['nombre' => 'Operativos', 'descripcion' => 'Gastos operativos'],
            ['nombre' => 'Mantenimiento', 'descripcion' => 'Mantenimiento de equipos'],
            ['nombre' => 'Servicios', 'descripcion' => 'Servicios externos'],
            ['nombre' => 'Otros', 'descripcion' => 'Otros gastos'],
        ];
        foreach ($catGastos as $c) {
            CategoriaGasto::firstOrCreate(['nombre' => $c['nombre']], $c);
        }
    }
}
