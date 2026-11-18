<?php

namespace Tests\Feature\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema mínimo de finanzas sobre sqlite en memoria (las migraciones reales
 * dependen de tablas de todo el sistema).
 */
trait TablasFinanzas
{
    protected function crearTablasFinanzas(): void
    {
        $conexion = (string) config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        foreach (['fin_conciliaciones', 'fin_retiros', 'fin_transferencias', 'fin_control_gasto_meses', 'fin_cierres', 'fin_egresos', 'fin_egreso_plantillas', 'fin_movimientos', 'fin_categorias', 'fin_cuentas'] as $tabla) {
            Schema::dropIfExists($tabla);
        }

        Schema::create('fin_cuentas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 120);
            $table->string('tipo', 20);
            $table->boolean('activa')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
        Schema::create('fin_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('clase', 20);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
        Schema::create('fin_movimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->string('tipo', 30);
            $table->tinyInteger('signo');
            $table->date('fecha_efectiva');
            $table->decimal('importe', 15, 2);
            $table->string('concepto', 180);
            $table->unsignedBigInteger('categoria_id')->nullable();
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_texto', 120)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('revertido_por_id')->nullable();
            $table->unsignedBigInteger('transferencia_id')->nullable();
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_egreso_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 160)->unique();
            $table->decimal('monto', 15, 2);
            $table->boolean('es_sueldo')->default(false);
            $table->unsignedInteger('usuario_staff_id')->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
        Schema::create('fin_egresos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->unsignedBigInteger('categoria_id');
            $table->unsignedBigInteger('plantilla_id')->nullable();
            $table->date('fecha');
            $table->decimal('importe', 15, 2);
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->string('comprobante', 80)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->unsignedInteger('usuario_staff_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('contabilizado');
            $table->string('documento_path')->nullable();
            $table->string('factura_path')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_cierres', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->string('tipo', 20);
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            foreach (['saldo_inicial', 'ingresos', 'egresos', 'transferencias_entrada', 'transferencias_salida', 'retiros', 'ajustes', 'saldo_calculado', 'saldo_real', 'diferencia'] as $columna) {
                $table->decimal($columna, 15, 2)->default(0);
            }
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_control_gasto_meses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('estado', 20)->default('abierta');
            $table->unsignedBigInteger('cuenta_id')->nullable();
            $table->decimal('importe', 15, 2)->nullable();
            $table->unsignedBigInteger('fin_egreso_id')->nullable();
            $table->unsignedInteger('abierto_por')->nullable();
            $table->timestamp('abierto_en')->nullable();
            $table->unsignedInteger('cerrado_por')->nullable();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_transferencias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_origen_id');
            $table->unsignedBigInteger('cuenta_destino_id');
            $table->decimal('importe', 15, 2);
            $table->date('fecha');
            $table->string('numero_referencia', 80)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('contabilizada');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();
        });
        Schema::create('fin_retiros', function (Blueprint $table) {
            $table->id();
            $table->string('persona', 160);
            $table->date('fecha');
            $table->decimal('importe', 15, 2);
            $table->unsignedBigInteger('cuenta_id');
            $table->string('motivo', 180);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('autorizacion', 160)->nullable();
            $table->string('comprobante', 80)->nullable();
            $table->string('estado', 20)->default('contabilizado');
            $table->timestamps();
        });
        Schema::create('fin_conciliaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cuenta_id');
            $table->date('fecha');
            $table->decimal('saldo_sistema', 15, 2);
            $table->decimal('saldo_real', 15, 2);
            $table->decimal('diferencia', 15, 2);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('ajuste_movimiento_id')->nullable();
            $table->timestamps();
        });
    }
}
