<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ticket_asuntos', 'icono_svg')) {
            Schema::table('ticket_asuntos', function (Blueprint $table) {
                $table->string('icono_svg', 40)->nullable()->after('icono');
            });
        }

        $mapeo = [
            1 => 'lento', 2 => 'sin-internet', 3 => 'parabolica', 4 => 'antena', 5 => 'plan',
            6 => 'router', 7 => 'refrescar', 8 => 'intercambio', 9 => 'antena', 10 => 'herramienta',
            11 => 'llave', 12 => 'cable', 13 => 'intermitente', 14 => 'casa', 15 => 'rayo',
            16 => 'power', 17 => 'paquete', 18 => 'conector', 19 => 'fibra', 20 => 'cortado',
            21 => 'fibra', 22 => 'parabolica', 23 => 'cable', 24 => 'fuente', 25 => 'alerta',
            26 => 'conector', 27 => 'cortado', 28 => 'cancelar', 29 => 'desconexion', 30 => 'troncal',
            31 => 'caja-nap', 32 => 'fuego', 33 => 'wifi', 34 => 'fuente', 35 => 'tv',
            36 => 'onu', 37 => 'tv', 38 => 'internet', 39 => 'paquete', 40 => 'fuego',
            41 => 'documento', 42 => 'fuente', 43 => 'intercambio', 44 => 'chat', 45 => 'onu',
            46 => 'tvbox', 47 => 'cancelar', 48 => 'app', 49 => 'dinero', 50 => 'ubicacion',
            51 => 'candado',
        ];

        foreach ($mapeo as $id => $clave) {
            DB::table('ticket_asuntos')->where('id', $id)->whereNull('icono_svg')->update(['icono_svg' => $clave]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_asuntos', 'icono_svg')) {
            Schema::table('ticket_asuntos', function (Blueprint $table) {
                $table->dropColumn('icono_svg');
            });
        }
    }
};
