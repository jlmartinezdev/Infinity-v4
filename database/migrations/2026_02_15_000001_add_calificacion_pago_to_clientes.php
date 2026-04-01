<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('calificacion_pago', 20)->nullable()->after('estado')
                ->comment('malo: <50% a fecha; bueno: >=50% a fecha; excelente: 100% a fecha');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('calificacion_pago');
        });
    }
};
