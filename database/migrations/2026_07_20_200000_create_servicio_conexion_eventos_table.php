<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicio_conexion_eventos', function (Blueprint $table) {
            $table->increments('servicio_conexion_evento_id');
            $table->unsignedBigInteger('servicio_id');
            $table->string('tipo', 32);
            // pppoe_up | pppoe_down | senal_optica | senal_antena | snapshot
            $table->string('fuente', 32)->nullable();
            // mikrotik_consulta | olt_consulta | cron | manual | webhook
            $table->timestamp('ocurrio_at');

            // PPPoE
            $table->string('pppoe_estado', 16)->nullable(); // up | down
            $table->string('usuario_pppoe', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->unsignedInteger('router_id')->nullable();
            $table->string('uptime', 64)->nullable();

            // Óptica ONU
            $table->unsignedInteger('olt_id')->nullable();
            $table->unsignedTinyInteger('pon_port')->nullable();
            $table->unsignedSmallInteger('onu_index')->nullable();
            $table->decimal('rx_power_dbm', 8, 2)->nullable();
            $table->decimal('tx_power_dbm', 8, 2)->nullable();
            $table->string('onu_estado', 32)->nullable();
            $table->string('onu_descripcion', 128)->nullable();

            // Antena / wireless
            $table->decimal('antena_signal_dbm', 8, 2)->nullable();
            $table->decimal('antena_snr_db', 8, 2)->nullable();
            $table->string('antena_radio_iface', 64)->nullable();

            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('servicio_id')
                ->references('servicio_id')
                ->on('servicios')
                ->cascadeOnDelete();
            $table->foreign('router_id')
                ->references('router_id')
                ->on('routers')
                ->nullOnDelete();
            $table->foreign('olt_id')
                ->references('olt_id')
                ->on('olts')
                ->nullOnDelete();

            $table->index(['servicio_id', 'ocurrio_at']);
            $table->index(['servicio_id', 'tipo', 'ocurrio_at']);
            $table->index(['tipo', 'ocurrio_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_conexion_eventos');
    }
};
