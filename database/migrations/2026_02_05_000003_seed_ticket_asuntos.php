<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $asuntos = [
        'Internet Lento',
        'No Tiene Internet',
        'Antena Desalineada',
        'Antena Dañada',
        'No Responde la Antena',
        'No Responde el Router Wifi',
        'Router Wifi Reseteado(Valores de Fabrica)',
        'Cambio de Router Wifi',
        'Cambio de Antena',
        'Cambio de Antena + Router Wifi',
        'Cambio de Contraseña en Router Wifi',
        'Cable UTP Dañado',
        'Internet Intermitente',
        'Cambio de Domicilio',
        'PoE Dañado',
        'Reconexión',
        'Recolección De Equipos',
        'Conector Dañado',
        'Cambio A Fibra Óptica',
        'Cable Fibra Dañado',
        'Jumper Dañado',
        'Antena valores De Fabrica',
        'Cableado Para Modem Extra',
        'Eliminador Dañado',
        'Cables Mal Colocados',
        'RJ45 Dañado',
        'Alambres Rotos',
        'Cancelación',
        'Desconexión',
        'Troncal Dañado',
        'Caja Nap Dañada',
    ];

    public function up(): void
    {
        if (DB::table('ticket_asuntos')->exists()) {
            return;
        }
        foreach ($this->asuntos as $nombre) {
            DB::table('ticket_asuntos')->insert([
                'nombre' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('ticket_asuntos')->truncate();
    }
};
