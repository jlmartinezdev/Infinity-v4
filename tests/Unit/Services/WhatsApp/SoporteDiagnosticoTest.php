<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\Ubnt\McaStatusParser;
use App\Services\WhatsApp\Soporte\EvaluadorDiagnostico;
use App\Services\WhatsApp\Soporte\FraseCliente;
use App\Services\WhatsApp\Soporte\SoporteTexto;
use App\Services\WhatsApp\Soporte\TratoCliente;
use App\Services\WhatsApp\Soporte\ValidadorHechos;
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

    public function test_enlace_sano_informa_analisis_antes_de_preguntar(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'texto' => 'no anda internet',
            'medicion' => [
                'pudo_medir' => true,
                'sesion_online' => true,
                'ping_general_ok' => true,
                'ping_cpe_ok' => true,
                'causas' => [],
            ],
        ]);

        $this->assertSame('pregunta', $decision['tipo']);
        $this->assertSame(FraseCliente::ENLACE_OK_PREGUNTA, $decision['reply']);
        $this->assertStringNotContainsString('dBm', $decision['reply']);
    }

    public function test_cpe_sin_respuesta_escala_con_frase_grounded(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'texto' => 'sin internet',
            'medicion' => [
                'pudo_medir' => true,
                'sesion_online' => true,
                'ping_general_ok' => true,
                'ping_cpe_ok' => false,
                'causas' => ['cpe_sin_respuesta'],
            ],
        ]);

        $this->assertSame('escalado', $decision['tipo']);
        $this->assertSame('cpe', $decision['motivo']);
        $this->assertSame(FraseCliente::CPE_CAIDO, $decision['reply']);
    }

    public function test_optica_mala_escala_sin_decir_dbm(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'texto' => 'no anda',
            'medicion' => [
                'pudo_medir' => true,
                'optica_ok' => false,
                'optica_rx_dbm' => -31.5,
                'causas' => ['optica_mala'],
            ],
        ]);

        $this->assertSame('escalado', $decision['tipo']);
        $this->assertSame('optica', $decision['motivo']);
        $this->assertSame(FraseCliente::OPTICA, $decision['reply']);
        $this->assertStringNotContainsString('-31', $decision['reply']);
    }

    public function test_validador_hechos_corta_reinicio_inventado(): void
    {
        $out = (new ValidadorHechos)->validar(
            'Reinicie el router y pruebe de nuevo.',
            ['pudo_medir' => true, 'causas' => [], 'incidente_zona_abierto' => false],
            [],
            FraseCliente::ESCALADO,
        );

        $this->assertTrue($out['filtrado']);
        $this->assertSame('n8n_hechos_reinicio', $out['modo']);
        $this->assertSame(FraseCliente::ESCALADO, $out['reply']);
    }

    public function test_filtro_permite_mbps_de_catalogo_sin_medicion(): void
    {
        $texto = 'Fibra Básico 100 Mbps — Gs. 100.000.';
        $this->assertSame($texto, FraseCliente::filtrar($texto, FraseCliente::ESCALADO));
    }

    public function test_pregunta_de_enlace_no_incluye_estilo_en_la_frase(): void
    {
        $this->assertStringNotContainsString('Estilo Interplus', FraseCliente::ENLACE_OK_PREGUNTA);
        $this->assertStringNotContainsString('obligatorio', FraseCliente::ENLACE_OK_PREGUNTA);
        $this->assertSame(
            FraseCliente::ENLACE_OK_PREGUNTA,
            FraseCliente::filtrar(FraseCliente::ENLACE_OK_PREGUNTA, FraseCliente::ESCALADO)
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

        $zona = FraseCliente::zona('Gloria', 'Benítez');
        $this->assertStringContainsString('Señora Benítez, gracias por avisarnos', $zona);
        $this->assertStringContainsString('novedades', mb_strtolower($zona));
        $this->assertStringContainsString('órdenes', $zona);
        $this->assertStringNotContainsString('agradecemos', mb_strtolower($zona));

        $ok = FraseCliente::zonaRestablecida('Gloria', 'Benítez');
        $this->assertStringContainsString('Señora Benítez', $ok);
        $this->assertStringContainsString('restablecido', mb_strtolower($ok));
        $this->assertStringContainsString(FraseCliente::CIERRE, $ok);
    }

    public function test_incidente_de_zona_usa_frase_con_agradecimiento(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'incidente' => true,
            'texto' => 'sin internet desde la tarde',
            'medicion' => [],
        ]);

        $this->assertSame('zona', $decision['tipo']);
        $this->assertStringContainsString('gracias por avisarnos', mb_strtolower($decision['reply']));
        $this->assertStringContainsString('novedades', mb_strtolower($decision['reply']));
    }

    public function test_comprobante_responde_formal_sin_saludo_interplus(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_comprobante' => true,
            'texto' => 'te mando el comprobante',
            'medicion' => [],
        ]);

        $this->assertSame('comprobante', $decision['tipo']);
        $this->assertTrue($decision['escalate']);

        $enHorario = FraseCliente::comprobante('Gloria', 'Benítez', true);
        $fuera = FraseCliente::comprobante('Gloria', 'Benítez', false);
        $sinNombre = FraseCliente::comprobante(null, null, false);
        $this->assertStringContainsString('Señora Benítez, gracias por enviarnos su comprobante', $enHorario);
        $this->assertStringContainsString('en breve', mb_strtolower($enHorario));
        $this->assertStringContainsString('lunes a viernes', mb_strtolower($fuera));
        $this->assertStringStartsWith('Gracias por enviarnos su comprobante', $sinNombre);
        $this->assertStringNotContainsString('Te saluda', $enHorario);
        $this->assertStringNotContainsString('tu comprobante', $enHorario);
        $this->assertStringNotContainsString('¡Hola', $enHorario);
    }

    public function test_clasifica_cambio_de_wifi_y_un_sitio(): void
    {
        $this->assertTrue(SoporteTexto::pareceCambioWifi('cambia la clave del wifi'));
        $this->assertSame('facebook.com', SoporteTexto::sitio('no me abre facebook'));
        $this->assertTrue(SoporteTexto::pareceFalla('no anda internet'));
        $this->assertTrue(SoporteTexto::pareceFalla('quería saber la señal de mi wifi'));
        $this->assertTrue(SoporteTexto::pareceConsultaEquipos('podes listarme los equipos que ves, si hay algun vecino'));
        $this->assertTrue(SoporteTexto::pideVecinosWifi('si hay algun vecino'));
    }

    public function test_lista_equipos_vista_sin_dbm_y_aclara_vecinos(): void
    {
        $texto = FraseCliente::listaEquiposVista(['Samsung', 'Notebook'], true);
        $this->assertStringContainsString('1. Samsung', $texto);
        $this->assertStringContainsString('vecinos', mb_strtolower($texto));
        $this->assertStringNotContainsString('dBm', $texto);
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
