<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('splitter_primarios', function (Blueprint $table) {
            $table->decimal('potencia_entrada', 8, 2)->nullable()->after('puerto_entrada')->comment('dBm');
            $table->decimal('potencia_salida', 8, 2)->nullable()->after('potencia_entrada')->comment('dBm');
        });

        Schema::table('splitter_secundarios', function (Blueprint $table) {
            $table->decimal('potencia_entrada', 8, 2)->nullable()->after('puerto_entrada')->comment('dBm');
            $table->decimal('potencia_salida', 8, 2)->nullable()->after('potencia_entrada')->comment('dBm');
        });
    }

    public function down(): void
    {
        Schema::table('splitter_primarios', function (Blueprint $table) {
            $table->dropColumn(['potencia_entrada', 'potencia_salida']);
        });

        Schema::table('splitter_secundarios', function (Blueprint $table) {
            $table->dropColumn(['potencia_entrada', 'potencia_salida']);
        });
    }
};
