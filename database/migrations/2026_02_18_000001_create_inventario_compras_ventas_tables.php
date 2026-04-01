<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('rif_nit', 50)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->text('direccion')->nullable();
            $table->text('notas')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('categorias_producto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias_producto')->nullOnDelete();
            $table->string('nombre', 200);
            $table->string('codigo', 50)->nullable()->unique();
            $table->string('unidad', 20)->default('unidad');
            $table->decimal('stock_actual', 12, 2)->default(0);
            $table->decimal('stock_minimo', 12, 2)->default(0);
            $table->decimal('precio_compra', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->text('descripcion')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->timestamps();
        });

        Schema::create('categorias_gasto', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->constrained('proveedores')->cascadeOnDelete();
            $table->date('fecha');
            $table->string('numero_factura', 100)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('pagado', 12, 2)->default(0);
            $table->string('estado', 20)->default('pendiente'); // pendiente, pagado, parcial, anulado
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->unsignedBigInteger('servicio_id')->nullable();
            $table->date('fecha');
            $table->string('numero_factura', 100)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('cobrado', 12, 2)->default(0);
            $table->string('estado', 20)->default('pendiente'); // pendiente, cobrado, parcial, anulado
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->nullOnDelete();
        });

        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('tipo', 20); // entrada, salida, ajuste
            $table->decimal('cantidad', 12, 2);
            $table->decimal('stock_anterior', 12, 2);
            $table->decimal('stock_nuevo', 12, 2);
            $table->string('referencia_tipo', 50)->nullable(); // compra, venta, ajuste
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->text('motivo')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_gasto_id')->constrained('categorias_gasto')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->text('descripcion')->nullable();
            $table->string('referencia', 100)->nullable();
            $table->boolean('pagado')->default(false);
            $table->timestamps();
        });

        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30); // compra, gasto
            $table->unsignedBigInteger('referencia_id');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 50)->default('efectivo'); // efectivo, transferencia, cheque, tarjeta
            $table->string('referencia_pago', 100)->nullable();
            $table->text('notas')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
        Schema::dropIfExists('gastos');
        Schema::dropIfExists('inventario_movimientos');
        Schema::dropIfExists('venta_detalles');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('compra_detalles');
        Schema::dropIfExists('compras');
        Schema::dropIfExists('categorias_gasto');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias_producto');
        Schema::dropIfExists('proveedores');
    }
};
