<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'nota_tecnico')) {
                $table->string('nota_tecnico', 500)->nullable()->after('observaciones');
            }
            if (! Schema::hasColumn('tickets', 'detalle_tecnico')) {
                $table->text('detalle_tecnico')->nullable()->after('nota_tecnico');
            }
            if (! Schema::hasColumn('tickets', 'actualizado_por_id')) {
                $table->unsignedInteger('actualizado_por_id')->nullable()->after('asignado_id');
                $table->foreign('actualizado_por_id')->references('usuario_id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'actualizado_por_id')) {
                $table->dropForeign(['actualizado_por_id']);
                $table->dropColumn('actualizado_por_id');
            }
            if (Schema::hasColumn('tickets', 'detalle_tecnico')) {
                $table->dropColumn('detalle_tecnico');
            }
            if (Schema::hasColumn('tickets', 'nota_tecnico')) {
                $table->dropColumn('nota_tecnico');
            }
        });
    }
};
