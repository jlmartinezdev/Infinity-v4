<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('servicios') && !Schema::hasColumn('servicios', 'saldo_a_favor')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->decimal('saldo_a_favor', 15, 2)->default(0)->after('estado_pago')
                    ->comment('Crédito del cliente por pagos en exceso');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('servicios', 'saldo_a_favor')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->dropColumn('saldo_a_favor');
            });
        }
    }
};
