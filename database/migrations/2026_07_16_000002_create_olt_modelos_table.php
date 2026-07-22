<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_modelos', function (Blueprint $table) {
            $table->increments('olt_modelo_id');
            $table->string('slug', 64)->unique();
            $table->string('nombre', 120);
            $table->string('marca', 64)->default('Otro');
            $table->string('descripcion', 255)->nullable();
            $table->string('imagen', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        $seeds = [
            ['slug' => 'vsol-v1600d', 'nombre' => 'V1600D', 'marca' => 'VSOL', 'descripcion' => 'OLT GPON 8/16 PON', 'orden' => 0],
            ['slug' => 'vsol-v1600g1', 'nombre' => 'V1600G1', 'marca' => 'VSOL', 'descripcion' => 'OLT GPON compacto', 'orden' => 1],
            ['slug' => 'huawei-ma5608t', 'nombre' => 'MA5608T', 'marca' => 'Huawei', 'descripcion' => 'SmartAX OLT', 'orden' => 2],
            ['slug' => 'zte-c320', 'nombre' => 'C320', 'marca' => 'ZTE', 'descripcion' => 'OLT GPON C320', 'orden' => 3],
            ['slug' => 'fiberhome-an5516', 'nombre' => 'AN5516', 'marca' => 'Fiberhome', 'descripcion' => 'OLT GPON', 'orden' => 4],
            ['slug' => 'otro', 'nombre' => 'Otro / genérico', 'marca' => 'Otro', 'descripcion' => 'Modelo no listado', 'orden' => 99],
        ];

        foreach ($seeds as $row) {
            DB::table('olt_modelos')->insert([
                'slug' => $row['slug'],
                'nombre' => $row['nombre'],
                'marca' => $row['marca'],
                'descripcion' => $row['descripcion'],
                'imagen' => 'images/olts/olt-generic.svg',
                'activo' => true,
                'orden' => $row['orden'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_modelos');
    }
};
