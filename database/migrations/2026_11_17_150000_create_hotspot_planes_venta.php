<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotspot_planes', function (Blueprint $table) {
            $table->id('hotspot_plan_id');
            $table->string('nombre', 80);
            $table->unsignedInteger('gigas');
            $table->unsignedInteger('precio');
            $table->unsignedSmallInteger('vigencia_horas')->default(24);
            $table->string('rate_limit', 50)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('hotspot_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotspot_plan_id')->nullable()->index();
            $table->string('token', 64)->unique();
            $table->string('mac', 32)->nullable()->index();
            $table->unsignedInteger('monto');
            $table->unsignedInteger('gigas');
            $table->unsignedSmallInteger('vigencia_horas')->default(24);
            $table->string('rate_limit', 50)->nullable();
            $table->string('estado', 20)->default('pendiente')->index();
            $table->string('username', 32)->nullable()->index();
            $table->string('password', 16)->nullable();
            $table->unsignedBigInteger('tpago_payment_link_id')->nullable()->index();
            $table->string('checkout_url', 1000)->nullable();
            $table->boolean('radius_ok')->default(false);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamp('vence_at')->nullable();
            $table->timestamps();

            $table->foreign('hotspot_plan_id')
                ->references('hotspot_plan_id')
                ->on('hotspot_planes')
                ->nullOnDelete();
        });

        Schema::table('tpago_payment_links', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotspot_ventas');
        Schema::dropIfExists('hotspot_planes');

        Schema::table('tpago_payment_links', function (Blueprint $table) {
            $table->unsignedBigInteger('cliente_id')->nullable(false)->change();
        });
    }
};
