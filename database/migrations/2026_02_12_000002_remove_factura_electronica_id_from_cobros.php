<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solo se cobra factura interna: eliminar columna factura_electronica_id de cobros.
     */
    public function up(): void
    {
        if (!Schema::hasTable('cobros')) {
            return;
        }

        if (Schema::hasColumn('cobros', 'factura_electronica_id')) {
            Schema::table('cobros', function (Blueprint $table) {
                $table->dropForeign(['factura_electronica_id']);
            });
            Schema::table('cobros', function (Blueprint $table) {
                $table->dropColumn('factura_electronica_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('cobros')) {
            return;
        }

        if (!Schema::hasColumn('cobros', 'factura_electronica_id')) {
            Schema::table('cobros', function (Blueprint $table) {
                $table->foreignId('factura_electronica_id')->nullable()->after('cliente_id')
                    ->constrained('factura_electronicas')->nullOnDelete();
            });
        }
    }
};
