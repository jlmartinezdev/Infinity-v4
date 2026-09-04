<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\TipoTecnologia;
use App\Services\WhatsApp\WhatsAppAgentService;
use Tests\TestCase;

class WhatsAppAgentPlanesTest extends TestCase
{
    public function test_gpon_es_fibra_y_wireless_es_antena(): void
    {
        $fibra = $this->plan(2, 'PLAN BASICO FTTH 100 Mbps', '100Mbps', 100000, 'GPON');
        $antena = $this->plan(1, 'PLAN BASICO 10 Mbps', '10 Mbps', 100000, 'WIRELESS');

        $this->assertSame('fibra', WhatsAppAgentService::serializarPlanWa($fibra)['grupo']);
        $this->assertSame('antena', WhatsAppAgentService::serializarPlanWa($antena)['grupo']);
        $this->assertSame('Gs. 100.000', WhatsAppAgentService::serializarPlanWa($fibra)['precio_texto']);
    }

    public function test_planes_como_texto_separa_fibra_y_antena(): void
    {
        $texto = WhatsAppAgentService::planesComoTexto(
            [['nombre' => 'PLAN BASICO FTTH 100 Mbps', 'velocidad' => '100Mbps', 'precio_texto' => 'Gs. 100.000']],
            [['nombre' => 'PLAN ESTANDAR 20 Mbps', 'velocidad' => '20 Mbps', 'precio_texto' => 'Gs. 150.000']],
        );

        $this->assertStringContainsString('Fibra (GPON):', $texto);
        $this->assertStringContainsString('Antena (WIRELESS):', $texto);
        $this->assertStringContainsString('PLAN BASICO FTTH 100 Mbps', $texto);
        $this->assertStringContainsString('PLAN ESTANDAR 20 Mbps', $texto);
        $this->assertStringContainsString('SOLO fibra', $texto);
        $this->assertStringContainsString('SOLO antena', $texto);
        $this->assertStringContainsString('una línea por plan', $texto);
        $this->assertTrue(strpos($texto, 'Fibra') < strpos($texto, 'Antena'));
    }

    private function plan(int $id, string $nombre, string $velocidad, float $precio, string $tech): Plan
    {
        $plan = new Plan;
        $plan->plan_id = $id;
        $plan->nombre = $nombre;
        $plan->velocidad = $velocidad;
        $plan->precio = $precio;
        $tipo = new TipoTecnologia;
        $tipo->descripcion = $tech;
        $plan->setRelation('tipoTecnologia', $tipo);

        return $plan;
    }
}
