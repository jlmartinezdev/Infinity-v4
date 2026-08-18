<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cpe_modelos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 16);
            $table->string('clave', 32);
            $table->string('nombre', 64);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['tipo', 'clave']);
        });

        $ahora = now();
        foreach (['onu', 'router', 'antena'] as $tipo) {
            foreach ((array) config('cpe.'.$tipo, []) as $clave => $nombre) {
                DB::table('cpe_modelos')->insert([
                    'tipo' => $tipo,
                    'clave' => $clave,
                    'nombre' => $nombre,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }

        if (Schema::hasColumn('servicios', 'cpe_onu')) {
            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE servicios MODIFY cpe_onu VARCHAR(64) NULL');
                DB::statement('ALTER TABLE servicios MODIFY cpe_router VARCHAR(64) NULL');
                DB::statement('ALTER TABLE servicios MODIFY cpe_antena VARCHAR(64) NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cpe_modelos');
    }
};
