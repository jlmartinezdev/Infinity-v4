<?php

namespace Tests\Feature;

use App\Models\FinCategoria;
use App\Models\FinCierre;
use App\Models\FinControlGastoMes;
use App\Models\FinCuenta;
use App\Models\FinEgreso;
use App\Models\FinEgresoPlantilla;
use App\Models\FinMovimiento;
use App\Services\Finanzas\FinanzasLibroService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class FinanzasEgresosTest extends TestCase
{
    private FinCuenta $cuenta;

    private FinCategoria $opex;

    private FinCategoria $capex;

    protected function setUp(): void
    {
        parent::setUp();

        $conexion = (string) config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        foreach (['fin_control_gasto_meses', 'fin_cierres', 'fin_egresos', 'fin_egreso_plantillas', 'fin_movimientos', 'fin_categorias', 'fin_cuentas'] as $tabla) {
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

        $this->cuenta = FinCuenta::query()->create(['codigo' => 'banco', 'nombre' => 'Banco', 'tipo' => 'banco', 'activa' => true, 'orden' => 1]);
        $this->opex = FinCategoria::query()->create(['nombre' => 'Gasto operativo', 'clase' => 'opex', 'activa' => true]);
        $this->capex = FinCategoria::query()->create(['nombre' => 'Inversión', 'clase' => 'capex', 'activa' => true]);
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckPermiso::class]);
    }

    private function libro(): FinanzasLibroService
    {
        return app(FinanzasLibroService::class);
    }

    private function egreso(float $importe = 100000, ?string $fecha = null): FinEgreso
    {
        return $this->libro()->registrarEgreso(
            $this->cuenta->id,
            $this->opex->id,
            Carbon::parse($fecha ?? now()->toDateString()),
            $importe,
            null,
            null,
            null,
            null,
            ['concepto' => 'Compra de conectores'],
        );
    }

    public function test_anular_un_egreso_lo_revierte_y_devuelve_el_saldo(): void
    {
        $egreso = $this->egreso(250000);
        $this->assertSame(-250000.0, $this->libro()->saldoActual($this->cuenta->id));

        $reverso = $this->libro()->anularEgreso($egreso, 'Importe mal cargado', null);

        $this->assertSame('anulado', $egreso->fresh()->estado);
        $this->assertSame(0.0, $this->libro()->saldoActual($this->cuenta->id));
        $this->assertSame('Anulación: Importe mal cargado', $reverso->observaciones);
        $this->assertSame($egreso->fecha->toDateString(), $reverso->fecha_efectiva->toDateString());
        $this->assertSame($reverso->id, FinMovimiento::query()->where('referencia_tipo', 'egreso')->value('revertido_por_id'));
        $this->assertSame(0.0, $this->libro()->sumaClase('opex', now()->startOfMonth(), now()->endOfMonth()));
    }

    public function test_no_se_anula_dos_veces_ni_sin_motivo(): void
    {
        $egreso = $this->egreso();

        try {
            $this->libro()->anularEgreso($egreso, '   ', null);
            $this->fail('Debió pedir el motivo.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('motivo', $e->getMessage());
        }

        $this->libro()->anularEgreso($egreso, 'Duplicado', null);
        $this->expectException(InvalidArgumentException::class);
        $this->libro()->anularEgreso($egreso, 'Otra vez', null);
    }

    public function test_si_el_periodo_esta_cerrado_el_reverso_va_con_fecha_de_hoy(): void
    {
        $fechaVieja = now()->subMonthNoOverflow()->startOfMonth()->addDays(4);
        $egreso = $this->egreso(80000, $fechaVieja->toDateString());
        FinCierre::query()->create([
            'cuenta_id' => $this->cuenta->id,
            'tipo' => 'mensual',
            'periodo_desde' => $fechaVieja->copy()->startOfMonth()->toDateString(),
            'periodo_hasta' => $fechaVieja->copy()->endOfMonth()->toDateString(),
        ]);

        $reverso = $this->libro()->anularEgreso($egreso, 'Pago duplicado', null);

        $this->assertSame(now()->toDateString(), $reverso->fecha_efectiva->toDateString());
        $this->assertSame(-80000.0, $this->libro()->saldoHasta($this->cuenta->id, $fechaVieja->copy()->endOfMonth()));
        $this->assertSame(0.0, $this->libro()->saldoActual($this->cuenta->id));
    }

    public function test_el_resumen_de_control_de_gastos_no_se_anula_aqui(): void
    {
        $egreso = $this->egreso();
        FinControlGastoMes::query()->create(['anio' => now()->year, 'mes' => now()->month, 'estado' => 'cerrada', 'fin_egreso_id' => $egreso->id]);

        $this->expectExceptionMessage('reabriendo el mes');
        $this->libro()->anularEgreso($egreso, 'No corresponde', null);
    }

    public function test_un_gasto_habitual_respeta_la_categoria_elegida(): void
    {
        $plantilla = FinEgresoPlantilla::query()->create(['nombre' => 'Antena', 'monto' => 300000, 'es_sueldo' => false, 'orden' => 1, 'activa' => true]);

        $this->post(route('finanzas.egresos.store'), [
            'plantilla_id' => $plantilla->id,
            'cuenta_id' => $this->cuenta->id,
            'categoria_id' => $this->capex->id,
            'importe' => '300.000',
            'fecha' => now()->toDateString(),
        ])->assertRedirect(route('finanzas.egresos'));

        $egreso = FinEgreso::query()->sole();
        $this->assertSame($this->capex->id, (int) $egreso->categoria_id);
        $this->assertSame($plantilla->id, (int) $egreso->plantilla_id);
    }

    public function test_la_categoria_nueva_no_queda_si_el_egreso_falla(): void
    {
        FinCierre::query()->create([
            'cuenta_id' => $this->cuenta->id,
            'tipo' => 'diario',
            'periodo_desde' => now()->toDateString(),
            'periodo_hasta' => now()->toDateString(),
        ]);

        $this->post(route('finanzas.egresos.store'), [
            'descripcion' => 'Router de prueba',
            'cuenta_id' => $this->cuenta->id,
            'categoria_id' => $this->opex->id,
            'nueva_categoria' => 'Equipos de red',
            'nueva_clase' => 'capex',
            'importe' => '150.000',
            'fecha' => now()->toDateString(),
        ])->assertSessionHasErrors('form');

        $this->assertFalse(FinCategoria::query()->where('nombre', 'Equipos de red')->exists());
        $this->assertSame(0, FinEgreso::query()->count());
    }

    public function test_la_categoria_nueva_se_reusa_si_ya_existe(): void
    {
        foreach (['Router 1', 'Router 2'] as $descripcion) {
            $this->post(route('finanzas.egresos.store'), [
                'descripcion' => $descripcion,
                'cuenta_id' => $this->cuenta->id,
                'categoria_id' => $this->opex->id,
                'nueva_categoria' => 'Equipos de red',
                'nueva_clase' => 'capex',
                'importe' => '150.000',
                'fecha' => now()->toDateString(),
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(1, FinCategoria::query()->where('nombre', 'Equipos de red')->count());
        $this->assertSame(2, FinEgreso::query()->count());
    }

    public function test_la_ruta_de_anular_pide_motivo_y_anula(): void
    {
        $egreso = $this->egreso();

        $this->from(route('finanzas.egresos'))
            ->post(route('finanzas.egresos.anular', $egreso), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');
        $this->assertSame('contabilizado', $egreso->fresh()->estado);

        $this->from(route('finanzas.egresos'))
            ->post(route('finanzas.egresos.anular', $egreso), ['motivo' => 'Cuenta equivocada'])
            ->assertRedirect(route('finanzas.egresos'))
            ->assertSessionHas('success');
        $this->assertSame('anulado', $egreso->fresh()->estado);
    }

    public function test_la_ruta_de_anular_exige_permiso_de_edicion(): void
    {
        $routes = file_get_contents(base_path('routes/web.php')) ?: '';
        $bloqueEditar = substr($routes, strpos($routes, "permiso:finanzas.editar"));
        $bloqueEditar = substr($bloqueEditar, 0, strpos($bloqueEditar, '});'));

        $this->assertStringContainsString("->name('finanzas.egresos.anular')", $bloqueEditar);
    }
}
