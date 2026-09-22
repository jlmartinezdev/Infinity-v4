<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('facturacion_parametros')
            ->where('clave', 'hora_corte_automatico')
            ->where('valor', '00:01')
            ->update([
                'valor' => '09:00',
                'updated_at' => now(),
            ]);

        Cache::forget('facturacion_param_hora_corte_automatico');
    }

    public function down(): void
    {
        DB::table('facturacion_parametros')
            ->where('clave', 'hora_corte_automatico')
            ->where('valor', '09:00')
            ->update([
                'valor' => '00:01',
                'updated_at' => now(),
            ]);

        Cache::forget('facturacion_param_hora_corte_automatico');
    }
};
