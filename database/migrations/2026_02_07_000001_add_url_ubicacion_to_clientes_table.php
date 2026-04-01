<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('url_ubicacion', 500)->nullable()->after('direccion')
                ->comment('URL o coordenadas de ubicación (copiado desde pedido.maps_gps al marcar instalado)');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('url_ubicacion');
        });
    }
};
