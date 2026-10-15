<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_asuntos', function (Blueprint $table) {
            $table->string('icono', 50)->nullable()->default('🎫')->after('nombre');
            $table->string('color', 30)->nullable()->default('#8b5cf6')->after('icono');
        });

        // Mapeo inicial temático para los 51 asuntos existentes
        $mapeo = [
            1  => ['icono' => '🐢', 'color' => '#6366f1'], // Internet Lento
            2  => ['icono' => '🚫', 'color' => '#ef4444'], // No Tiene Internet
            3  => ['icono' => '📡', 'color' => '#0284c7'], // Antena Desalineada
            4  => ['icono' => '⚡', 'color' => '#f97316'], // Antena Dañada
            5  => ['icono' => '📊', 'color' => '#10b981'], // Cambio de plan
            6  => ['icono' => '📶', 'color' => '#06b6d4'], // No Responde el Router Wifi
            7  => ['icono' => '🔄', 'color' => '#0ea5e9'], // Router Wifi Reseteado
            8  => ['icono' => '🔁', 'color' => '#3b82f6'], // Cambio de Router Wifi
            9  => ['icono' => '📡', 'color' => '#0284c7'], // Cambio de Antena
            10 => ['icono' => '🛠️', 'color' => '#8b5cf6'], // Cambio de Antena + Router Wifi
            11 => ['icono' => '🔑', 'color' => '#f59e0b'], // Cambio de Contraseña en Router Wifi
            12 => ['icono' => '🔌', 'color' => '#ef4444'], // Cable UTP Dañado
            13 => ['icono' => '〰️', 'color' => '#eab308'], // Internet Intermitente
            14 => ['icono' => '🏠', 'color' => '#10b981'], // Cambio de Domicilio
            15 => ['icono' => '⚡', 'color' => '#f97316'], // PoE Dañado
            16 => ['icono' => '🟢', 'color' => '#10b981'], // Reconexión
            17 => ['icono' => '📦', 'color' => '#64748b'], // Recolección De Equipos
            18 => ['icono' => '🔧', 'color' => '#ef4444'], // Conector Dañado
            19 => ['icono' => '💡', 'color' => '#8b5cf6'], // Cambio A Fibra Óptica
            20 => ['icono' => '✂️', 'color' => '#ef4444'], // Cable Fibra Dañado
            21 => ['icono' => '🧵', 'color' => '#ef4444'], // Jumper Dañado
            22 => ['icono' => '🔄', 'color' => '#0284c7'], // Antena valores De Fabrica
            23 => ['icono' => '🌐', 'color' => '#06b6d4'], // Cableado Para Modem Extra
            24 => ['icono' => '⚡', 'color' => '#f97316'], // Eliminador Dañado
            25 => ['icono' => '⚠️', 'color' => '#f59e0b'], // Cables Mal Colocados
            26 => ['icono' => '🔌', 'color' => '#ef4444'], // RJ45 Dañado
            27 => ['icono' => '⚡', 'color' => '#ef4444'], // Alambres Rotos
            28 => ['icono' => '❌', 'color' => '#dc2626'], // Cancelación
            29 => ['icono' => '🔌', 'color' => '#dc2626'], // Desconexión
            30 => ['icono' => '💥', 'color' => '#b91c1c'], // Troncal Dañado
            31 => ['icono' => '📦', 'color' => '#ea580c'], // Caja Nap Dañada
            32 => ['icono' => '🔥', 'color' => '#dc2626'], // Fuente quemado
            33 => ['icono' => '📶', 'color' => '#06b6d4'], // Problema de Señal WIFI 5G
            34 => ['icono' => '🔌', 'color' => '#f97316'], // Cambio POE + Fuente
            35 => ['icono' => '📺', 'color' => '#a855f7'], // Solicitud APP TV
            36 => ['icono' => '🔴', 'color' => '#ef4444'], // Onu no prende
            37 => ['icono' => '📺', 'color' => '#a855f7'], // Problemas con la configuracion de TV
            38 => ['icono' => '🌐', 'color' => '#3b82f6'], // Problemas con la conexion de internet
            39 => ['icono' => '📦', 'color' => '#64748b'], // RECOLECCIÓN DE TV BOX
            40 => ['icono' => '🔥', 'color' => '#dc2626'], // Cargador Quemado
            41 => ['icono' => '📝', 'color' => '#10b981'], // Cambio de Nombre titular Datos.
            42 => ['icono' => '🔌', 'color' => '#f97316'], // Cambio de Fuente
            43 => ['icono' => '🔁', 'color' => '#3b82f6'], // Cambio de Equipo
            44 => ['icono' => '💬', 'color' => '#22c55e'], // WhatsApp
            45 => ['icono' => '💥', 'color' => '#ef4444'], // Onu Dañado
            46 => ['icono' => '📺', 'color' => '#a855f7'], // Pedido de tvbox
            47 => ['icono' => '❌', 'color' => '#dc2626'], // Cancelación App de TV
            48 => ['icono' => '🔄', 'color' => '#a855f7'], // Actualización App TV
            49 => ['icono' => '💰', 'color' => '#f59e0b'], // Visita por Mora
            50 => ['icono' => '📍', 'color' => '#0284c7'], // Cambio de Lugar
            51 => ['icono' => '🔐', 'color' => '#a855f7'], // Cambio de Contraseña App
        ];

        foreach ($mapeo as $id => $data) {
            DB::table('ticket_asuntos')->where('id', $id)->update($data);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_asuntos', function (Blueprint $table) {
            $table->dropColumn(['icono', 'color']);
        });
    }
};
