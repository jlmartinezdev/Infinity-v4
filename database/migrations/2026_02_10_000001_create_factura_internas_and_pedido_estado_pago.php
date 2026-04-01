<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla factura_internas (facturación interna por servicios), sus detalles,
     * y campo estado_pago en pedidos.
     */
    public function up(): void
    {
        Schema::create('factura_internas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->restrictOnDelete();

            $table->date('periodo_desde')->comment('Inicio período facturado');
            $table->date('periodo_hasta')->comment('Fin período facturado');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 20)->default('emitida')->comment('emitida, anulada');
            $table->string('moneda', 3)->default('PYG');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_impuestos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();

            $table->unsignedInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['cliente_id', 'fecha_emision']);
            $table->index(['estado']);
        });

        Schema::create('factura_interna_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_interna_id')->constrained('factura_internas')->cascadeOnDelete();
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id')->references('id')->on('impuestos')->nullOnDelete();
            $table->unsignedBigInteger('servicio_id')->nullable();
            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->nullOnDelete();

            $table->string('descripcion');
            $table->decimal('cantidad', 12, 4)->default(1);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0);
            $table->decimal('monto_impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            $table->timestamps();
            $table->index('factura_interna_id');
        });

        Schema::table('pedidos', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos', 'estado_pago')) {
                $table->string('estado_pago', 30)->nullable()->after('estado_instalado')
                    ->comment('pendiente, pagado, parcial, exento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos', 'estado_pago')) {
                $table->dropColumn('estado_pago');
            }
        });
        Schema::dropIfExists('factura_interna_detalles');
        Schema::dropIfExists('factura_internas');
    }
};
