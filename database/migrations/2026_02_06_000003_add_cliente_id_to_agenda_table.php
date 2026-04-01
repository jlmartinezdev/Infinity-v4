<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->unsignedInteger('cliente_id')->nullable()->after('titulo')
                ->comment('Cliente asociado cuando la cita se crea desde un ticket');
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });
        Schema::table('agenda', function (Blueprint $table) {
            $table->dropColumn('cliente_id');
        });
    }
};
