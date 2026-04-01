<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración consolidada: tabla pedidos (crear o añadir columnas faltantes).
     * Reemplaza: create_pedidos_table, add_ubicacion_plan, remove_estado_id,
     * add_lat_lon, add_prioridad_instalacion, add_estado_instalado, add_usuario_pppoe_creado.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pedidos')) {
            Schema::create('pedidos', function (Blueprint $table) {
                $table->increments('pedido_id');
                $table->unsignedInteger('cliente_id');
                $table->date('fecha_pedido');
                $table->text('descripcion')->nullable();
                $table->text('observaciones')->nullable();
                $table->text('ubicacion')->nullable();
                $table->text('maps_gps')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lon', 10, 7)->nullable();
                $table->unsignedInteger('plan_id')->nullable();
                $table->unsignedTinyInteger('prioridad_instalacion')->default(2)->comment('1=alta, 2=media, 3=baja');
                $table->boolean('estado_instalado')->default(false);
                $table->boolean('usuario_pppoe_creado')->default(false);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('cliente_id')->references('cliente_id')->on('clientes');
                $table->foreign('plan_id')->references('plan_id')->on('planes');
            });
            return;
        }

        if (Schema::hasColumn('pedidos', 'estado_id')) {
            Schema::table('pedidos', function (Blueprint $table) {
                $table->dropForeign(['estado_id']);
                $table->dropColumn('estado_id');
            });
        }

        $add = function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'ubicacion')) {
                $table->text('ubicacion')->nullable()->after('fecha_pedido');
            }
            if (!Schema::hasColumn('pedidos', 'maps_gps')) {
                $table->text('maps_gps')->nullable()->after('ubicacion');
            }
            if (!Schema::hasColumn('pedidos', 'plan_id')) {
                $table->unsignedInteger('plan_id')->nullable()->after('maps_gps');
                $table->foreign('plan_id')->references('plan_id')->on('planes');
            }
            if (!Schema::hasColumn('pedidos', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('maps_gps');
            }
            if (!Schema::hasColumn('pedidos', 'lon')) {
                $table->decimal('lon', 10, 7)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('pedidos', 'prioridad_instalacion')) {
                $table->unsignedTinyInteger('prioridad_instalacion')->default(2)->after('plan_id')->comment('1=alta, 2=media, 3=baja');
            }
            if (!Schema::hasColumn('pedidos', 'estado_instalado')) {
                $table->boolean('estado_instalado')->default(false)->after('prioridad_instalacion');
            }
            if (!Schema::hasColumn('pedidos', 'usuario_pppoe_creado')) {
                $table->boolean('usuario_pppoe_creado')->default(false)->after('estado_instalado');
            }
        };

        Schema::table('pedidos', $add);
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
