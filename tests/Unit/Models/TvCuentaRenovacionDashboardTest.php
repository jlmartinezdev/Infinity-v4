<?php

namespace Tests\Unit\Models;

use App\Models\TvCuenta;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TvCuentaRenovacionDashboardTest extends TestCase
{
    public function test_un_dia_antes_entre_semana_y_dos_el_sabado(): void
    {
        $cuenta = new TvCuenta;
        $cuenta->vencimiento_pago = Carbon::parse('2026-11-23');

        $viernes = Carbon::parse('2026-11-20')->startOfDay();
        $sabado = Carbon::parse('2026-11-21')->startOfDay();
        $domingo = Carbon::parse('2026-11-22')->startOfDay();

        $this->assertSame(1, TvCuenta::diasAnticipoRenovacionDashboard($viernes));
        $this->assertSame(2, TvCuenta::diasAnticipoRenovacionDashboard($sabado));
        $this->assertFalse($cuenta->requiereRenovacionEnDashboard($viernes));
        $this->assertTrue($cuenta->requiereRenovacionEnDashboard($sabado));
        $this->assertTrue($cuenta->requiereRenovacionEnDashboard($domingo));
    }

    public function test_muestra_vencidas_y_el_dia_del_vencimiento(): void
    {
        $cuenta = new TvCuenta;
        $cuenta->vencimiento_pago = Carbon::parse('2026-11-23');
        $lunes = Carbon::parse('2026-11-23')->startOfDay();
        $martes = Carbon::parse('2026-11-24')->startOfDay();

        $this->assertTrue($cuenta->requiereRenovacionEnDashboard($lunes));
        $this->assertTrue($cuenta->requiereRenovacionEnDashboard($martes));
    }
}
