<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_acceso', function (Blueprint $table) {
            if (! Schema::hasColumn('solicitudes_acceso', 'codigo_verificacion')) {
                $table->string('codigo_verificacion', 10)->nullable()->after('estado')->index();
            }
            if (! Schema::hasColumn('solicitudes_acceso', 'telefono_verificado')) {
                $table->boolean('telefono_verificado')->default(false)->after('codigo_verificacion');
            }
            if (! Schema::hasColumn('solicitudes_acceso', 'telefono_verificado_at')) {
                $table->timestamp('telefono_verificado_at')->nullable()->after('telefono_verificado');
            }
            if (! Schema::hasColumn('solicitudes_acceso', 'whatsapp_from')) {
                $table->string('whatsapp_from', 30)->nullable()->after('telefono_verificado_at');
            }
        });

        // Ampliar enum de estado (MySQL/MariaDB)
        try {
            DB::statement("ALTER TABLE solicitudes_acceso MODIFY COLUMN estado ENUM('pendiente_verificacion','pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente_verificacion'");
        } catch (\Throwable $e) {
            // SQLite u otros: si estado es string, no hace falta
        }
    }

    public function down(): void
    {
        Schema::table('solicitudes_acceso', function (Blueprint $table) {
            foreach (['codigo_verificacion', 'telefono_verificado', 'telefono_verificado_at', 'whatsapp_from'] as $col) {
                if (Schema::hasColumn('solicitudes_acceso', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        try {
            DB::statement("ALTER TABLE solicitudes_acceso MODIFY COLUMN estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente'");
        } catch (\Throwable $e) {
            //
        }
    }
};
