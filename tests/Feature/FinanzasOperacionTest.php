<?php

namespace Tests\Feature;

use App\Models\FinCategoria;
use App\Models\FinCierre;
use App\Models\FinConciliacion;
use App\Models\FinCuenta;
use App\Models\FinMovimiento;
use App\Services\Finanzas\FinanzasLibroService;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class FinanzasOperacionTest extends TestCase
{
    use Concerns\TablasFinanzas;

    private FinCuenta $banco;

    private FinCuenta $efectivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTablasFinanzas();
        $this->banco = FinCuenta::query()->create(['codigo' => 'banco', 'nombre' => 'Banco', 'tipo' => 'banco', 'activa' => true, 'orden' => 1]);
        $this->efectivo = FinCuenta::query()->create(['codigo' => 'efectivo', 'nombre' => 'Efectivo', 'tipo' => 'efectivo', 'activa' => true, 'orden' => 2]);
        foreach ([['Gasto operativo', 'opex'], ['Retiro', 'retiro'], ['Comisión Bancard', 'comision'], ['Ajuste de conciliación', 'ajuste']] as [$nombre, $clase]) {
            FinCategoria::query()->create(['nombre' => $nombre, 'clase' => $clase, 'activa' => true]);
        }
        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckPermiso::class]);
    }

    private function libro(): FinanzasLibroService
    {
        return app(FinanzasLibroService::class);
    }

    private function ingreso(float $importe, Carbon $fecha, int $referencia): FinMovimiento
    {
        return $this->libro()->registrar([
            'cuenta_id' => $this->banco->id,
            'tipo' => 'ingreso',
            'signo' => 1,
            'fecha' => $fecha,
            'importe' => $importe,
            'concepto' => 'Recibo '.$referencia,
            'referencia_tipo' => 'cobro',
            'referencia_id' => $referencia,
        ]);
    }

    public function test_las_sumas_del_dashboard_no_cuentan_lo_revertido_y_separan_retiros(): void
    {
        $hoy = now()->startOfDay();
        $this->ingreso(500000, $hoy, 1);
        $anulado = $this->ingreso(200000, $hoy, 2);
        $this->libro()->revertir($anulado, 'Recibo anulado', null);

        $opex = (int) FinCategoria::query()->where('clase', 'opex')->value('id');
        $gasto = $this->libro()->registrarEgreso($this->banco->id, $opex, $hoy, 100000, null, null, null, null);
        $gastoAnulado = $this->libro()->registrarEgreso($this->banco->id, $opex, $hoy, 40000, null, null, null, null);
        $this->libro()->anularEgreso($gastoAnulado, 'Duplicado', null);
        $this->libro()->registrarRetiro('Socio', $hoy, 70000, $this->banco->id, 'Adelanto', null, null, null);

        $desde = $hoy->copy()->startOfMonth();
        $hasta = $hoy->copy()->endOfMonth();
        $this->assertSame(500000.0, $this->libro()->sumaVigente('ingreso', $desde, $hasta));
        $this->assertSame(100000.0, $this->libro()->sumaVigente('egreso', $desde, $hasta));
        $this->assertSame(70000.0, $this->libro()->sumaVigente('retiro', $desde, $hasta));
        $this->assertNotNull($gasto->id);
    }

    public function test_no_se_cierra_un_periodo_que_no_termino(): void
    {
        try {
            $this->libro()->cerrar('mensual', now(), [$this->banco->id => 0, $this->efectivo->id => 0], null);
            $this->fail('Debió rechazar el mes en curso.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('todavía no terminó', $e->getMessage());
        }

        try {
            $this->libro()->cerrar('diario', now(), [$this->banco->id => 0, $this->efectivo->id => 0], null);
            $this->fail('Debió rechazar el día de hoy.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString(now()->addDay()->format('d/m/Y'), $e->getMessage());
        }

        $this->assertSame(0, FinCierre::query()->count());
    }

    public function test_un_periodo_terminado_se_cierra(): void
    {
        $this->libro()->cerrar('diario', now()->subDay(), [$this->banco->id => 0, $this->efectivo->id => 0], null);

        $this->assertSame(2, FinCierre::query()->count());
    }

    public function test_la_transferencia_a_la_misma_cuenta_avisa_en_espanol_y_conserva_lo_escrito(): void
    {
        $this->from(route('finanzas.transferencias'))
            ->post(route('finanzas.transferencias.store'), [
                'cuenta_origen_id' => $this->banco->id,
                'cuenta_destino_id' => $this->banco->id,
                'importe' => '150.000',
                'fecha' => now()->toDateString(),
                'numero_referencia' => 'OP-77',
            ])
            ->assertRedirect(route('finanzas.transferencias'))
            ->assertSessionHasErrors(['cuenta_destino_id' => 'La cuenta de destino tiene que ser distinta de la de origen.'])
            ->assertSessionHasInput('numero_referencia', 'OP-77');
    }

    public function test_un_dato_faltante_avisa_en_espanol(): void
    {
        $this->from(route('finanzas.retiros'))
            ->post(route('finanzas.retiros.store'), [
                'fecha' => now()->toDateString(),
                'importe' => '50.000',
                'cuenta_id' => $this->banco->id,
                'motivo' => 'Adelanto',
            ])
            ->assertSessionHasErrors(['persona' => 'Completá la persona.']);
    }

    public function test_el_ajuste_de_conciliacion_exige_observaciones(): void
    {
        $datos = [
            'cuenta_id' => $this->banco->id,
            'fecha' => now()->toDateString(),
            'saldo_real' => '10.000',
            'ajustar' => '1',
        ];

        $this->from(route('finanzas.conciliacion'))
            ->post(route('finanzas.conciliacion.store'), $datos)
            ->assertSessionHasErrors(['observaciones' => 'Explicá en observaciones por qué se ajusta la diferencia.']);
        $this->assertSame(0, FinConciliacion::query()->count());

        $this->from(route('finanzas.conciliacion'))
            ->post(route('finanzas.conciliacion.store'), $datos + ['observaciones' => 'Depósito no registrado'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull(FinConciliacion::query()->sole()->ajuste_movimiento_id);
        $this->assertSame(10000.0, $this->libro()->saldoActual($this->banco->id));
    }

    public function test_conciliar_sin_ajuste_no_pide_observaciones(): void
    {
        $this->from(route('finanzas.conciliacion'))
            ->post(route('finanzas.conciliacion.store'), [
                'cuenta_id' => $this->banco->id,
                'fecha' => now()->toDateString(),
                'saldo_real' => '10.000',
            ])
            ->assertSessionHasNoErrors();
        $this->assertNull(FinConciliacion::query()->sole()->ajuste_movimiento_id);
    }
}
