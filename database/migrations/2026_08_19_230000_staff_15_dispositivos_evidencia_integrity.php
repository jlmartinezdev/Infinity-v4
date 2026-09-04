<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->string('device_key', 80)->default('default');
            $table->string('nombre', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->boolean('app_activa')->default(false);
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->unique(['cliente_id', 'device_key']);
            $table->index(['app_activa', 'last_seen']);
            $table->index('last_seen');
        });

        // Backfill desde telemetría actual de clientes
        $rows = DB::table('clientes')
            ->where(function ($q) {
                $q->where('app_activa', true)
                    ->orWhereNotNull('ultimo_ingreso')
                    ->orWhereNotNull('dispositivo')
                    ->orWhereNotNull('app_version');
            })
            ->get(['cliente_id', 'dispositivo', 'app_version', 'app_activa', 'ultimo_ingreso', 'fecha_activacion_app']);

        $now = now();
        foreach ($rows as $row) {
            $nombre = $row->dispositivo ?: null;
            $key = $nombre ? substr(hash('sha256', mb_strtolower(trim($nombre))), 0, 40) : 'default';
            DB::table('dispositivos')->insert([
                'cliente_id' => $row->cliente_id,
                'device_key' => $key,
                'nombre' => $nombre ? mb_substr($nombre, 0, 120) : null,
                'app_version' => $row->app_version ? mb_substr($row->app_version, 0, 40) : null,
                'app_activa' => (bool) $row->app_activa,
                'last_seen' => $row->ultimo_ingreso,
                'last_login' => $row->ultimo_ingreso,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('staff_evidencias', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 40); // visita | pedido_instalacion
            $table->unsignedBigInteger('entity_id');
            $table->unsignedInteger('usuario_id');
            $table->string('path', 500);
            $table->string('caption', 500)->nullable();
            $table->uuid('client_photo_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
            $table->index(['entity_type', 'entity_id']);
            $table->unique(['entity_type', 'entity_id', 'client_photo_id'], 'staff_evidencias_entity_photo_unique');
        });

        Schema::create('integrity_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('nonce', 64)->unique();
            $table->string('ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_nonces');
        Schema::dropIfExists('staff_evidencias');
        Schema::dropIfExists('dispositivos');
    }
};
