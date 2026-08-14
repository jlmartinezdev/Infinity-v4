<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_network_backups', function (Blueprint $table) {
            $table->increments('router_network_backup_id');
            $table->unsignedInteger('router_origen_id');
            $table->string('nombre', 128)->nullable();
            $table->string('notas', 255)->nullable();
            $table->unsignedInteger('cant_ipv4')->default(0);
            $table->unsignedInteger('cant_ipv6')->default(0);
            $table->unsignedInteger('cant_rutas_v4')->default(0);
            $table->unsignedInteger('cant_rutas_v6')->default(0);
            $table->timestamp('leido_en')->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();

            $table->foreign('router_origen_id')
                ->references('router_id')
                ->on('routers')
                ->cascadeOnDelete();
        });

        Schema::create('router_network_backup_addresses', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('router_network_backup_id');
            $table->string('familia', 8); // ipv4 | ipv6
            $table->string('address', 128);
            $table->string('network', 128)->nullable();
            $table->string('interface', 128)->nullable();
            $table->boolean('disabled')->default(false);
            $table->string('comment', 255)->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->foreign('router_network_backup_id', 'rnb_addr_backup_fk')
                ->references('router_network_backup_id')
                ->on('router_network_backups')
                ->cascadeOnDelete();
            $table->index(['router_network_backup_id', 'familia'], 'rnb_addr_backup_fam_idx');
        });

        Schema::create('router_network_backup_routes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('router_network_backup_id');
            $table->string('familia', 8); // ipv4 | ipv6
            $table->string('dst_address', 128);
            $table->string('gateway', 255)->nullable();
            $table->unsignedSmallInteger('distance')->nullable();
            $table->string('routing_table', 64)->nullable();
            $table->string('scope', 32)->nullable();
            $table->string('target_scope', 32)->nullable();
            $table->string('pref_src', 128)->nullable();
            $table->string('check_gateway', 32)->nullable();
            $table->boolean('disabled')->default(false);
            $table->string('comment', 255)->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();

            $table->foreign('router_network_backup_id', 'rnb_route_backup_fk')
                ->references('router_network_backup_id')
                ->on('router_network_backups')
                ->cascadeOnDelete();
            $table->index(['router_network_backup_id', 'familia'], 'rnb_route_backup_fam_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_network_backup_routes');
        Schema::dropIfExists('router_network_backup_addresses');
        Schema::dropIfExists('router_network_backups');
    }
};
