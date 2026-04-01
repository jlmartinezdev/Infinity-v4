<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salida_pons', function (Blueprint $table) {
            $table->unsignedInteger('olt_puerto_id')->nullable()->after('caja_nap_id');
            $table->foreign('olt_puerto_id')->references('olt_puerto_id')->on('olt_puertos')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('salida_pons', function (Blueprint $table) {
            $table->dropForeign(['olt_puerto_id']);
        });
    }
};
