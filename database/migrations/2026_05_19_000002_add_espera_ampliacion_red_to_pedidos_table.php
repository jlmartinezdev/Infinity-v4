<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos', 'espera_ampliacion_red')) {
                $table->boolean('espera_ampliacion_red')->default(false)->after('usuario_pppoe_creado');
            }
            if (! Schema::hasColumn('pedidos', 'espera_ampliacion_red_at')) {
                $table->timestamp('espera_ampliacion_red_at')->nullable()->after('espera_ampliacion_red');
            }
            if (! Schema::hasColumn('pedidos', 'espera_ampliacion_red_notas')) {
                $table->text('espera_ampliacion_red_notas')->nullable()->after('espera_ampliacion_red_at');
            }
            if (! Schema::hasColumn('pedidos', 'espera_ampliacion_red_usuario_id')) {
                $table->unsignedInteger('espera_ampliacion_red_usuario_id')->nullable()->after('espera_ampliacion_red_notas');
                $table->foreign('espera_ampliacion_red_usuario_id')
                    ->references('usuario_id')
                    ->on('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'espera_ampliacion_red_usuario_id')) {
                $table->dropForeign(['espera_ampliacion_red_usuario_id']);
                $table->dropColumn('espera_ampliacion_red_usuario_id');
            }
            if (Schema::hasColumn('pedidos', 'espera_ampliacion_red_notas')) {
                $table->dropColumn('espera_ampliacion_red_notas');
            }
            if (Schema::hasColumn('pedidos', 'espera_ampliacion_red_at')) {
                $table->dropColumn('espera_ampliacion_red_at');
            }
            if (Schema::hasColumn('pedidos', 'espera_ampliacion_red')) {
                $table->dropColumn('espera_ampliacion_red');
            }
        });
    }
};
