<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\Ubnt\McaStatusParser;
use App\Services\WhatsApp\Soporte\EvaluadorDiagnostico;
use App\Services\WhatsApp\Soporte\FraseCliente;
use App\Services\WhatsApp\Soporte\SoporteTexto;
use App\Services\WhatsApp\Soporte\TratoCliente;
use PHPUnit\Framework\TestCase;

class SoporteDiagnosticoTest extends TestCase
{
    public function test_suspendido_responde_cobranza_sin_escalar(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'suspendido',
            'saldo' => 150000,
            'parece_falla' => true,
            'texto' => 'no anda internet',
            'medicion' => ['pudo_medir' => true, 'sesion_online' => false],
        ]);

        $this->assertSame('cobranza', $decision['tipo']);
        $this->assertFalse($decision['escalate']);
        $this->assertStringContainsString('150.000', $decision['reply']);
        $this->assertStringNotContainsString('dBm', $decision['reply']);
    }

    public function test_puerto_lento_pide_revisar_el_cable_sin_decir_la_velocidad(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'texto' => 'no anda internet',
            'medicion' => [
                'pudo_medir' => true,
                'sesion_online' => true,
                'ping_general_ok' => true,
                'lan_speed' => 100,
                'lan_conectado' => true,
            ],
        ]);

        $this->assertSame('accion', $decision['tipo']);
        $this->assertSame(FraseCliente::CABLE, $decision['reply']);
        $this->assertStringNotContainsString('100', $decision['reply']);
    }

    public function test_senal_debil_del_celular_pide_acercarse_sin_el_numero(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'paso' => 'equipo',
            'texto' => '1',
            'medicion' => [
                'pudo_medir' => true,
                'equipos' => [
                    ['nombre' => 'Samsung', 'rssi' => -82],
                ],
            ],
        ]);

        $this->assertSame(FraseCliente::ACERCATE, $decision['reply']);
        $this->assertStringNotContainsString('-82', $decision['reply']);
        $this->assertFalse($decision['escalate']);
    }

    public function test_pagina_caida_con_internet_sano_no_escala(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'paso' => 'sitio',
            'texto' => 'facebook no abre',
            'medicion' => [
                'ping_general_ok' => true,
                'sitio_ok' => false,
            ],
        ]);

        $this->assertSame('resuelto', $decision['tipo']);
        $this->assertSame(FraseCliente::SITIO_MAL, $decision['reply']);
        $this->assertFalse($decision['escalate']);
    }

    public function test_filtro_descarta_una_medicion(): void
    {
        $this->assertSame(
            FraseCliente::ESCALADO,
            FraseCliente::filtrar('Tu señal está en -70 dBm', FraseCliente::ESCALADO)
        );
    }

    public function test_parser_lee_velocidad_del_puerto(): void
    {
        $datos = (new McaStatusParser)->parse("lanSpeed=100Mbps\nlanPlugged=1\n");

        $this->assertSame(100, $datos['lan_speed']);
        $this->assertTrue($datos['lan_conectado']);
    }

    public function test_trato_por_nombre_y_escalado_formal(): void
    {
        $this->assertSame('Señor', TratoCliente::resolver('Rufino', 'Leiva Avalos')['trato']);
        $this->assertSame('Señor Leiva', TratoCliente::vocativo('Rufino', 'Leiva Avalos'));
        $this->assertSame('Señora', TratoCliente::resolver('María', 'López')['trato']);
        $this->assertSame('Señora López', TratoCliente::vocativo('María', 'López'));
        $this->assertNull(TratoCliente::generoDe('Alex'));

        $texto = FraseCliente::escalado('7156404', 'María', 'López');
        $this->assertStringContainsString('Señora López, recibimos su aviso.', $texto);
        $this->assertStringContainsString('monitoreando su servicio', $texto);
        $this->assertStringContainsString('7.156.404', $texto);
        $this->assertStringNotContainsString('971 714 322', $texto);
        $this->assertStringNotContainsString('agradecemos', mb_strtolower($texto));
        $this->assertStringNotContainsString('Lamentamos', $texto);

        $cierre = FraseCliente::resuelto('Rufino', 'Leiva');
        $this->assertStringContainsString('Señor Leiva', $cierre);
        $this->assertStringContainsString(FraseCliente::CIERRE, $cierre);
    }

    public function test_clasifica_cambio_de_wifi_y_un_sitio(): void
    {
        $this->assertTrue(SoporteTexto::pareceCambioWifi('cambia la clave del wifi'));
        $this->assertSame('facebook.com', SoporteTexto::sitio('no me abre facebook'));
        $this->assertTrue(SoporteTexto::pareceFalla('no anda internet'));
    }

    public function test_saca_cedula_y_telefono_del_texto(): void
    {
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('soy 4765661 Por que no anda la señal de wifi'));
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('nr 4765661'));
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('nro 4765661'));
        $this->assertSame('595986504437', SoporteTexto::telefonoEnTexto('595 986 504437'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('Rufino Leiva Avalos'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('soy Rufino Leiva Avalos no anda el wifi'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('Rufino Leiva Avalos no anda el wifi'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('595 986 504437'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('nr 4765661'));
        $this->assertSame(
            FraseCliente::ESCALADO,
            FraseCliente::filtrar(
                'Un técnico revisará tu línea. Comunicate al +595 971 714 322.',
                FraseCliente::ESCALADO
            )
        );
        $this->assertSame(
            FraseCliente::ESCALADO,
            FraseCliente::filtrar('María, agradecemos tu aviso.', FraseCliente::ESCALADO)
        );
    }
}
