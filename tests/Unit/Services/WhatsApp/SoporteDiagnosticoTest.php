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

    public function test_televisor_no_repite_el_cable_del_router(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'paso' => 'cable',
            'texto' => 'Desde ayer no anda mi tv',
            'medicion' => [
                'pudo_medir' => true,
                'lan_speed' => 100,
                'lan_conectado' => true,
            ],
        ]);

        $this->assertSame(FraseCliente::TV, $decision['reply']);
        $this->assertStringContainsString('HDMI', $decision['reply']);
        $this->assertStringNotContainsString('router', mb_strtolower($decision['reply']));
    }

    public function test_ya_funciona_cierra_sin_volver_al_cable(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'paso' => 'cable',
            'texto' => 'Ya me funciona gracias',
            'medicion' => ['lan_speed' => 100],
        ]);

        $this->assertSame('cierre', $decision['tipo']);
        $this->assertSame(FraseCliente::YA_FUNCIONA, $decision['reply']);
    }

    public function test_nada_o_sin_senal_pasa_a_tecnico_sin_listar_equipos(): void
    {
        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'paso' => 'sitio',
            'texto' => 'Nada, no hay señal ninguna',
            'medicion' => [
                'pudo_medir' => true,
                'equipos' => [['nombre' => 'Samsung', 'rssi' => -40]],
            ],
        ]);

        $this->assertSame('escalado', $decision['tipo']);
        $this->assertTrue($decision['escalate']);
        $this->assertStringNotContainsString('Samsung', $decision['reply']);
        $this->assertStringNotContainsString('¿No le anda', $decision['reply']);
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
        $this->assertSame('Sr.', TratoCliente::resolver('Rufino', 'Leiva Avalos')['trato']);
        $this->assertSame('Sr. Leiva', TratoCliente::vocativo('Rufino', 'Leiva Avalos'));
        $this->assertSame('Sra.', TratoCliente::resolver('María', 'López')['trato']);
        $this->assertSame('Sra. López', TratoCliente::vocativo('María', 'López'));
        $this->assertNull(TratoCliente::generoDe('Alex'));
        $this->assertNull(TratoCliente::vocativo('Alex', 'Gomez'));
        $this->assertSame('Sr.', TratoCliente::resolver('Derlis', 'Cabañas')['trato']);
        $this->assertSame('Sr. Cabañas', TratoCliente::vocativo('Derlis', 'Cabañas'));

        $texto = FraseCliente::escalado('7156404', 'María', 'López');
        $this->assertStringContainsString('Sra. López, recibimos su aviso.', $texto);
        $this->assertStringContainsString('monitoreando su servicio', $texto);
        $this->assertStringNotContainsString('7.156.404', $texto);
        $this->assertStringNotContainsString('cédula', mb_strtolower($texto));
        $this->assertStringNotContainsString('971 714 322', $texto);
        $this->assertStringNotContainsString('agradecemos', mb_strtolower($texto));
        $this->assertStringNotContainsString('Lamentamos', $texto);

        $cierre = FraseCliente::resuelto('Rufino', 'Leiva');
        $this->assertStringContainsString('Sr. Leiva', $cierre);
        $this->assertStringContainsString(FraseCliente::CIERRE, $cierre);

        $zona = FraseCliente::zona('Gloria', 'Benítez');
        $this->assertStringContainsString('Sra. Benítez, gracias por avisarnos', $zona);
        $this->assertStringContainsString('novedades', mb_strtolower($zona));
        $this->assertStringContainsString('órdenes', $zona);
        $this->assertStringNotContainsString('agradecemos', mb_strtolower($zona));

        $propio = FraseCliente::servicioRestablecido('Gloria', 'Benítez');
        $this->assertStringContainsString('volvió a conectar', mb_strtolower($propio));
        $this->assertStringContainsString('Sra. Benítez', $propio);

        $this->assertTrue(\App\Services\WhatsApp\Soporte\IncidenteZonaService::cierreReciente(
            \Carbon\Carbon::parse('2026-11-02 11:31:00'),
            \Carbon\Carbon::parse('2026-11-02 12:00:00'),
        ));
        $this->assertFalse(\App\Services\WhatsApp\Soporte\IncidenteZonaService::cierreReciente(
            \Carbon\Carbon::parse('2026-11-02 11:31:00'),
            \Carbon\Carbon::parse('2026-11-03 13:50:00'),
        ));

        $ok = FraseCliente::zonaRestablecida('Gloria', 'Benítez');
        $this->assertStringContainsString('Sra. Benítez', $ok);
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
        $this->assertStringContainsString('Sra. Benítez, gracias por enviarnos su comprobante', $enHorario);
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

    public function test_lista_equipos_vista_sin_dbm_y_sin_asumir_clave(): void
    {
        $texto = FraseCliente::listaEquiposVista(['Samsung', 'Notebook'], true);
        $this->assertStringContainsString('2 equipos', $texto);
        $this->assertStringContainsString('1. Samsung', $texto);
        $this->assertStringContainsString('alguna duda', $texto);
        $this->assertStringNotContainsString('CAMBIAR WIFI', $texto);
        $this->assertStringNotContainsString('redes WiFi', $texto);
        $vacio = FraseCliente::listaEquiposVista([]);
        $this->assertStringNotContainsString('ajenas', $vacio);
        $this->assertStringContainsString('alguna duda', $vacio);
        $this->assertStringNotContainsString('dBm', $texto);
        $this->assertTrue(SoporteTexto::pareceConfirmaCambioWifi('cambiar wifi'));
        $this->assertTrue(SoporteTexto::pideVecinosWifi('creo que un vecino está enganchado al wifi'));
    }

    public function test_consulta_nombre_wifi_no_dispara_cambio_ni_inventa_ssid(): void
    {
        $this->assertTrue(SoporteTexto::pareceConsultaNombreWifi('que nombre de wifi tengo?'));
        $this->assertTrue(SoporteTexto::pareceConsultaNombreWifi('y el nombre del wifi?'));
        $this->assertTrue(SoporteTexto::pareceConsultaEquipos('que es lo que ves en mi red?'));
        $this->assertTrue(SoporteTexto::pareceConsultaNombreWifi('que no lo ves en el escaneo de red el nombre actual?'));
        $this->assertFalse(SoporteTexto::pareceConsultaNombreWifi('cambiar el nombre del wifi a CasaNueva'));
        $this->assertTrue(SoporteTexto::pareceFalla('me deis la señal de mi servicio'));
        $this->assertTrue(SoporteTexto::pareceConsultaTitular('a nombre de quien esta?'));
        $this->assertStringContainsString('Gloria', FraseCliente::titularServicio(['nombre' => 'Gloria Britez', 'cedula' => '3204406']));
        $this->assertFalse(SoporteTexto::pareceCambioWifi('que nombre de wifi tengo?'));
        $this->assertNull(SoporteTexto::nombreWifi('que nombre de wifi tengo?'));
        $this->assertFalse(SoporteTexto::pareceConfirmaCambioWifi('como vas a cambiar?'));
        $this->assertFalse(SoporteTexto::pareceConfirmaCambioWifi('hace cuanto que no se apaga mi red?'));
        $this->assertTrue(SoporteTexto::pareceCambioWifi('cambiar la clave del wifi'));
        $this->assertSame('CasaNueva', SoporteTexto::nombreWifi('cambiar el nombre del wifi a CasaNueva'));
        $this->assertStringContainsString('Interplus-5G', FraseCliente::nombreWifiActual(['Interplus-5G']));
    }

    public function test_saca_cedula_y_telefono_del_texto(): void
    {
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('soy 4765661 Por que no anda la señal de wifi'));
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('nr 4765661'));
        $this->assertSame('4765661', SoporteTexto::cedulaEnTexto('nro 4765661'));
        $this->assertSame('4194002', SoporteTexto::cedulaEnTexto('4194002'));
        $this->assertSame('4194002', SoporteTexto::cedulaEnTexto('mi cedula es 4194002'));
        $this->assertSame('4194002', SoporteTexto::cedulaEnTexto('mi cédula es 4194002'));
        $this->assertSame('2620430', SoporteTexto::cedulaEnTexto('2.620.430'));
        $this->assertSame('2620430', SoporteTexto::cedulaEnTexto('y el de mi mama 2.620.430'));
        $this->assertSame('2620430', SoporteTexto::cedulaEnTexto('cuantos debe 2.620.430'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('2.620.430'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('y el de mi mama 2.620.430'));
        $this->assertTrue(SoporteTexto::pareceCuantoAbonar('cuantos es su cuenta'));
        $this->assertTrue(SoporteTexto::pareceCuantoAbonar('cuantos debe 2.620.430'));
        $this->assertTrue(SoporteTexto::pareceInsisteSaldo('ya?'));
        $this->assertTrue(SoporteTexto::pareceInsisteSaldo('ya esta?'));
        $this->assertTrue(SoporteTexto::pareceInsisteSaldo('de que estabamos hablando?'));
        $this->assertSame('2620430', SoporteTexto::cedulaEnTexto('cuantos debe 2.620.430 ya? cuantos debe 2.620.430'));
        $filtradoConsulta = FraseCliente::filtrar(
            'Seguimos consultando el valor a abonar correspondiente a la cédula 2.620.430. En breve le informaremos por este medio.',
            FraseCliente::saldoPendiente(['saldo' => 50000])
        );
        $this->assertStringNotContainsString('consultando', mb_strtolower($filtradoConsulta));
        $this->assertStringContainsString('50.000', $filtradoConsulta);
        $this->assertStringNotContainsString(
            'consultando',
            mb_strtolower(FraseCliente::filtrar(
                'Estamos consultando el valor a abonar correspondiente a la cédula 2.620.430. En breve le informaremos.',
                FraseCliente::saldoPendiente(['saldo' => 50000])
            ))
        );
        $this->assertSame('595986504437', SoporteTexto::telefonoEnTexto('595 986 504437'));
        $this->assertSame('595986504437', SoporteTexto::telefonoEnTexto('0986504437'));
        $this->assertSame('595986504437', SoporteTexto::telefonoEnTexto('mi celular es 0986504437'));
        $this->assertSame('595986504437', SoporteTexto::telefonoEnTexto('mi whatsapp es 595986504437'));
        $this->assertSame('5491125474032', SoporteTexto::telefonoEnTexto('+549112547-4032'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('Rufino Leiva Avalos'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('soy Rufino Leiva Avalos no anda el wifi'));
        $this->assertSame('rufino leiva avalos', SoporteTexto::nombreEnTexto('Rufino Leiva Avalos no anda el wifi'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('595 986 504437'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('mi celular es 0986504437'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('mi whatsapp es 595986504437'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('nr 4765661'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('4194002'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('mi cedula es 4194002'));
        $this->assertTrue(SoporteTexto::esSoloIdentificador('mi cédula es 4194002'));
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

    public function test_validador_nunca_devuelve_prompt_de_catalogo(): void
    {
        $lista = "Estos son nuestros planes vigentes (mensual, IVA incluido):\nFibra:\n• Plan Básico 100 Mbps — Gs. 100.000";
        $prompt = "Planes vigentes (mensual...\nVenta suave: no venda\nSalida WhatsApp: una línea\n· prioridad 2";
        $out = (new ValidadorHechos)->validar(
            'El plan cuesta Gs. 120.000 mensual',
            [],
            [
                'fibra' => [['precio' => 100000]],
                'antena' => [['precio' => 150000]],
                'planes_texto' => $lista,
            ],
            FraseCliente::ESCALADO,
        );
        $this->assertTrue($out['filtrado']);
        $this->assertSame('n8n_catalogo', $out['modo']);
        $this->assertSame($lista, $out['reply']);
        $this->assertStringNotContainsString('Venta suave', $out['reply']);

        $fuga = (new ValidadorHechos)->validar($prompt, [], ['planes_texto' => $lista], FraseCliente::ESCALADO);
        $this->assertTrue($fuga['filtrado']);
        $this->assertSame('n8n_fuga_prompt', $fuga['modo']);
        $this->assertSame($lista, $fuga['reply']);
    }

    public function test_cedula_con_palabra_plan_no_dispara_precio_inventado(): void
    {
        $out = (new ValidadorHechos)->validar(
            'Su cédula 5263934 figura con el plan básico activo.',
            [],
            [
                'fibra' => [['precio' => 100000]],
                'antena' => [],
                'planes_texto' => 'lista limpia',
            ],
            FraseCliente::ESCALADO,
        );
        $this->assertFalse($out['filtrado']);
        $this->assertStringContainsString('5263934', $out['reply']);
    }

    public function test_filtro_permite_cotizacion_antena_con_mbps(): void
    {
        $texto = 'Antena Estandar 20 Mbps — Gs. 150.000';
        $this->assertSame($texto, FraseCliente::filtrar($texto, FraseCliente::ESCALADO));
        $this->assertTrue(FraseCliente::pareceFugaLineamiento('Venta suave: no venda internet genérico'));
        $this->assertFalse(FraseCliente::pareceFugaLineamiento($texto));
    }

    public function test_detalle_servicio_y_deteccion_intents(): void
    {
        $this->assertTrue(SoporteTexto::pareceConsultaServicio('quiero ver detalle de mi servicio completo'));
        $this->assertTrue(SoporteTexto::pareceConsultaServicio('quiero saber de mi servicio'));
        $this->assertTrue(SoporteTexto::pareceEstadoServicio('y que tal esta mi servicio ahora'));
        $this->assertTrue(SoporteTexto::pareceConsultaEquipos('podes mirar mi wifi y los conectados ahora'));
        $this->assertSame(
            'Plan Estándar — valor mensual Gs. 150.000.',
            FraseCliente::valorEnVezDeCosto('Plan Estándar — costo mensual Gs. 150.000.')
        );
        $this->assertSame('Es bonificado.', FraseCliente::valorEnVezDeCosto('Es sin costo.'));
        $this->assertSame(
            FraseCliente::REVISANDO_SERVICIO,
            FraseCliente::filtrar('En este momento no registramos incidentes reportados en su zona.', FraseCliente::ESCALADO)
        );
        $this->assertSame(
            FraseCliente::REVISANDO_SERVICIO,
            FraseCliente::filtrar('En este momento no es posible realizar la medición de su equipo ni visualizar los dispositivos conectados.', FraseCliente::ESCALADO)
        );
        $this->assertStringContainsString('valor mensual', FraseCliente::detalleServicio([
            'plan_nombre' => 'PLAN ESTANDAR',
            'plan_precio' => 150000,
            'estado' => 'activo',
            'saldo' => 0,
            'es_antena' => false,
        ]));
        $this->assertStringNotContainsString('costo', FraseCliente::detalleServicio([
            'plan_nombre' => 'PLAN ESTANDAR',
            'plan_precio' => 150000,
            'estado' => 'activo',
            'saldo' => 0,
            'es_antena' => false,
        ]));
        $this->assertTrue(SoporteTexto::pareceConsultaCatalogo('qué planes de fibra tienen'));
        $this->assertFalse(SoporteTexto::pareceConsultaCatalogo('cambiar la clave del wifi'));

        $detalle = FraseCliente::detalleServicio([
            'plan_nombre' => 'PLAN ESTANDAR 20 Mbps',
            'plan_precio' => 150000,
            'estado' => 'activo',
            'saldo' => 0,
            'es_antena' => true,
            'cedula' => '3204406',
        ]);
        $this->assertStringContainsString('PLAN ESTANDAR 20 Mbps', $detalle);
        $this->assertStringContainsString('activo', $detalle);
        $this->assertStringContainsString('sin deuda', mb_strtolower($detalle));
        $this->assertStringNotContainsString('Venta suave', $detalle);
    }

    public function test_zona_abierta_no_tapa_otra_pregunta(): void
    {
        $this->assertTrue(SoporteTexto::pareceConsultaEquipos('y que es lo que ves de mi red'));
        $this->assertTrue(SoporteTexto::pareceConsultaPing('el ping no anda?'));
        $this->assertTrue(SoporteTexto::pareceConsultaServicio('cuantos debo?'));

        $this->assertFalse(SoporteTexto::esSeguimientoZona(true, 'cuantos debo?'));
        $this->assertFalse(SoporteTexto::esSeguimientoZona(true, 'podes mirar mi wifi y los conectados ahora'));
        $this->assertFalse(SoporteTexto::esSeguimientoZona(true, 'el ping no anda?'));
        $this->assertFalse(SoporteTexto::esSeguimientoZona(true, 'y que es lo que ves de mi red'));
        $this->assertTrue(SoporteTexto::esSeguimientoZona(true, 'sigue sin internet'));
        $this->assertFalse(SoporteTexto::esSeguimientoZona(false, 'sigue sin internet'));

        $ping = FraseCliente::pingVisto(['pudo_medir' => true, 'ping_cpe_ok' => false]);
        $this->assertStringContainsString('no responde', $ping);
        $this->assertStringContainsString('alguna duda', $ping);
        $this->assertStringNotContainsString('zona', mb_strtolower($ping));
        $this->assertStringNotContainsString('CAMBIAR WIFI', FraseCliente::redEnRevision());

        $decision = (new EvaluadorDiagnostico)->decidir([
            'estado' => 'activo',
            'parece_falla' => true,
            'incidente' => true,
            'pregunta_ajena' => true,
            'texto' => 'el ping no anda?',
            'medicion' => ['pudo_medir' => true, 'ping_cpe_ok' => false],
        ]);
        $this->assertNotSame('zona', $decision['tipo']);
    }
}
