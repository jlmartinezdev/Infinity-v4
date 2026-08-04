<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('subtitulo', 300)->nullable();
            $table->string('imagen')->nullable();
            $table->string('accion_url', 500)->nullable();
            $table->string('tipo', 20)->default('promo'); // promo|aviso|upsell|referi
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['activa', 'orden']);
            $table->index('tipo');
        });

        Schema::create('premios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            $table->unsignedInteger('puntos_requeridos');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['activo', 'orden']);
        });

        Schema::create('cliente_puntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id')->unique();
            $table->integer('saldo')->default(0);
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });

        Schema::create('puntos_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->integer('puntos'); // +crédito / -débito
            $table->integer('saldo_despues');
            $table->string('tipo', 30); // credito|debito|ajuste|canje|reversa|regla
            $table->string('concepto', 255);
            $table->string('referencia_tipo', 50)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->index(['cliente_id', 'created_at']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });

        Schema::create('loyalty_reglas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->integer('puntos')->default(0);
            $table->boolean('activa')->default(true);
            $table->string('evento', 60)->default('manual'); // manual|pago_recibido|bienvenida
            $table->json('condiciones')->nullable();
            $table->timestamps();
        });

        Schema::create('canjes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->foreignId('premio_id')->constrained('premios')->restrictOnDelete();
            $table->unsignedInteger('puntos_usados');
            $table->string('modalidad', 30); // retiro_oficina|descuento_factura
            $table->string('estado', 30)->default('PENDIENTE');
            $table->text('notas')->nullable();
            $table->unsignedInteger('staff_user_id')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->index(['cliente_id', 'created_at']);
            $table->index(['estado', 'created_at']);
        });

        Schema::create('planes_upsell', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('plan_id');
            $table->text('beneficios')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('es_superior')->default(false);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->foreign('plan_id')->references('plan_id')->on('planes')->cascadeOnDelete();
            $table->unique('plan_id');
            $table->index(['activo', 'orden']);
        });

        Schema::create('upsell_staff_aviso', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('usuario_id');
            $table->timestamps();

            $table->unique('usuario_id');
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upsell_staff_aviso');
        Schema::dropIfExists('planes_upsell');
        Schema::dropIfExists('canjes');
        Schema::dropIfExists('loyalty_reglas');
        Schema::dropIfExists('puntos_movimientos');
        Schema::dropIfExists('cliente_puntos');
        Schema::dropIfExists('premios');
        Schema::dropIfExists('novedades');
    }
};
