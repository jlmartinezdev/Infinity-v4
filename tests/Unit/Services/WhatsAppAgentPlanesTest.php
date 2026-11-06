<?php

namespace Tests\Unit\Services;

use App\Models\Plan;
use App\Models\TipoTecnologia;
use App\Services\WhatsApp\WhatsAppAgentService;
use PHPUnit\Framework\TestCase;

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

    public function test_planes_para_prompt_tiene_instrucciones(): void
    {
        $texto = WhatsAppAgentService::planesParaPrompt(
            [['nombre' => 'PLAN BASICO FTTH 100 Mbps', 'velocidad' => '100Mbps', 'precio_texto' => 'Gs. 100.000', 'prioridad' => 3]],
            [['nombre' => 'PLAN ESTANDAR 20 Mbps', 'velocidad' => '20 Mbps', 'precio_texto' => 'Gs. 150.000', 'prioridad' => 2]],
        );

        $this->assertStringContainsString('Fibra (GPON):', $texto);
        $this->assertStringContainsString('Antena (WIRELESS):', $texto);
        $this->assertStringContainsString('SOLO fibra', $texto);
        $this->assertStringContainsString('SOLO antena', $texto);
        $this->assertStringContainsString('una línea por plan', $texto);
        $this->assertStringContainsString('prioridad 3', $texto);
        $this->assertTrue(strpos($texto, 'Fibra') < strpos($texto, 'Antena'));
    }

    public function test_planes_para_cliente_sin_instrucciones_ni_prioridad(): void
    {
        $texto = WhatsAppAgentService::planesParaCliente(
            [['nombre' => 'PLAN BASICO FTTH 100 Mbps', 'velocidad' => '100Mbps', 'precio_texto' => 'Gs. 100.000', 'prioridad' => 3]],
            [['nombre' => 'PLAN ESTANDAR 20 Mbps', 'velocidad' => '20 Mbps', 'precio_texto' => 'Gs. 150.000', 'prioridad' => 2]],
        );

        $this->assertStringContainsString('estos son los planes mensuales', mb_strtolower($texto));
        $this->assertStringNotContainsString('iva incluido', mb_strtolower($texto));
        $this->assertStringNotContainsString('vigentes', mb_strtolower($texto));
        $this->assertStringContainsString('PLAN BASICO FTTH 100 Mbps', $texto);
        $this->assertStringContainsString('Gs. 100.000', $texto);
        $this->assertStringNotContainsString('Venta suave', $texto);
        $this->assertStringNotContainsString('Salida WhatsApp', $texto);
        $this->assertStringNotContainsString('Cotice SOLO', $texto);
        $this->assertStringNotContainsString('prioridad', mb_strtolower($texto));
    }

    public function test_plan_dedicado_es_a_medida(): void
    {
        $this->assertTrue(WhatsAppAgentService::planEsAMedida('Dedicado 150 Mbps'));
        $this->assertTrue(WhatsAppAgentService::planEsAMedida('PLAN DEDICADO', '150 Mbps'));
        $this->assertFalse(WhatsAppAgentService::planEsAMedida('PLAN PREMIUM 30 Mbps'));
    }

    public function test_planes_para_prompt_sin_iva_incluido(): void
    {
        $texto = WhatsAppAgentService::planesParaPrompt(
            [['nombre' => 'PLAN BASICO FTTH 100 Mbps', 'velocidad' => '100Mbps', 'precio_texto' => 'Gs. 100.000']],
            [],
        );
        $this->assertStringNotContainsString('IVA incluido', $texto);
        $this->assertStringContainsString('No mencione “IVA incluido”', $texto);
        $this->assertStringContainsString('Embudo Premium', $texto);
        $this->assertStringContainsString('recomendación Premium', $texto);
    }

    public function test_filtro_catalogo_fibra_o_antena(): void
    {
        $this->assertSame('fibra', WhatsAppAgentService::filtroCatalogoDesdeTexto('qué planes de fibra tienen'));
        $this->assertSame('antena', WhatsAppAgentService::filtroCatalogoDesdeTexto('cuánto sale el de 20 mb antena'));
        $this->assertSame('ambos', WhatsAppAgentService::filtroCatalogoDesdeTexto('qué planes hay'));
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
