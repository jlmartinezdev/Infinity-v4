<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('tv_cuenta_asignaciones', 'tvbox_comodato')) {
                $after = Schema::hasColumn('tv_cuenta_asignaciones', 'precio_aplicado')
                    ? 'precio_aplicado'
                    : (Schema::hasColumn('tv_cuenta_asignaciones', 'es_promo') ? 'es_promo' : 'servicio_id');
                $table->boolean('tvbox_comodato')->default(false)->after($after);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'tvbox_comodato')) {
                $table->dropColumn('tvbox_comodato');
            }
        });
    }
};
