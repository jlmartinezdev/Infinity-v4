<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_asuntos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->unique();
            $table->string('color', 20)->default('#10b981');
            $table->unsignedSmallInteger('orden')->default(100);
            $table->boolean('activo')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('whatsapp_contactos', function (Blueprint $table) {
            if (! Schema::hasColumn('whatsapp_contactos', 'whatsapp_asunto_id')) {
                $table->foreignId('whatsapp_asunto_id')
                    ->nullable()
                    ->after('cliente_id')
                    ->constrained('whatsapp_asuntos')
                    ->nullOnDelete();
            }
        });

        $now = now();
        $defaults = [
            ['nombre' => 'Soporte técnico', 'color' => '#3b82f6', 'orden' => 10],
            ['nombre' => 'Facturación', 'color' => '#8b5cf6', 'orden' => 20],
            ['nombre' => 'Instalación / Pedido', 'color' => '#f59e0b', 'orden' => 30],
            ['nombre' => 'Comercial', 'color' => '#10b981', 'orden' => 40],
            ['nombre' => 'Reclamo', 'color' => '#ef4444', 'orden' => 50],
            ['nombre' => 'Otro', 'color' => '#64748b', 'orden' => 90],
        ];

        foreach ($defaults as $row) {
            DB::table('whatsapp_asuntos')->updateOrInsert(
                ['nombre' => $row['nombre']],
                [
                    'color' => $row['color'],
                    'orden' => $row['orden'],
                    'activo' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('whatsapp_contactos', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_contactos', 'whatsapp_asunto_id')) {
                $table->dropConstrainedForeignId('whatsapp_asunto_id');
            }
        });

        Schema::dropIfExists('whatsapp_asuntos');
    }
};
