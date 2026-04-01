<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Renombrar facturas -> factura_electronicas, factura_detalles -> factura_electronica_detalles.
     * Actualizar cobros: factura_id -> factura_electronica_id y agregar factura_interna_id.
     */
    public function up(): void
    {
        if (Schema::hasTable('facturas')) {
            Schema::rename('facturas', 'factura_electronicas');
        }
        if (Schema::hasTable('factura_detalles')) {
            Schema::rename('factura_detalles', 'factura_electronica_detalles');
        }

        if (Schema::hasTable('factura_electronica_detalles') && Schema::hasColumn('factura_electronica_detalles', 'factura_id')) {
            $fkName = DB::selectOne("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'factura_electronica_detalles' AND COLUMN_NAME = 'factura_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            if ($fkName) {
                Schema::table('factura_electronica_detalles', function (Blueprint $table) use ($fkName) {
                    $table->dropForeign($fkName->CONSTRAINT_NAME);
                });
            }
            Schema::table('factura_electronica_detalles', function (Blueprint $table) {
                $table->renameColumn('factura_id', 'factura_electronica_id');
            });
        }
        if (Schema::hasTable('factura_electronica_detalles') && Schema::hasColumn('factura_electronica_detalles', 'factura_electronica_id')) {
            $hasFk = DB::selectOne("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'factura_electronica_detalles' AND COLUMN_NAME = 'factura_electronica_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            if (!$hasFk) {
                Schema::table('factura_electronica_detalles', function (Blueprint $table) {
                    $table->foreign('factura_electronica_id')->references('id')->on('factura_electronicas')->cascadeOnDelete();
                });
            }
        }

        if (Schema::hasColumn('cobros', 'factura_id')) {
            Schema::table('cobros', function (Blueprint $table) {
                $table->dropForeign(['factura_id']);
            });
            Schema::table('cobros', function (Blueprint $table) {
                $table->renameColumn('factura_id', 'factura_electronica_id');
            });
        }
        if (Schema::hasTable('cobros') && Schema::hasColumn('cobros', 'factura_electronica_id') && !Schema::hasColumn('cobros', 'factura_interna_id')) {
            Schema::table('cobros', function (Blueprint $table) {
                $table->foreignId('factura_interna_id')->nullable()->after('factura_electronica_id')
                    ->constrained('factura_internas')->nullOnDelete();
            });
        }
        if (Schema::hasTable('cobros') && Schema::hasColumn('cobros', 'factura_electronica_id')) {
            $hasCobrosFk = DB::selectOne("SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cobros' AND COLUMN_NAME = 'factura_electronica_id' AND REFERENCED_TABLE_NAME IS NOT NULL");
            if (!$hasCobrosFk) {
                Schema::table('cobros', function (Blueprint $table) {
                    $table->foreign('factura_electronica_id')->references('id')->on('factura_electronicas')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropForeign(['factura_interna_id']);
            $table->dropForeign(['factura_electronica_id']);
        });
        Schema::table('cobros', function (Blueprint $table) {
            $table->dropColumn('factura_interna_id');
            $table->renameColumn('factura_electronica_id', 'factura_id');
        });
        Schema::table('cobros', function (Blueprint $table) {
            $table->foreign('factura_id')->references('id')->on('facturas')->nullOnDelete();
        });

        Schema::table('factura_electronica_detalles', function (Blueprint $table) {
            $table->dropForeign(['factura_electronica_id']);
        });
        Schema::table('factura_electronica_detalles', function (Blueprint $table) {
            $table->renameColumn('factura_electronica_id', 'factura_id');
        });
        Schema::table('factura_electronica_detalles', function (Blueprint $table) {
            $table->foreign('factura_id')->references('id')->on('facturas')->cascadeOnDelete();
        });

        Schema::rename('factura_electronicas', 'facturas');
        Schema::rename('factura_electronica_detalles', 'factura_detalles');
    }
};
