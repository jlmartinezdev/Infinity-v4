<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Cupon;
use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\SorteoCampana;
use App\Models\SorteoTicket;
use App\Services\Sorteo\SorteoService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SorteoNavidadTest extends TestCase
{
    private SorteoService $sorteo;

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

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('cliente_id');
            $table->string('cedula')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('telefono')->nullable();
            $table->string('estado')->default('activo');
            $table->boolean('app_activa')->default(false);
            $table->dateTime('fecha_activacion_app')->nullable();
            $table->timestamps();
        });

        Schema::create('factura_internas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->string('tipo_factura')->default('servicio');
            $table->date('periodo_desde')->nullable();
            $table->date('periodo_hasta')->nullable();
            $table->date('fecha_emision')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado')->default('emitida');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('total_impuestos', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('factura_interna_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_interna_id');
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->unsignedBigInteger('servicio_id')->nullable();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 4)->default(1);
            $table->decimal('precio_unitario', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('porcentaje_impuesto', 5, 2)->default(0);
            $table->decimal('monto_impuesto', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });

        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->unsignedBigInteger('factura_interna_id')->nullable();
            $table->decimal('monto', 15, 2);
            $table->dateTime('fecha_pago');
            $table->string('forma_pago')->default('efectivo');
            $table->string('numero_recibo')->nullable();
            $table->timestamps();
        });

        Schema::create('facturacion_parametros', function (Blueprint $table) {
            $table->string('clave')->primary();
            $table->text('valor')->nullable();
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('cobro_factura_interna', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('factura_interna_id');
            $table->decimal('monto', 15, 2);
            $table->timestamps();
        });

        $migration = require base_path('database/migrations/2026_11_22_210000_create_sorteo_navideno_tables.php');
        $migration->up();

        $this->sorteo = app(SorteoService::class);
    }

    public function test_pago_el_5_de_diciembre_a_las_2359_es_apto(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-05 23:59:00');

        $this->sorteo->evaluarCobro($cobro);

        $this->assertSame(1, SorteoTicket::query()->where('origen', 'pago')->where('estado', 'activo')->count());
        $this->assertSame('50', $this->sorteo->clasificar(SorteoCampana::activa(), $cliente)['nivel']);
    }

    public function test_pago_el_6_de_diciembre_a_las_0000_no_es_apto(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-06 00:00:00');

        $this->sorteo->evaluarCobro($cobro);

        $this->assertSame(0, SorteoTicket::query()->count());
        $this->assertSame('pago_tarde', $this->sorteo->clasificar(SorteoCampana::activa(), $cliente)['motivo']);
    }

    public function test_pago_anticipado_de_noviembre_es_apto(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-11-20 10:00:00');

        $this->sorteo->evaluarCobro($cobro);

        $this->assertSame(1, SorteoTicket::query()->where('estado', 'activo')->count());
    }

    public function test_anticipo_en_saldo_a_favor_cubre_la_factura(): void
    {
        [$cliente] = $this->clienteConFacturaNoviembre(false);
        $cobro = Cobro::withoutEvents(fn () => Cobro::create([
            'cliente_id' => $cliente->cliente_id,
            'monto' => 100000,
            'fecha_pago' => '2026-11-18 09:00:00',
            'forma_pago' => 'efectivo',
            'numero_recibo' => 'A-1',
        ]));
        $factura = FacturaInterna::withoutEvents(fn () => FacturaInterna::create([
            'cliente_id' => $cliente->cliente_id,
            'tipo_factura' => 'servicio',
            'periodo_desde' => '2026-11-01',
            'periodo_hasta' => '2026-11-30',
            'fecha_emision' => '2026-12-01',
            'fecha_vencimiento' => '2026-12-05',
            'estado' => 'pagada',
            'subtotal' => 0,
            'total_impuestos' => 0,
            'total' => 0,
        ]));
        FacturaInternaDetalle::create([
            'factura_interna_id' => $factura->id,
            'descripcion' => 'Saldo a favor aplicado',
            'precio_unitario' => -100000,
            'subtotal' => -100000,
            'total' => -100000,
        ]);

        $this->sorteo->evaluarFacturaRecienEmitida($factura->fresh());

        $this->assertSame(1, SorteoTicket::query()->where('estado', 'activo')->count());
        $this->assertNotNull($cobro->id);
    }

    public function test_cliente_sin_app_no_es_apto(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cliente->update(['app_activa' => false, 'fecha_activacion_app' => null]);
        $cobro = $this->cobro($cliente, $factura, '2026-12-04 12:00:00');

        $this->sorteo->evaluarCobro($cobro);

        $this->assertSame(0, SorteoTicket::query()->count());
        $this->assertSame('sin_app', $this->sorteo->clasificar(SorteoCampana::activa(), $cliente->fresh())['motivo']);
    }

    public function test_reprocesar_el_mismo_pago_no_duplica_el_ticket(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-05 23:59:00');

        $this->sorteo->evaluarCobro($cobro);
        $this->sorteo->evaluarCobro($cobro->fresh());

        $this->assertSame(1, SorteoTicket::query()->count());
    }

    public function test_anular_y_volver_a_cobrar_reactiva_el_mismo_numero(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-03 11:00:00');
        $this->sorteo->evaluarCobro($cobro);
        $numero = SorteoTicket::query()->value('numero');

        $this->sorteo->anularPorCobro($cobro);
        $this->assertSame('anulado', SorteoTicket::query()->value('estado'));

        DB::table('cobro_factura_interna')->where('cobro_id', $cobro->id)->delete();
        $otro = $this->cobro($cliente, $factura, '2026-12-04 11:00:00');
        $this->sorteo->evaluarCobro($otro);

        $this->assertSame(1, SorteoTicket::query()->count());
        $this->assertSame($numero, SorteoTicket::query()->value('numero'));
        $this->assertSame('activo', SorteoTicket::query()->value('estado'));
    }

    public function test_cupon_de_bienvenida_no_se_canjea_dos_veces(): void
    {
        [$cliente] = $this->clienteConFacturaNoviembre(false);
        $factura = FacturaInterna::withoutEvents(fn () => FacturaInterna::create([
            'cliente_id' => $cliente->cliente_id,
            'tipo_factura' => 'servicio',
            'periodo_desde' => '2027-01-01',
            'periodo_hasta' => '2027-01-31',
            'fecha_emision' => '2027-02-01',
            'fecha_vencimiento' => '2027-02-05',
            'estado' => 'emitida',
            'subtotal' => 100000,
            'total_impuestos' => 0,
            'total' => 100000,
        ]));
        $cupon = Cupon::create([
            'cliente_id' => $cliente->cliente_id,
            'tipo' => Cupon::TIPO_BIENVENIDA,
            'porcentaje' => 10,
            'ventana' => '',
            'estado' => Cupon::PENDIENTE,
        ]);

        $this->sorteo->aplicarCupon($cupon, $factura);
        $this->assertEquals(90000.0, (float) $factura->fresh()->total);

        try {
            $this->sorteo->aplicarCupon($cupon->fresh(), $factura->fresh());
            $this->fail('El segundo canje debía rechazarse.');
        } catch (DomainException $e) {
            $this->assertSame('El cupón ya fue canjeado.', $e->getMessage());
        }

        $this->assertEquals(90000.0, (float) $factura->fresh()->total);
        $this->assertSame(1, Cupon::query()->where('estado', Cupon::CANJEADO)->count());
    }

    public function test_tres_vencimientos_puntuales_es_apto_100(): void
    {
        $cliente = $this->clienteApp();
        $this->pagarMes($cliente, '2026-09-01', '2026-09-30', '2026-10-01', '2026-10-05', '2026-10-03 09:00:00');
        $this->pagarMes($cliente, '2026-10-01', '2026-10-31', '2026-11-01', '2026-11-05', '2026-11-04 09:00:00');
        $noviembre = $this->pagarMes($cliente, '2026-11-01', '2026-11-30', '2026-12-01', '2026-12-05', '2026-12-05 10:00:00');

        $this->sorteo->evaluarCobro($noviembre['cobro']);

        $this->assertSame('100', $this->sorteo->clasificar(SorteoCampana::activa(), $cliente->fresh())['nivel']);
    }

    public function test_campana_inactiva_no_crea_ticket_y_sin_impresion_el_recibo_no_lleva_cupon(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-04 12:00:00');
        $campana = SorteoCampana::activa();
        $campana->update(['activa' => false]);

        $this->sorteo->evaluarCobro($cobro->fresh());
        $this->assertSame(0, SorteoTicket::query()->count());

        $campana->update(['activa' => true, 'imprimir_en_recibo' => false]);
        $this->sorteo->evaluarCobro($cobro->fresh());
        $this->assertSame(1, SorteoTicket::query()->where('estado', 'activo')->count());
        $this->assertNull($this->sorteo->bloqueRecibo($cobro->fresh()));
    }

    public function test_otra_campana_usa_sus_propias_fechas(): void
    {
        $vigente = SorteoCampana::activa();
        $vigente->update(['activa' => false]);
        $otra = SorteoCampana::create([
            'nombre' => 'Promo enero',
            'texto_ticket' => 'PROMO ENERO',
            'fecha_sorteo' => '2027-01-20',
            'timezone' => 'America/Asuncion',
            'periodo_uso_desde' => '2026-12-01',
            'periodo_uso_hasta' => '2026-12-31',
            'pago_limite' => '2027-01-05 23:59:59',
            'criterio_puntualidad' => 'vencimiento',
            'dia_puntualidad' => 5,
            'meses_puntualidad_100' => ['2027-01'],
            'exige_app' => true,
            'permite_multiples_premios' => false,
            'activa' => true,
            'imprimir_en_recibo' => true,
        ]);

        $cliente = $this->clienteApp();
        $diciembre = $this->pagarMes($cliente, '2026-12-01', '2026-12-31', '2027-01-01', '2027-01-05', '2027-01-04 15:00:00');
        $this->sorteo->evaluarCobro($diciembre['cobro']);

        $this->assertSame('100', $this->sorteo->clasificar($otra->fresh(), $cliente->fresh())['nivel']);
        $this->assertSame('sin_pago', $this->sorteo->clasificar($vigente->fresh(), $cliente->fresh())['motivo']);
        $this->assertSame(1, SorteoTicket::query()->where('campana_id', $otra->id)->count());
    }

    public function test_resena_actualiza_celular_y_el_recibo_trae_el_talon(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $cobro = $this->cobro($cliente, $factura, '2026-12-02 16:40:00');
        Cliente::withoutEvents(fn () => $cliente->update(['telefono' => '0981123456']));
        $this->sorteo->guardarResena(SorteoCampana::activa(), $cliente->fresh(), '0981123456', 1, $cobro->id);
        $this->sorteo->evaluarCobro($cobro->fresh());

        $this->assertSame('0981123456', $cliente->fresh()->telefono);
        $this->assertSame(2, SorteoTicket::query()->where('estado', 'activo')->count());

        $cliente = $cliente->fresh();
        $cliente->setRelation('servicios', collect());
        $cobro->setRelation('cliente', $cliente);
        $cobro->setRelation('facturaInternas', collect());
        $cobro->setRelation('usuario', null);
        $html = view('cobros._recibo-contenido', ['cobro' => $cobro, 'ajustes' => null])->render();
        $this->assertStringContainsString('TICKET NRO', $html);
        $this->assertStringContainsString('TICKET EXTRA', $html);
        $this->assertStringContainsString('CORTE Y RETENER EN CAJA', $html);
        $this->assertStringContainsString('0981123456', $html);
        $this->assertStringContainsString('8&lt;', $html);
    }

    public function test_backfill_dry_run_cuenta_sin_escribir(): void
    {
        [$cliente, $factura] = $this->clienteConFacturaNoviembre();
        $this->cobro($cliente, $factura, '2026-12-01 08:00:00');

        $this->artisan('sorteo:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Tickets nuevos: 1')
            ->assertSuccessful();

        $this->assertSame(0, SorteoTicket::query()->count());
    }

    /**
     * @return array{0: Cliente, 1: FacturaInterna}
     */
    private function clienteConFacturaNoviembre(bool $conFactura = true): array
    {
        $cliente = $this->clienteApp();
        if (! $conFactura) {
            return [$cliente, new FacturaInterna];
        }
        $pagado = $this->pagarMes($cliente, '2026-11-01', '2026-11-30', '2026-12-01', '2026-12-05', '2026-12-01 08:00:00', false);

        return [$cliente, $pagado['factura']];
    }

    private function clienteApp(): Cliente
    {
        return Cliente::withoutEvents(fn () => Cliente::create([
            'cedula' => (string) random_int(1000000, 9999999),
            'nombre' => 'Juan',
            'apellido' => 'Perez',
            'estado' => 'activo',
            'app_activa' => true,
            'fecha_activacion_app' => '2026-11-01 08:00:00',
        ]));
    }

    /**
     * @return array{factura: FacturaInterna, cobro: Cobro}
     */
    private function pagarMes(Cliente $cliente, string $desde, string $hasta, string $emision, string $vence, string $fechaPago, bool $conCobro = true): array
    {
        $factura = FacturaInterna::withoutEvents(fn () => FacturaInterna::create([
            'cliente_id' => $cliente->cliente_id,
            'tipo_factura' => 'servicio',
            'periodo_desde' => $desde,
            'periodo_hasta' => $hasta,
            'fecha_emision' => $emision,
            'fecha_vencimiento' => $vence,
            'estado' => 'emitida',
            'subtotal' => 100000,
            'total_impuestos' => 0,
            'total' => 100000,
        ]));
        $cobro = $conCobro ? $this->cobro($cliente, $factura, $fechaPago) : new Cobro;

        return ['factura' => $factura, 'cobro' => $cobro];
    }

    private function cobro(Cliente $cliente, FacturaInterna $factura, string $fecha): Cobro
    {
        $cobro = Cobro::withoutEvents(fn () => Cobro::create([
            'cliente_id' => $cliente->cliente_id,
            'factura_interna_id' => $factura->id,
            'monto' => 100000,
            'fecha_pago' => $fecha,
            'forma_pago' => 'efectivo',
            'numero_recibo' => 'R-'.random_int(1000, 9999),
        ]));
        $cobro->facturaInternas()->attach($factura->id, ['monto' => 100000]);

        return $cobro->fresh(['cliente', 'facturaInternas']);
    }
}
