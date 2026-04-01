<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['descripcion' => 'Administrador'],
            ['descripcion' => 'Técnico'],
            ['descripcion' => 'Cajero'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['descripcion' => $role['descripcion']],
                $role
            );
        }
    }
}
