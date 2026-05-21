<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estado_pedido_detalles', function (Blueprint $table) {
            if (! Schema::hasColumn('estado_pedido_detalles', 'pool_id')) {
                $table->unsignedInteger('pool_id')->nullable()->after('plan_id');
                $table->foreign('pool_id')->references('pool_id')->on('router_ip_pools');
            }
        });
    }

    public function down(): void
    {
        Schema::table('estado_pedido_detalles', function (Blueprint $table) {
            if (Schema::hasColumn('estado_pedido_detalles', 'pool_id')) {
                $table->dropForeign(['pool_id']);
                $table->dropColumn('pool_id');
            }
        });
    }
};
