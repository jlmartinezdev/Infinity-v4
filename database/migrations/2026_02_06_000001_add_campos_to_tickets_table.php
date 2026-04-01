<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('prioridad', 20)->default('media')->after('estado');
            $table->string('reportado_desde', 50)->nullable()->after('prioridad');
            $table->string('imagen', 255)->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['prioridad', 'reportado_desde', 'imagen']);
        });
    }
};
