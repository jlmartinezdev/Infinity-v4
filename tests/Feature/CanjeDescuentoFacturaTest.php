<?php

namespace Tests\Feature;

use App\Models\Canje;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\Premio;
use App\Services\Loyalty\CanjeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CanjeDescuentoFacturaTest extends TestCase
{
    private CanjeService $canjes;

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('CACHE_STORE=array');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['CACHE_STORE'] = 'array';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['CACHE_STORE'] = 'array';

        parent::setUp();

        $conexion = (string) config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        config(['loyalty.limite_canjes_mes' => 20]);

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('cliente_id');
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->timestamps();
        });

        Schema::create('premios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('tipo')->nullable();
            $table->unsignedInteger('puntos_requeridos')->default(0);
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->decimal('descuento_monto', 15, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_aprobacion')->default(false);
            $table->unsignedInteger('tope_anual_por_cliente')->nullable();
            $table->boolean('una_vez_por_cliente')->default(false);
            $table->timestamps();
        });

        Schema::create('canjes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->unsignedBigInteger('premio_id');
            $table->unsignedBigInteger('factura_interna_id')->nullable();
            $table->unsignedInteger('puntos_usados')->default(0);
            $table->string('modalidad');
            $table->string('estado')->default('PENDIENTE');
            $table->text('notas')->nullable();
            $table->unsignedInteger('staff_user_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('factura_internas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->string('tipo_factura')->default('servicio');
            $table->date('periodo_desde')->nullable();
            $table->date('periodo_hasta')->nullable();
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado')->default('pendiente');
            $table->string('moneda', 3)->default('PYG');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_impuestos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('descuento', 15, 2)->default(0);
            $table->text('observaciones')->nullable();
            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('factura_interna_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_interna_id');
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->unsignedInteger('servicio_id')->nullable();
            $table->string('descripcion');
            $table->decimal('cantidad', 15, 4)->default(1);
            $table->decimal('precio_unitario', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('porcentaje_impuesto', 8, 2)->default(0);
            $table->decimal('monto_impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        $this->canjes = app(CanjeService::class);
    }

    public function test_el_canje_se_aplica_en_la_factura_emitida(): void
    {
        $premio = Premio::query()->create([
            'nombre' => 'Bienvenida App 10%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'activo' => true,
            'stock' => null,
        ]);
        $canje = $this->canjes->crear(9, $premio->id);
        $factura = $this->factura(9, 100000);

        $subtotal = 100000.0;
        $impuestos = 0.0;
        $total = 100000.0;
        $this->canjes->aplicarDescuentosEnFactura($factura, $subtotal, $impuestos, $total, null);

        $linea = FacturaInternaDetalle::query()->where('factura_interna_id', $factura->id)->first();
        $canje = $canje->fresh();

        $this->assertSame(90000.0, $total);
        $this->assertSame(-10000.0, (float) $linea->total);
        $this->assertStringContainsString('10%', $linea->descripcion);
        $this->assertSame(90000.0, (float) $factura->fresh()->total);
        $this->assertSame(Canje::ESTADO_APLICADO, $canje->estado);
        $this->assertSame($factura->id, (int) $canje->factura_interna_id);
        $this->assertSame('Aplicado en factura #'.$factura->id, $canje->toPortalArray()['estado_label']);
    }

    public function test_el_monto_fijo_descuenta_la_factura(): void
    {
        $premio = Premio::query()->create([
            'nombre' => '5000 de descuento',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_monto' => 5000,
            'activo' => true,
            'stock' => null,
        ]);
        $this->canjes->crear(9, $premio->id);
        $factura = $this->factura(9, 20000);
        $subtotal = 20000.0;
        $impuestos = 0.0;
        $total = 20000.0;

        $this->canjes->aplicarDescuentosEnFactura($factura, $subtotal, $impuestos, $total, null);

        $this->assertSame(15000.0, $total);
        $this->assertSame(Canje::ESTADO_APLICADO, Canje::query()->first()->estado);
    }

    public function test_requiere_aprobacion_espera_al_staff(): void
    {
        $premio = Premio::query()->create([
            'nombre' => 'Canjealo 5%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 5,
            'requiere_aprobacion' => true,
            'activo' => true,
            'stock' => null,
        ]);
        $canje = $this->canjes->crear(9, $premio->id);
        $factura = $this->factura(9, 100000);
        $subtotal = 100000.0;
        $impuestos = 0.0;
        $total = 100000.0;

        $this->canjes->aplicarDescuentosEnFactura($factura, $subtotal, $impuestos, $total, null);

        $this->assertSame(100000.0, $total);
        $this->assertSame(Canje::ESTADO_PENDIENTE, $canje->fresh()->estado);
        $this->assertSame(0, FacturaInternaDetalle::query()->count());

        $this->canjes->aprobarDescuento($canje->fresh(), 1);
        $this->assertSame(Canje::ESTADO_APROBADO, $canje->fresh()->estado);

        $this->canjes->aplicarDescuentosEnFactura($factura, $subtotal, $impuestos, $total, null);

        $this->assertSame(95000.0, $total);
        $this->assertSame(Canje::ESTADO_APLICADO, $canje->fresh()->estado);
        $this->assertSame($factura->id, (int) $canje->fresh()->factura_interna_id);
    }

    public function test_un_solo_canje_del_mismo_premio_por_factura(): void
    {
        $premio = Premio::query()->create([
            'nombre' => '10%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'activo' => true,
            'stock' => null,
            'tope_anual_por_cliente' => 5,
        ]);
        $primero = $this->canjes->crear(9, $premio->id);
        $segundo = $this->canjes->crear(9, $premio->id);
        $factura = $this->factura(9, 100000);
        $subtotal = 100000.0;
        $impuestos = 0.0;
        $total = 100000.0;

        $this->canjes->aplicarDescuentosEnFactura($factura, $subtotal, $impuestos, $total, null);

        $this->assertSame(90000.0, $total);
        $this->assertSame(Canje::ESTADO_APLICADO, $primero->fresh()->estado);
        $this->assertSame(Canje::ESTADO_PENDIENTE, $segundo->fresh()->estado);

        $siguiente = $this->factura(9, 80000);
        $subtotal = 80000.0;
        $impuestos = 0.0;
        $total = 80000.0;
        $this->canjes->aplicarDescuentosEnFactura($siguiente, $subtotal, $impuestos, $total, null);

        $this->assertSame(72000.0, $total);
        $this->assertSame(Canje::ESTADO_APLICADO, $segundo->fresh()->estado);
        $this->assertSame($siguiente->id, (int) $segundo->fresh()->factura_interna_id);
    }

    private function factura(int $clienteId, float $total): FacturaInterna
    {
        return FacturaInterna::query()->create([
            'cliente_id' => $clienteId,
            'tipo_factura' => FacturaInterna::TIPO_SERVICIO,
            'periodo_desde' => '2026-12-01',
            'periodo_hasta' => '2026-12-31',
            'fecha_emision' => '2026-12-01',
            'fecha_vencimiento' => '2027-01-05',
            'estado' => 'pendiente',
            'moneda' => 'PYG',
            'subtotal' => $total,
            'total_impuestos' => 0,
            'total' => $total,
        ]);
    }
}
