<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_casos', function (Blueprint $table) {
            $table->unsignedInteger('usos_exitosos')->default(0)->after('activo');
            $table->unsignedInteger('usos_descartes')->default(0)->after('usos_exitosos');
        });
    }

    public function down(): void
    {
        Schema::table('wa_casos', function (Blueprint $table) {
            $table->dropColumn(['usos_exitosos', 'usos_descartes']);
        });
    }
};
