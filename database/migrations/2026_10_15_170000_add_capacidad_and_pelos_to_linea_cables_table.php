<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linea_cables', function (Blueprint $table) {
            $table->string('nombre', 100)->nullable()->after('linea_cable_id');
            $table->string('tipo_cable', 30)->default('distribucion')->after('fibra_color_id'); // troncal, distribucion, drop
            $table->unsignedSmallInteger('cantidad_hilos')->default(12)->after('tipo_cable'); // 2, 4, 6, 12, 24, 48, 72, 96, 144
            $table->string('estado', 30)->default('activo')->after('cantidad_hilos'); // activo, proyectado, mantenimiento, danado
            $table->json('hilos_detalle')->nullable()->after('coordenadas'); // matriz de pelos con color, estado, uso, etc.
        });
    }

    public function down(): void
    {
        Schema::table('linea_cables', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'tipo_cable', 'cantidad_hilos', 'estado', 'hilos_detalle']);
        });
    }
};
