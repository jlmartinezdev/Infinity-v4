<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (!Schema::hasColumn('routers', 'hotspot_servidor')) {
                $table->string('hotspot_servidor', 64)->nullable()->after('ip_loopback');
            }
        });

        Schema::create('hotspot_perfiles', function (Blueprint $table) {
            $table->increments('hotspot_perfil_id');
            $table->string('nombre', 150);
            $table->string('rate_limit', 50)->nullable();
            $table->string('shared_users', 20)->nullable();
            $table->string('idle_timeout', 20)->nullable();
            $table->string('session_timeout', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('servicio_hotspot', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('servicio_id')->unique();
            $table->unsignedInteger('router_id');
            $table->unsignedInteger('hotspot_perfil_id')->nullable();
            $table->string('username', 64);
            $table->string('password', 64);
            $table->string('comment', 255)->nullable();
            $table->string('ros_id', 32)->nullable();
            $table->timestamp('last_synced')->nullable();
            $table->timestamps();

            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->onDelete('cascade');
            $table->foreign('router_id')->references('router_id')->on('routers')->onDelete('cascade');
            $table->foreign('hotspot_perfil_id')->references('hotspot_perfil_id')->on('hotspot_perfiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('servicio_hotspot');
        Schema::dropIfExists('hotspot_perfiles');
        Schema::table('routers', function (Blueprint $table) {
            if (Schema::hasColumn('routers', 'hotspot_servidor')) {
                $table->dropColumn('hotspot_servidor');
            }
        });
    }
};
