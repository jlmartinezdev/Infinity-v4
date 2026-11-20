<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodo_ap_wireless_caidas', function (Blueprint $table) {
            $table->increments('caida_id');
            $table->unsignedInteger('ap_id');
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();

            $table->index(['ap_id', 'inicio']);
            $table->foreign('ap_id')->references('ap_id')->on('nodo_aps_wireless')->cascadeOnDelete();
        });

        $caidos = DB::table('nodo_aps_wireless')
            ->where('ping_ok', false)
            ->whereNotNull('ping_caido_desde')
            ->get(['ap_id', 'ping_caido_desde']);

        foreach ($caidos as $ap) {
            DB::table('nodo_ap_wireless_caidas')->insert([
                'ap_id' => $ap->ap_id,
                'inicio' => $ap->ping_caido_desde,
                'fin' => null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nodo_ap_wireless_caidas');
    }
};
