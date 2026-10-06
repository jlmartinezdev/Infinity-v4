<?php

namespace Tests\Unit\Support;

use App\Support\FacturaElectronicaListaPeriodos;
use Carbon\Carbon;
use Tests\TestCase;

class FacturaElectronicaListaPeriodosTest extends TestCase
{
    public function test_extrae_periodo_del_marcador(): void
    {
        $ym = FacturaElectronicaListaPeriodos::extraerYm(
            'Período facturación: 2026-09 (septiembre 2026)',
            '2026-10-01'
        );

        $this->assertSame('2026-09', $ym);
    }

    public function test_sin_marcador_usa_fecha_de_emision(): void
    {
        $ym = FacturaElectronicaListaPeriodos::extraerYm(null, Carbon::parse('2026-08-15'));

        $this->assertSame('2026-08', $ym);
    }

    public function test_agrupa_meses_por_cliente_y_resume_la_lista(): void
    {
        $porCliente = FacturaElectronicaListaPeriodos::porCliente([
            ['id' => 20, 'cliente_id' => 1, 'observaciones' => 'Período facturación: 2026-09', 'fecha_emision' => '2026-09-30'],
            ['id' => 10, 'cliente_id' => 1, 'observaciones' => 'Período facturación: 2026-08', 'fecha_emision' => '2026-08-31'],
            ['id' => 21, 'cliente_id' => 2, 'observaciones' => 'Período facturación: 2026-09', 'fecha_emision' => '2026-09-30'],
            ['id' => 22, 'cliente_id' => 2, 'observaciones' => 'Período facturación: 2026-09', 'fecha_emision' => '2026-09-29'],
        ]);

        $this->assertSame('2026-09', $porCliente[1][0]['ym']);
        $this->assertSame(20, $porCliente[1][0]['factura_id']);
        $this->assertSame('2026-08', $porCliente[1][1]['ym']);
        $this->assertCount(1, $porCliente[2]);
        $this->assertSame(21, $porCliente[2][0]['factura_id']);

        $resumen = FacturaElectronicaListaPeriodos::resumenLista([1, 2, 3], $porCliente);

        $this->assertSame('2026-09', $resumen[0]['ym']);
        $this->assertSame(2, $resumen[0]['clientes']);
        $this->assertSame('2026-08', $resumen[1]['ym']);
        $this->assertSame(1, $resumen[1]['clientes']);
        $this->assertStringContainsString('septiembre', FacturaElectronicaListaPeriodos::textoResumen($resumen));
    }
}
