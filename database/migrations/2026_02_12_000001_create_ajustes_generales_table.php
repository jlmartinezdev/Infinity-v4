<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_generales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_empresa', 200)->nullable();
            $table->string('logo', 500)->nullable()->comment('Ruta o URL del logo');
            $table->string('telefono', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('sitio_web', 255)->nullable();
            $table->timestamps();
        });

        \DB::table('ajustes_generales')->insert([
            'nombre_empresa' => config('app.name'),
            'logo' => null,
            'telefono' => null,
            'email' => null,
            'direccion' => null,
            'sitio_web' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_generales');
    }
};
