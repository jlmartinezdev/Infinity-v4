<?php

namespace Database\Seeders;

use App\Models\OltMarca;
use Illuminate\Database\Seeder;

class OltMarcaSeeder extends Seeder
{
    public function run(): void
    {
        $marcas = ['ZTE', 'Huawei', 'Nokia', 'FiberHome', 'Ciena', 'Calix'];

        foreach ($marcas as $nombre) {
            OltMarca::firstOrCreate(
                ['nombre' => $nombre],
                ['estado' => 'activo']
            );
        }
    }
}
