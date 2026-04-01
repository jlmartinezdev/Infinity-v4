<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Asegura fecha_pago como DATETIME en MySQL/MariaDB.
     * La migración anterior solo contemplaba driver "mysql"; con "mariadb" no ejecutaba el ALTER.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cobros') || ! Schema::hasColumn('cobros', 'fecha_pago')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE cobros MODIFY fecha_pago DATETIME NOT NULL');
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
