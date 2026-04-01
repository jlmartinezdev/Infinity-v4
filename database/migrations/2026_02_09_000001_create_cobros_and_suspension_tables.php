<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cobros (pagos recibidos), recibos, y campos de suspensión en servicios.
     * Gestión: factura interna → emisión → cobro → recibo; suspensión por falta de pago; activación.
     */
    public function up(): void
    {
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->restrictOnDelete();

            $table->foreignId('factura_id')->nullable()->constrained('facturas')->nullOnDelete()
                ->comment('Factura a la que se aplica el cobro (null = anticipo/otro)');

            $table->decimal('monto', 15, 2);
            $table->date('fecha_pago');
            $table->string('forma_pago', 50)->default('efectivo')
                ->comment('efectivo, transferencia, tarjeta, cheque, otro');
            $table->string('numero_recibo', 50)->unique()->comment('Número de recibo de pago');
            $table->string('referencia', 100)->nullable()->comment('Nº cheque, ref transferencia, etc.');
            $table->string('concepto', 100)->nullable()->comment('Mensualidad, reconexión, anticipo, etc.');
            $table->text('observaciones')->nullable();

            $table->unsignedInteger('usuario_id')->nullable();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['cliente_id', 'fecha_pago']);
            $table->index(['factura_id']);
        });

        Schema::table('servicios', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios', 'fecha_suspension')) {
                $table->date('fecha_suspension')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('servicios', 'motivo_suspension')) {
                $table->string('motivo_suspension', 255)->nullable()->after('fecha_suspension');
            }
        });

        Schema::create('facturacion_parametros', function (Blueprint $table) {
            $table->string('clave', 80)->primary();
            $table->text('valor')->nullable();
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (Schema::hasColumn('servicios', 'fecha_suspension')) {
                $table->dropColumn('fecha_suspension');
            }
            if (Schema::hasColumn('servicios', 'motivo_suspension')) {
                $table->dropColumn('motivo_suspension');
            }
        });
        Schema::dropIfExists('cobros');
        Schema::dropIfExists('facturacion_parametros');
    }
};
