<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mover estado_pago de pedidos a servicios.
     */
    public function up(): void
    {
        if (Schema::hasColumn('pedidos', 'estado_pago')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropColumn('estado_pago');
            });
        }

        if (Schema::hasTable('servicios') && !Schema::hasColumn('servicios', 'estado_pago')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->string('estado_pago', 30)->nullable()
                    ->comment('pendiente, pagado, parcial, exento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('servicios') && Schema::hasColumn('servicios', 'estado_pago')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->dropColumn('estado_pago');
            });
        }

        if (Schema::hasTable('pedidos') && !Schema::hasColumn('pedidos', 'estado_pago')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->string('estado_pago', 30)->nullable()->after('estado_instalado')
                    ->comment('pendiente, pagado, parcial, exento');
            });
        }
    }
};
