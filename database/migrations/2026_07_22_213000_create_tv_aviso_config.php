<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'clave' => 'tv_aviso_enabled',
                'valor' => '0',
                'descripcion' => 'Avisos WhatsApp de vencimiento TV (1=activo)',
            ],
            [
                'clave' => 'tv_aviso_dias_antes',
                'valor' => '7',
                'descripcion' => 'Días de anticipación para aviso de vencimiento TV',
            ],
            [
                'clave' => 'tv_aviso_hora',
                'valor' => '09:00',
                'descripcion' => 'Hora diaria (HH:MM) para enviar avisos de vencimiento TV',
            ],
            [
                'clave' => 'tv_aviso_usuario_ids',
                'valor' => '[]',
                'descripcion' => 'JSON de usuario_id staff que reciben avisos TV por WhatsApp',
            ],
        ];

        foreach ($params as $p) {
            DB::table('facturacion_parametros')->updateOrInsert(
                ['clave' => $p['clave']],
                [
                    'valor' => $p['valor'],
                    'descripcion' => $p['descripcion'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (! Schema::hasTable('tv_aviso_notificaciones')) {
            Schema::create('tv_aviso_notificaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tv_cuenta_id');
                $table->date('fecha_vencimiento');
                $table->timestamp('enviado_at');
                $table->timestamps();

                $table->unique(['tv_cuenta_id', 'fecha_vencimiento'], 'tv_aviso_cuenta_venc_unique');
                $table->foreign('tv_cuenta_id')
                    ->references('id')
                    ->on('tv_cuentas')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_aviso_notificaciones');

        DB::table('facturacion_parametros')->whereIn('clave', [
            'tv_aviso_enabled',
            'tv_aviso_dias_antes',
            'tv_aviso_hora',
            'tv_aviso_usuario_ids',
        ])->delete();
    }
};
