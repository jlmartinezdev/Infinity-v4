<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodo_aps_wireless', function (Blueprint $table) {
            $table->boolean('ping_alerta_enviada')->default(false)->after('ping_fallos_seguidos');
        });
    }

    public function down(): void
    {
        Schema::table('nodo_aps_wireless', function (Blueprint $table) {
            $table->dropColumn('ping_alerta_enviada');
        });
    }
};
