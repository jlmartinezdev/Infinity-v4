<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\PlantillaPedidoPendiente;
use Tests\TestCase;

class PlantillaPedidoPendienteTest extends TestCase
{
    public function test_el_cuerpo_retoma_el_pedido_sin_vender_ni_tutear(): void
    {
        $cuerpo = PlantillaPedidoPendiente::cuerpo();

        $this->assertStringContainsString('{{1}}', $cuerpo);
        $this->assertStringContainsString('pedido de instalación sigue pendiente', $cuerpo);
        $this->assertStringContainsString('retomar la conversación', $cuerpo);
        $this->assertStringNotContainsString('Premium', $cuerpo);
        $this->assertDoesNotMatchRegularExpression('/\b(podés|tenés|avisanos|puedes|tienes)\b/u', $cuerpo);
        $this->assertDoesNotMatchRegularExpression('/\d{5,}/', $cuerpo);

        $def = PlantillaPedidoPendiente::definicion();
        $this->assertSame('pedido_pendiente_retomar', $def['name']);
        $this->assertSame('UTILITY', $def['category']);
        $this->assertSame('es', $def['language']);
        $this->assertSame('Sí, retomemos', $def['components'][1]['buttons'][0]['text']);
    }
}
