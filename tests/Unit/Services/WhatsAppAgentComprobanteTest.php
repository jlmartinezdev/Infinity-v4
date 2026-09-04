<?php

namespace Tests\Unit\Services;

use App\Services\WhatsApp\WhatsAppAgentService;
use Carbon\Carbon;
use Tests\TestCase;

class WhatsAppAgentComprobanteTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_horario_laboral_lun_vie_9_a_18(): void
    {
        config([
            'app.timezone' => 'America/Argentina/Buenos_Aires',
            'whatsapp.agent.horario_desde' => '09:00',
            'whatsapp.agent.horario_hasta' => '18:00',
        ]);

        $this->assertTrue(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-31 09:00:00', 'America/Argentina/Buenos_Aires')));
        $this->assertTrue(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-31 14:05:00', 'America/Argentina/Buenos_Aires')));
        $this->assertTrue(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-31 18:00:00', 'America/Argentina/Buenos_Aires')));
        $this->assertFalse(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-31 08:59:00', 'America/Argentina/Buenos_Aires')));
        $this->assertFalse(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-31 18:01:00', 'America/Argentina/Buenos_Aires')));
        $this->assertFalse(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-29 14:00:00', 'America/Argentina/Buenos_Aires')));
        $this->assertFalse(WhatsAppAgentService::horarioLaboral(Carbon::parse('2026-08-30 10:00:00', 'America/Argentina/Buenos_Aires')));
    }

    public function test_texto_parece_pago(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoParecePago('Mi pago del mes'));
        $this->assertTrue(WhatsAppAgentService::textoParecePago('Comprobante de transferencia'));
        $this->assertTrue(WhatsAppAgentService::textoParecePago('Transferí por ueno'));
        $this->assertFalse(WhatsAppAgentService::textoParecePago('Se me apagó el router'));
        $this->assertFalse(WhatsAppAgentService::textoParecePago('Hola'));
    }

    public function test_bajar_wifi_es_instalacion_no_baja(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoPareceInstalacion('Quiero mandar bajar wifi'));
        $this->assertTrue(WhatsAppAgentService::textoPareceInstalacion('bajar wifi'));
        $this->assertTrue(WhatsAppAgentService::textoPareceInstalacion('Quiero que me instalen internet'));
        $this->assertFalse(WhatsAppAgentService::textoPareceBajaServicio('Quiero mandar bajar wifi'));
        $this->assertTrue(WhatsAppAgentService::textoPareceBajaServicio('Quiero darme de baja'));
        $this->assertTrue(WhatsAppAgentService::textoPareceBajaServicio('Quiero cancelar el servicio'));
        $this->assertFalse(WhatsAppAgentService::textoPareceInstalacion('Quiero darme de baja'));
    }

    public function test_detecta_captura_mas_texto_de_pago(): void
    {
        $historial = [
            ['rol' => 'cliente', 'tipo' => 'image', 'texto' => '[captura]'],
        ];

        $this->assertTrue(WhatsAppAgentService::detectarComprobante('text', 'Mi pago del mes', $historial));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('text', 'Mi pago del mes', []));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('text', 'Hola', $historial));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('image', '', []));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('image', 'Imagen', []));
        $this->assertTrue(WhatsAppAgentService::detectarComprobante('image', 'Mi pago del mes', []));
        $this->assertTrue(WhatsAppAgentService::detectarComprobante('image', '', [], 'comprobante'));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('image', 'Mi pago del mes', [], 'mapa'));
    }

    public function test_detecta_captura_de_mapa(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoPareceUbicacion('Camino asia malvinas'));
        $this->assertTrue(WhatsAppAgentService::detectarUbicacion('image', 'Imagen', [
            ['rol' => 'cliente', 'tipo' => 'text', 'texto' => 'Camino asia malvinas'],
        ]));
        $this->assertTrue(WhatsAppAgentService::detectarUbicacion('image', '', [], 'mapa'));
        $this->assertFalse(WhatsAppAgentService::detectarUbicacion('image', '', [], 'comprobante'));
        $this->assertFalse(WhatsAppAgentService::detectarComprobante('image', 'Imagen', [
            ['rol' => 'cliente', 'tipo' => 'text', 'texto' => 'Camino asia malvinas'],
        ]));
        $this->assertTrue(WhatsAppAgentService::textoPareceLinkMapa('Tres de Mayo https://share.google/lbSE4qK4z27koOCwX'));
        $this->assertTrue(WhatsAppAgentService::detectarUbicacion('text', 'Tres de Mayo https://share.google/lbSE4qK4z27koOCwX', []));
        $this->assertTrue(WhatsAppAgentService::textoPareceCedula('75616117'));
        $this->assertFalse(WhatsAppAgentService::textoPareceCedula('595981234567'));
        $this->assertFalse(WhatsAppAgentService::textoPareceCedula('Hola'));
    }

    public function test_wifi_suelto_no_es_corte(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoPareceWifi('wifi'));
        $this->assertTrue(WhatsAppAgentService::textoPareceWifi('Quiero wifi'));
        $this->assertFalse(WhatsAppAgentService::textoPareceWifi('se me cortó el wifi'));
        $this->assertFalse(WhatsAppAgentService::textoPareceWifi('el wifi está lento'));
        $this->assertFalse(WhatsAppAgentService::textoPareceWifi('se me cae internet'));
    }

    public function test_nombre_corto_saludo(): void
    {
        $this->assertSame('Ana', WhatsAppAgentService::nombreCortoSaludo('Ana Pérez'));
        $this->assertSame('Juan', WhatsAppAgentService::nombreCortoSaludo('  Juan  Carlos  '));
        $this->assertSame('', WhatsAppAgentService::nombreCortoSaludo(''));
        $this->assertSame('', WhatsAppAgentService::nombreCortoSaludo(null));
    }

    public function test_telefono_sandbox_siempre_595000(): void
    {
        $this->assertSame('595000000001', WhatsAppAgentService::telefonoSandbox(''));
        $this->assertSame('595000000001', WhatsAppAgentService::telefonoSandbox('595000000001'));
        $this->assertSame('595000123456', WhatsAppAgentService::telefonoSandbox('98123456'));
        $this->assertTrue(WhatsAppAgentService::esTelefonoTest('595000000001'));
        $this->assertFalse(WhatsAppAgentService::esTelefonoTest('595981234567'));
    }

    public function test_cobertura_aprobada_no_confunde_con_verificar(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoPareceCoberturaAprobada('Ubicación aprobada'));
        $this->assertTrue(WhatsAppAgentService::textoPareceCoberturaAprobada('cobertura aprobada'));
        $this->assertTrue(WhatsAppAgentService::textoPareceCoberturaAprobada('Hay cobertura en tu zona. Fibra 100 Mbps — Gs. 100.000.'));
        $this->assertTrue(WhatsAppAgentService::textoPareceCoberturaAprobada('Ya hay cobertura'));
        $this->assertFalse(WhatsAppAgentService::textoPareceCoberturaAprobada('Un técnico verifica si hay cobertura en tu zona y te confirmamos.'));
        $this->assertFalse(WhatsAppAgentService::textoPareceCoberturaAprobada('Hola, quiero wifi'));
        $this->assertTrue(WhatsAppAgentService::hiloTieneCoberturaAprobada([
            ['texto' => 'Hay cobertura en tu zona. ¿Cuál te instalamos?'],
        ]));
        $this->assertFalse(WhatsAppAgentService::hiloTieneCoberturaAprobada([
            ['texto' => 'Un técnico verifica si hay cobertura en tu zona y te confirmamos.'],
        ]));
    }

    public function test_plantillas_canned_de_ventas(): void
    {
        $this->assertTrue(WhatsAppAgentService::textoPareceBeneficiosPlanes('Qué beneficios tiene el estándar y el premium?'));
        $this->assertTrue(WhatsAppAgentService::textoPareceBeneficiosPlanes('aparte de la velocidad que más tiene?'));
        $this->assertFalse(WhatsAppAgentService::textoPareceBeneficiosPlanes('Cuánto sale el de 20 megas?'));

        $this->assertTrue(WhatsAppAgentService::textoPareceDatosTransferencia('A qué cuenta transfiero?'));
        $this->assertTrue(WhatsAppAgentService::textoPareceDatosTransferencia('datos para transferencia'));
        $this->assertFalse(WhatsAppAgentService::textoPareceDatosTransferencia('Ya pagué, les mando el comprobante'));

        $this->assertTrue(WhatsAppAgentService::textoPareceCondicionesServicio('Cuáles son las condiciones del servicio?'));
        $this->assertTrue(WhatsAppAgentService::textoPareceCondicionesServicio('los equipos son en comodato?'));
        $this->assertFalse(WhatsAppAgentService::textoPareceCondicionesServicio('Hola, quiero wifi'));

        $canned = WhatsAppAgentService::textosCanned();
        $this->assertStringContainsString('ueno bank', $canned['transferencia']);
        $this->assertStringContainsString('619556130', $canned['transferencia']);
        $this->assertStringContainsString('trafico prioritario', $canned['beneficios']);
        $this->assertStringContainsString('CONDICIONES DEL SERVICIO', $canned['condiciones']);
        $this->assertStringContainsString('comodato', $canned['condiciones']);
    }
}
