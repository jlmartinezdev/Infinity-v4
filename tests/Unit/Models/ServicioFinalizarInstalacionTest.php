<?php

namespace Tests\Unit\Models;

use App\Models\Servicio;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Tests\TestCase;

class ServicioFinalizarInstalacionTest extends TestCase
{
    private function servicio(array $attrs = []): Servicio
    {
        $servicio = new Servicio;
        $servicio->estado = Servicio::ESTADO_ACTIVO;
        $servicio->pedido_id = null;
        $servicio->fecha_instalacion = now()->toDateString();

        foreach ($attrs as $key => $value) {
            $servicio->{$key} = $value;
        }

        return $servicio;
    }

    public function test_servicio_adicional_del_mes_puede_finalizarse(): void
    {
        $hoy = Carbon::parse('2026-09-10');
        $servicio = $this->servicio(['fecha_instalacion' => '2026-09-03']);

        $this->assertTrue($servicio->esCandidatoFinalizarInstalacion($hoy));
    }

    public function test_sin_fecha_de_instalacion_puede_finalizarse(): void
    {
        $servicio = $this->servicio(['fecha_instalacion' => null]);

        $this->assertTrue($servicio->esCandidatoFinalizarInstalacion(Carbon::parse('2026-09-10')));
    }

    public function test_servicio_de_pedido_no_se_finaliza_desde_cliente(): void
    {
        $servicio = $this->servicio(['pedido_id' => 44]);

        $this->assertFalse($servicio->esCandidatoFinalizarInstalacion(Carbon::parse('2026-09-10')));
    }

    public function test_servicio_cancelado_no_puede_finalizarse(): void
    {
        $servicio = $this->servicio(['estado' => Servicio::ESTADO_CANCELADO]);

        $this->assertFalse($servicio->esCandidatoFinalizarInstalacion(Carbon::parse('2026-09-10')));
    }

    public function test_instalacion_de_otro_mes_no_muestra_el_boton(): void
    {
        $servicio = $this->servicio(['fecha_instalacion' => '2026-08-20']);

        $this->assertFalse($servicio->esCandidatoFinalizarInstalacion(Carbon::parse('2026-09-10')));
    }

    public function test_factura_de_instalacion_recien_desde_el_dia_7(): void
    {
        $this->assertFalse(FacturacionService::puedeEmitirFacturaPorInstalacion(Carbon::parse('2026-09-06')));
        $this->assertTrue(FacturacionService::puedeEmitirFacturaPorInstalacion(Carbon::parse('2026-09-07')));
    }
}
