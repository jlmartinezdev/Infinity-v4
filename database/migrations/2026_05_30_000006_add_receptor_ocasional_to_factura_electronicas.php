<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fkName = collect(DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), 'factura_electronicas', 'cliente_id']
        ))->pluck('CONSTRAINT_NAME')->first();

        if ($fkName) {
            Schema::table('factura_electronicas', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        Schema::table('factura_electronicas', function (Blueprint $table) {
            if (! Schema::hasColumn('factura_electronicas', 'es_ocasional')) {
                $table->unsignedInteger('cliente_id')->nullable()->change();
                $table->boolean('es_ocasional')->default(false)->after('cliente_id');
                $table->string('receptor_documento', 30)->nullable()->after('es_ocasional');
                $table->string('receptor_nombre', 100)->nullable()->after('receptor_documento');
                $table->string('receptor_apellido', 100)->nullable()->after('receptor_nombre');
                $table->string('receptor_direccion', 255)->nullable()->after('receptor_apellido');
                $table->string('receptor_email', 100)->nullable()->after('receptor_direccion');
                $table->string('receptor_telefono', 30)->nullable()->after('receptor_email');
            }
        });

        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->dropForeign(['cliente_id']);
        });

        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->dropColumn([
                'es_ocasional',
                'receptor_documento',
                'receptor_nombre',
                'receptor_apellido',
                'receptor_direccion',
                'receptor_email',
                'receptor_telefono',
            ]);
            $table->unsignedInteger('cliente_id')->nullable(false)->change();
        });

        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->restrictOnDelete();
        });
    }
};
