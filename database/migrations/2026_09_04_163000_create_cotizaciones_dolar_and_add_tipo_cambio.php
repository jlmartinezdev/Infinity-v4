<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_dolar', function (Blueprint $table) {
            $table->id();
            $table->decimal('valor', 12, 2);
            $table->date('fecha');
            $table->string('notas', 255)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::table('compras', function (Blueprint $table) {
            if (! Schema::hasColumn('compras', 'tipo_cambio')) {
                $table->decimal('tipo_cambio', 12, 2)->nullable()->after('numero_factura');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'tipo_cambio')) {
                $table->decimal('tipo_cambio', 12, 2)->nullable()->after('numero_factura');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (Schema::hasColumn('compras', 'tipo_cambio')) {
                $table->dropColumn('tipo_cambio');
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'tipo_cambio')) {
                $table->dropColumn('tipo_cambio');
            }
        });

        Schema::dropIfExists('cotizaciones_dolar');
    }
};
