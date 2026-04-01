<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración consolidada: tabla estado_pedido_detalles (crear o añadir columnas faltantes).
     * Reemplaza: create_estado_pedido_detalles_table, add_notas, add_parametros (nodo_id, tecnologia_id, plan_id).
     */
    public function up(): void
    {
        if (!Schema::hasTable('estado_pedido_detalles')) {
            Schema::create('estado_pedido_detalles', function (Blueprint $table) {
                $table->unsignedInteger('pedido_id');
                $table->unsignedInteger('estado_id');
                $table->unsignedInteger('usuario_id');
                $table->dateTime('fecha')->nullable();
                $table->char('estado', 1)->nullable();
                $table->text('notas')->nullable();
                $table->unsignedInteger('nodo_id')->nullable();
                $table->unsignedInteger('tecnologia_id')->nullable();
                $table->unsignedInteger('plan_id')->nullable();
                $table->timestamps();

                $table->primary(['pedido_id', 'estado_id']);
                $table->index('pedido_id');
                $table->index('estado_id');
                $table->index('usuario_id');

                $table->foreign('pedido_id')->references('pedido_id')->on('pedidos');
                $table->foreign('estado_id')->references('estado_id')->on('estados_pedidos');
                $table->foreign('usuario_id')->references('usuario_id')->on('users');
                $table->foreign('nodo_id')->references('nodo_id')->on('nodos');
                $table->foreign('tecnologia_id')->references('tecnologia_id')->on('tipos_tecnologias');
                $table->foreign('plan_id')->references('plan_id')->on('planes');
            });
            return;
        }

        Schema::table('estado_pedido_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('estado_pedido_detalles', 'notas')) {
                $table->text('notas')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('estado_pedido_detalles', 'nodo_id')) {
                $table->unsignedInteger('nodo_id')->nullable()->after('notas');
                $table->foreign('nodo_id')->references('nodo_id')->on('nodos');
            }
            if (!Schema::hasColumn('estado_pedido_detalles', 'tecnologia_id')) {
                $table->unsignedInteger('tecnologia_id')->nullable()->after('nodo_id');
                $table->foreign('tecnologia_id')->references('tecnologia_id')->on('tipos_tecnologias');
            }
            if (!Schema::hasColumn('estado_pedido_detalles', 'plan_id')) {
                $table->unsignedInteger('plan_id')->nullable()->after('tecnologia_id');
                $table->foreign('plan_id')->references('plan_id')->on('planes');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estado_pedido_detalles');
    }
};
