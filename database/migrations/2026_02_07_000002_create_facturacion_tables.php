<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tablas de facturación (Paraguay): impuestos, facturas, factura_detalles.
     * Preparado para factura electrónica SET/DNIT (timbrado, CDC, QR, XML).
     */
    public function up(): void
    {
        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique()->comment('Ej: IVA10, IVA5, EXENTO');
            $table->string('nombre', 100);
            $table->decimal('porcentaje', 5, 2)->default(0)->comment('Porcentaje aplicable (10, 5, 0)');
            $table->boolean('activo')->default(true);
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('facturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->restrictOnDelete();

            $table->string('tipo_documento', 30)->default('factura_contado')
                ->comment('factura_contado, factura_credito, nota_credito, etc.');
            $table->string('estado', 20)->default('borrador')
                ->comment('borrador, emitida, anulada');

            $table->string('numero_timbrado', 20)->nullable()->comment('Número de timbrado SET (Paraguay)');
            $table->date('timbrado_vigencia_desde')->nullable();
            $table->date('timbrado_vigencia_hasta')->nullable();
            $table->unsignedTinyInteger('establecimiento')->default(1)->comment('Código establecimiento 001');
            $table->unsignedTinyInteger('punto_emision')->default(1)->comment('Código punto emisión 001');
            $table->unsignedInteger('numero')->nullable()->comment('Número secuencial factura (001-001-0001234)');

            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('moneda', 3)->default('PYG')->comment('PYG, USD');
            $table->decimal('tipo_cambio', 12, 4)->nullable()->comment('Cambio si moneda diferente a operativa');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_impuestos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('observaciones')->nullable();

            $table->unsignedInteger('usuario_id')->nullable()->comment('Usuario que emitió');
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();

            $table->string('set_cdc', 50)->nullable()->comment('Código de control SET (factura electrónica)');
            $table->string('set_qr_url', 500)->nullable()->comment('URL o datos QR SET');
            $table->timestamp('set_fecha_autorizacion')->nullable();
            $table->string('set_estado_envio', 30)->nullable()->comment('pendiente, autorizado, rechazado');
            $table->string('xml_path', 500)->nullable()->comment('Ruta XML firmado');
            $table->string('pdf_path', 500)->nullable()->comment('Ruta PDF generado');

            $table->timestamps();

            $table->index(['cliente_id', 'fecha_emision']);
            $table->index(['estado', 'fecha_emision']);
            $table->index(['numero_timbrado', 'establecimiento', 'punto_emision', 'numero'], 'facturas_numero_idx');
        });

        Schema::create('factura_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas')->cascadeOnDelete();
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->foreign('impuesto_id')->references('id')->on('impuestos')->nullOnDelete();

            $table->string('descripcion');
            $table->decimal('cantidad', 12, 4)->default(1);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0);
            $table->decimal('monto_impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);

            $table->unsignedBigInteger('servicio_id')->nullable()->comment('Servicio asociado si aplica');
            $table->timestamps();

            $table->index('factura_id');
        });

        Schema::table('factura_detalles', function (Blueprint $table) {
            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_detalles');
        Schema::dropIfExists('facturas');
        Schema::dropIfExists('impuestos');
    }
};
