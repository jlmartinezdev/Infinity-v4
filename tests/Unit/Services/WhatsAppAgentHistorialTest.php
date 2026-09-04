<?php

namespace Tests\Unit\Services;

use App\Models\WhatsappMensaje;
use App\Services\WhatsApp\WhatsAppAgentService;
use Tests\TestCase;

class WhatsAppAgentHistorialTest extends TestCase
{
    public function test_serializa_entrada_y_salida(): void
    {
        $entrada = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'tipo' => 'text',
            'cuerpo' => 'Hola, se me cortó internet',
        ]);
        $entrada->created_at = now()->setTime(14, 5);

        $salida = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_SALIDA,
            'tipo' => 'text',
            'cuerpo' => 'Reiniciá el módem por favor',
            'contexto_tipo' => 'manual_panel',
        ]);
        $salida->created_at = now()->setTime(14, 7);

        $itemIn = WhatsAppAgentService::serializarItemHistorial($entrada);
        $itemOut = WhatsAppAgentService::serializarItemHistorial($salida);

        $this->assertSame('cliente', $itemIn['rol']);
        $this->assertSame('Hola, se me cortó internet', $itemIn['texto']);
        $this->assertSame('asesor', $itemOut['rol']);
        $this->assertSame('manual_panel', $itemOut['origen']);
    }

    public function test_media_sin_cuerpo_usa_etiqueta(): void
    {
        $img = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'tipo' => 'image',
            'cuerpo' => '',
        ]);

        $item = WhatsAppAgentService::serializarItemHistorial($img);
        $this->assertSame('[captura]', $item['texto']);
    }

    public function test_texto_vacio_se_omite(): void
    {
        $vacio = new WhatsappMensaje([
            'direccion' => WhatsappMensaje::DIRECCION_ENTRADA,
            'tipo' => 'text',
            'cuerpo' => '   ',
        ]);

        $this->assertNull(WhatsAppAgentService::serializarItemHistorial($vacio));
    }

    public function test_historial_como_texto_ordena_roles(): void
    {
        $texto = WhatsAppAgentService::historialComoTexto([
            ['rol' => 'cliente', 'texto' => 'cuánto sale el de 20?', 'at' => '2026-08-31T14:00:00-03:00'],
            ['rol' => 'asesor', 'texto' => 'Gs 150.000 el Estándar', 'at' => '2026-08-31T14:01:00-03:00'],
        ]);

        $this->assertStringContainsString('Cliente: cuánto sale el de 20?', $texto);
        $this->assertStringContainsString('Asesor: Gs 150.000 el Estándar', $texto);
        $this->assertTrue(strpos($texto, 'Cliente:') < strpos($texto, 'Asesor:'));
    }
}
