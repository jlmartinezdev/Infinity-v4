<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->foreignId('cuenta_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->tinyInteger('signo');
            $table->date('fecha_efectiva');
            $table->decimal('importe', 15, 2);
            $table->string('concepto', 180);
            $table->foreignId('categoria_id')->nullable()->constrained('fin_categorias')->nullOnDelete();
            $table->string('referencia_tipo', 40)->nullable();
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('referencia_texto', 120)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->unsignedBigInteger('revertido_por_id')->nullable();
            $table->unsignedBigInteger('transferencia_id')->nullable();
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id', 'fecha_efectiva']);
            $table->index(['referencia_tipo', 'referencia_id']);
            $table->unique(['referencia_tipo', 'referencia_id', 'tipo'], 'fin_mov_ref_unica');
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::table('fin_movimientos', function (Blueprint $table) {
            $table->foreign('revertido_por_id')->references('id')->on('fin_movimientos')->nullOnDelete();
        });

        Schema::create('fin_transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_origen_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->foreignId('cuenta_destino_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->decimal('importe', 15, 2);
            $table->date('fecha');
            $table->string('numero_referencia', 80)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('contabilizada');
            $table->unsignedBigInteger('liquidacion_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->index('fecha');
        });

        Schema::create('fin_liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_liquidacion');
            $table->string('referencia', 80);
            $table->decimal('bruto', 15, 2);
            $table->decimal('comision', 15, 2)->default(0);
            $table->decimal('neto', 15, 2);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('contabilizada');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
        });

        Schema::create('fin_liquidacion_cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('fin_liquidaciones')->cascadeOnDelete();
            $table->foreignId('cobro_id')->nullable()->constrained('cobros')->nullOnDelete();
            $table->decimal('importe', 15, 2);
            $table->unique('cobro_id');
        });

        Schema::create('fin_egresos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->foreignId('categoria_id')->constrained('fin_categorias')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('importe', 15, 2);
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
            $table->string('comprobante', 80)->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('estado', 20)->default('contabilizado');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->index('fecha');
        });

        Schema::create('fin_retiros', function (Blueprint $table) {
            $table->id();
            $table->string('persona', 160);
            $table->date('fecha');
            $table->decimal('importe', 15, 2);
            $table->foreignId('cuenta_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->string('motivo', 180);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->string('autorizacion', 160)->nullable();
            $table->string('comprobante', 80)->nullable();
            $table->string('estado', 20)->default('contabilizado');
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->index('fecha');
        });

        Schema::create('fin_conciliaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->date('fecha');
            $table->decimal('saldo_sistema', 15, 2);
            $table->decimal('saldo_real', 15, 2);
            $table->decimal('diferencia', 15, 2);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('ajuste_movimiento_id')->nullable()->constrained('fin_movimientos')->nullOnDelete();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->index(['cuenta_id', 'fecha']);
        });

        Schema::create('fin_cierres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_id')->constrained('fin_cuentas')->restrictOnDelete();
            $table->string('tipo', 20);
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->decimal('saldo_inicial', 15, 2);
            $table->decimal('ingresos', 15, 2);
            $table->decimal('egresos', 15, 2);
            $table->decimal('transferencias_entrada', 15, 2);
            $table->decimal('transferencias_salida', 15, 2);
            $table->decimal('retiros', 15, 2);
            $table->decimal('ajustes', 15, 2);
            $table->decimal('saldo_calculado', 15, 2);
            $table->decimal('saldo_real', 15, 2);
            $table->decimal('diferencia', 15, 2);
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('usuario_id')->on('users')->nullOnDelete();
            $table->unique(['cuenta_id', 'tipo', 'periodo_desde'], 'fin_cierre_unico');
        });

        $ahora = now();
        DB::table('fin_cuentas')->insert([
            ['codigo' => 'efectivo', 'nombre' => 'Efectivo', 'tipo' => 'efectivo', 'activa' => true, 'orden' => 1, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['codigo' => 'banco', 'nombre' => 'Transferencia bancaria', 'tipo' => 'banco', 'activa' => true, 'orden' => 2, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['codigo' => 'bancard', 'nombre' => 'Bancard / TPago', 'tipo' => 'pasarela', 'activa' => true, 'orden' => 3, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        DB::table('fin_categorias')->insert([
            ['nombre' => 'Cobro de clientes', 'clase' => 'ingreso', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Comisión Bancard', 'clase' => 'comision', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Gasto operativo', 'clase' => 'opex', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Inversión', 'clase' => 'capex', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Retiro', 'clase' => 'retiro', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Ajuste de conciliación', 'clase' => 'ajuste', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['nombre' => 'Saldo inicial', 'clase' => 'ajuste', 'activa' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);

        foreach ([
            ['finanzas.ver', 'Finanzas — Ver', 210],
            ['finanzas.crear', 'Finanzas — Crear', 211],
            ['finanzas.editar', 'Finanzas — Editar', 212],
        ] as [$codigo, $nombre, $orden]) {
            DB::table('permisos')->updateOrInsert(
                ['codigo' => $codigo],
                ['nombre' => $nombre, 'categoria' => 'Finanzas', 'orden' => $orden, 'created_at' => $ahora, 'updated_at' => $ahora]
            );
        }

        $rolId = DB::table('roles')->whereRaw('LOWER(descripcion) = ?', ['administrador'])->value('rol_id');
        if ($rolId) {
            $permisoIds = DB::table('permisos')->whereIn('codigo', ['finanzas.ver', 'finanzas.crear', 'finanzas.editar'])->pluck('id');
            foreach ($permisoIds as $permisoId) {
                DB::table('rol_permiso')->insertOrIgnore([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fin_cierres');
        Schema::dropIfExists('fin_conciliaciones');
        Schema::dropIfExists('fin_retiros');
        Schema::dropIfExists('fin_egresos');
        Schema::dropIfExists('fin_liquidacion_cobros');
        Schema::dropIfExists('fin_liquidaciones');
        Schema::dropIfExists('fin_transferencias');
        Schema::table('fin_movimientos', function (Blueprint $table) {
            $table->dropForeign(['revertido_por_id']);
        });
        Schema::dropIfExists('fin_movimientos');
        Schema::dropIfExists('fin_categorias');
        Schema::dropIfExists('fin_cuentas');
    }
};
