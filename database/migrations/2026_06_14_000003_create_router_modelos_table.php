<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_modelos', function (Blueprint $table) {
            $table->increments('router_modelo_id');
            $table->string('slug', 64)->unique();
            $table->string('nombre', 120);
            $table->string('serie', 32)->default('MikroTik');
            $table->string('descripcion', 255)->nullable();
            $table->string('imagen', 255)->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        $now = now();
        $orden = 0;
        foreach (config('mikrotik_modelos', []) as $slug => $data) {
            DB::table('router_modelos')->insert([
                'slug' => $slug,
                'nombre' => $data['nombre'],
                'serie' => $data['serie'] ?? 'MikroTik',
                'descripcion' => $data['descripcion'] ?? null,
                'imagen' => $data['imagen'] ?? null,
                'activo' => true,
                'orden' => $orden++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('router_modelos');
    }
};
