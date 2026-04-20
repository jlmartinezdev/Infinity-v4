<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->unsignedTinyInteger('dia_aviso_vencimiento')
                ->nullable()
                ->after('vencimiento_pago');
        });

        DB::table('tv_cuentas')
            ->whereNotNull('vencimiento_pago')
            ->update([
                'dia_aviso_vencimiento' => DB::raw('DAY(vencimiento_pago)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $table->dropColumn('dia_aviso_vencimiento');
        });
    }
};
