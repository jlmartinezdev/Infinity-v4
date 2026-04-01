<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite guardar hora del pago junto a la fecha.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cobros')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE cobros MODIFY fecha_pago DATETIME NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cobros')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE cobros MODIFY fecha_pago DATE NOT NULL');
        }
    }
};
