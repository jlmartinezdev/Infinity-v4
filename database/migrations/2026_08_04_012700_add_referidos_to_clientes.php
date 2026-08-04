<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'referido_codigo')) {
                $table->string('referido_codigo', 20)->nullable()->unique()->after('aprobado_por');
            }
            if (! Schema::hasColumn('clientes', 'referido_por_cliente_id')) {
                $table->unsignedInteger('referido_por_cliente_id')->nullable()->after('referido_codigo');
                $table->foreign('referido_por_cliente_id')
                    ->references('cliente_id')
                    ->on('clientes')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (Schema::hasColumn('clientes', 'referido_por_cliente_id')) {
                $table->dropForeign(['referido_por_cliente_id']);
                $table->dropColumn('referido_por_cliente_id');
            }
            if (Schema::hasColumn('clientes', 'referido_codigo')) {
                $table->dropColumn('referido_codigo');
            }
        });
    }
};
