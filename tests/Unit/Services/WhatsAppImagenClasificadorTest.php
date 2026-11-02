<?php

namespace Tests\Unit\Services;

use App\Services\WhatsApp\WhatsAppImagenClasificadorService;
use Tests\TestCase;

class WhatsAppImagenClasificadorTest extends TestCase
{
    public function test_ocr_de_mapa_whatsapp(): void
    {
        $ocr = 'Enviar ubicación. Ubicación en tiempo real. Enviar mi ubicación actual. Estación Número 800. Ganaderia y Hipodromo San Valentin.';
        $this->assertSame(
            WhatsAppImagenClasificadorService::TIPO_MAPA,
            WhatsAppImagenClasificadorService::clasificarDesdeTexto($ocr)
        );
    }

    public function test_ocr_de_comprobante_ueno(): void
    {
        $ocr = 'ueno bank. Comprobante de transferencia. Gs. 100.000. Transferencia exitosa.';
        $this->assertSame(
            WhatsAppImagenClasificadorService::TIPO_COMPROBANTE,
            WhatsAppImagenClasificadorService::clasificarDesdeTexto($ocr)
        );
    }

    public function test_parsea_json_roto_con_tipo(): void
    {
        $parsed = WhatsAppImagenClasificadorService::parsearRespuestaVision(
            "{\n  \"tipo\": \"mapa\",\n  \"texto\": \"Enviar ubicación\nUbicación actual\"\n}"
        );
        $this->assertNotNull($parsed);
        $this->assertSame('mapa', $parsed['tipo']);
    }

    public function test_parsea_json_de_vision(): void
    {
        $parsed = WhatsAppImagenClasificadorService::parsearRespuestaVision(
            "```json\n{\"tipo\":\"mapa\",\"texto\":\"Enviar ubicación\"}\n```"
        );
        $this->assertNotNull($parsed);
        $this->assertSame('mapa', $parsed['tipo']);
        $this->assertStringContainsString('ubicación', mb_strtolower($parsed['ocr']));
    }

    public function test_mapa_gana_si_vision_dice_mapa(): void
    {
        $this->assertSame(
            'mapa',
            WhatsAppImagenClasificadorService::resolverTipo('mapa', 'otro')
        );
    }

    public function test_ocr_de_wifi_sin_internet_iphone(): void
    {
        $ocr = 'Flia Maidana-5G. Sin conexión a Internet. Si esta es tu red Wi-Fi, intenta reiniciar el módem. Poca seguridad. WPA/WPA2 (TKIP). Olvidar esta red. Conexión automática.';
        $this->assertSame(
            WhatsAppImagenClasificadorService::TIPO_WIFI_FALLA,
            WhatsAppImagenClasificadorService::clasificarDesdeTexto($ocr)
        );
        $this->assertSame(
            WhatsAppImagenClasificadorService::TIPO_WIFI_FALLA,
            WhatsAppImagenClasificadorService::resolverTipo('otro', 'wifi_falla')
        );
        $sintoma = WhatsAppImagenClasificadorService::sintomaParaSoporte('wifi_falla', $ocr);
        $this->assertNotNull($sintoma);
        $this->assertStringContainsString('sin conexión a Internet', $sintoma);
    }

    public function test_parsea_wifi_falla_en_json(): void
    {
        $parsed = WhatsAppImagenClasificadorService::parsearRespuestaVision(
            '{"tipo":"wifi_falla","texto":"Sin conexión a Internet. Olvidar esta red."}'
        );
        $this->assertNotNull($parsed);
        $this->assertSame('wifi_falla', $parsed['tipo']);
    }
}
