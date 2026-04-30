<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('salida_pons', 'olt_puerto_id')) {
            Schema::table('salida_pons', function (Blueprint $table) {
                $table->unsignedInteger('olt_puerto_id')->nullable()->after('olt_id');
                $table->foreign('olt_puerto_id')->references('olt_puerto_id')->on('olt_puertos')->nullOnDelete();
            });
        }

        $salidas = DB::table('salida_pons')
            ->whereNotNull('olt_id')
            ->get(['salida_pon_id', 'olt_id', 'puerto_olt']);

        foreach ($salidas as $row) {
            $opId = DB::table('olt_puertos')
                ->where('olt_id', $row->olt_id)
                ->where('numero', (int) $row->puerto_olt)
                ->value('olt_puerto_id');
            if ($opId) {
                DB::table('salida_pons')
                    ->where('salida_pon_id', $row->salida_pon_id)
                    ->update(['olt_puerto_id' => $opId]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('salida_pons', 'olt_puerto_id')) {
            Schema::table('salida_pons', function (Blueprint $table) {
                try {
                    $table->dropForeign(['olt_puerto_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('olt_puerto_id');
            });
        }
    }
};
