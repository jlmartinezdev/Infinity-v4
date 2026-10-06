<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            if (! Schema::hasColumn('clientes', 'debe_cambiar_clave_app')) {
                $table->boolean('debe_cambiar_clave_app')->default(false)->after('fecha_otorgamiento');
            }
            if (! Schema::hasColumn('clientes', 'push_cambio_clave')) {
                $table->boolean('push_cambio_clave')->default(true)->after('debe_cambiar_clave_app');
            }
        });

        $asunto = 'Cambio de Contraseña App';
        $exists = DB::table('ticket_asuntos')->where('nombre', $asunto)->exists();
        if (! $exists) {
            DB::table('ticket_asuntos')->insert([
                'nombre' => $asunto,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            foreach (['push_cambio_clave', 'debe_cambiar_clave_app'] as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // No borrar el asunto: puede haber tickets históricos.
    }
};
