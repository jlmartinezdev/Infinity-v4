<?php

namespace Tests\Unit\Services\WhatsApp;

use App\Services\WhatsApp\Soporte\FraseCliente;
use App\Services\WhatsApp\Soporte\SoporteTexto;
use App\Services\WhatsApp\WhatsAppAgentService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Casos habituales de clientes: la intención se clasifica bien, sin inventar “verificando”.
 */
class CasosHabitualesClienteTest extends TestCase
{
    /**
     * @param  array<string, bool>  $esperado
     */
    #[DataProvider('casosIntencion')]
    public function test_intencion_habitual(string $texto, array $esperado): void
    {
        $flags = $this->flags($texto);
        foreach ($esperado as $clave => $valor) {
            $this->assertSame(
                $valor,
                $flags[$clave] ?? false,
                "[{$clave}] en «{$texto}»"
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: array<string, bool>}>
     */
    public static function casosIntencion(): array
    {
        $pago = ['datos_transferencia' => true, 'cuanto_abonar' => true, 'falla' => false, 'zona' => false];
        $soloCuentas = ['datos_transferencia' => true, 'cuanto_abonar' => false, 'falla' => false];
        $soloSaldo = ['datos_transferencia' => false, 'cuanto_abonar' => true, 'falla' => false, 'zona' => false];
        $falla = ['falla' => true, 'datos_transferencia' => false, 'cuanto_abonar' => false];
        $wifiCambio = ['wifi_cambio' => true, 'wifi_nombre' => false, 'falla' => false];
        $wifiNombre = ['wifi_nombre' => true, 'wifi_cambio' => false];
        $equipos = ['equipos' => true, 'falla' => false, 'zona' => false];
        $ping = ['ping' => true, 'zona' => false];
        $zona = ['zona' => true, 'cuanto_abonar' => false, 'equipos' => false];
        $catalogo = ['catalogo' => true, 'falla' => false];
        $beneficios = ['beneficios' => true];
        $condiciones = ['condiciones' => true];
        $titular = ['titular' => true];
        $estado = ['estado_servicio' => true];
        $plazo = ['plazo_pago' => true, 'datos_transferencia' => false, 'cuanto_abonar' => false];
        $cierre = ['cierre' => true];
        $idCedula = ['identificador' => true, 'cedula' => true];
        $idCel = ['identificador' => true, 'telefono' => true];

        return [
            // —— pago / transferencia / saldo (formatos reales) ——
            'pago informal noche' => ['Buenas noche disculpame a q cuenta depósito ahora para abonar y cuanto tengo q abonar 100mil yo creo', $pago],
            'a q cuenta deposito' => ['a q cuenta deposito', $soloCuentas],
            'a que cuenta deposito' => ['a que cuenta deposito para pagar', $soloCuentas],
            'donde te pago' => ['donde te pago', $soloCuentas],
            'donde deposito' => ['donde deposito', $soloCuentas],
            'alias para pagar' => ['me pasas el alias para pagar', $soloCuentas],
            'numero de cuenta' => ['me das el numero de cuenta', $soloCuentas],
            'pagar por transferencia' => ['puedo pagar por transferencia?', $soloCuentas],
            'como hago la transferencia' => ['como hago la transferencia', $soloCuentas],
            'cuenta para abonar' => ['cuenta para abonar', $soloCuentas],
            'para abonar' => ['necesito los datos para abonar', $soloCuentas],
            'tigo money' => ['tienen tigo money?', ['datos_transferencia' => false, 'tigo' => true]],
            'cuanto debo' => ['cuanto debo', $soloSaldo],
            'cuantos debo' => ['cuantos debo', $soloSaldo],
            'cuanto tengo q abonar' => ['cuanto tengo q abonar', $soloSaldo],
            'cuanto tengo que abonar' => ['cuanto tengo que abonar', $soloSaldo],
            'cuanto hay que pagar' => ['cuanto hay que pagar', $soloSaldo],
            'mi saldo' => ['mi saldo', $soloSaldo],
            'saldo pendiente' => ['saldo pendiente', $soloSaldo],
            'lo que debo' => ['decime lo que debo', $soloSaldo],
            'quiero saber cuantos debo' => ['Quiero saber cuantos debo', $soloSaldo],
            'plazo si no abonamos' => ['Que pasa si no abonamos hoy', $plazo],
            'plazo hasta cuando' => ['hasta cuando puedo pagar', $plazo],
            'ya pague no es cuenta' => ['ya pague ahi te mando el comprobante', ['datos_transferencia' => false, 'cuanto_abonar' => false]],

            // —— identificación ——
            'solo cedula' => ['4194002', $idCedula],
            'mi cedula es' => ['mi cedula es 4194002', $idCedula],
            'mi cedula con tilde' => ['mi cédula es 4194002', $idCedula],
            'cedula con puntos' => ['2.620.430', $idCedula],
            'cedula de la mama' => ['y el de mi mama 2.620.430', $idCedula],
            'cuantos debe con cedula' => ['cuantos debe 2.620.430', ['cuanto_abonar' => true, 'cedula' => true, 'identificador' => false]],
            'cuantos es su cuenta' => ['cuantos es su cuenta', $soloSaldo],
            'ya insiste saldo' => ['ya?', ['insiste_saldo' => true, 'falla' => false]],
            'ya esta insiste' => ['ya esta?', ['insiste_saldo' => true]],
            'de que hablabamos' => ['de que estabamos hablando?', ['insiste_saldo' => true]],
            'de que hablamos' => ['de que hablamos?', ['insiste_saldo' => true, 'catalogo' => false]],
            'como que no' => ['como que no', ['insiste_saldo' => true, 'catalogo' => false]],
            'ci numero' => ['ci 4765661', $idCedula],
            'nro cedula' => ['nro 4765661', $idCedula],
            'celular local' => ['0986504437', $idCel],
            'celular 595' => ['595 986 504437', $idCel],
            'mi celular es' => ['mi celular es 0986504437', $idCel],
            'mi whatsapp es' => ['mi whatsapp es 595986504437', $idCel],
            'telefono con mas' => ['+595986504437', $idCel],

            // —— reclamos / falla ——
            'sin internet' => ['no tengo internet', $falla],
            'no anda' => ['no anda el wifi', $falla],
            'no funciona' => ['no funciona internet', $falla],
            'se corta' => ['se corta todo el tiempo', $falla],
            'muy lento' => ['esta muy lento', $falla],
            'desde ayer' => ['desde ayer no anda', $falla],
            'quedo sin' => ['quedo sin servicio', $falla],
            'intermitente' => ['anda intermitente', $falla],
            'no navega' => ['no navega nada', $falla],
            'sin senal' => ['sin señal de wifi', $falla],
            'reclamo internet' => ['reclamo por el internet', $falla],
            'pagina no abre' => ['la pagina no abre', $falla],

            // —— wifi / equipos / ping ——
            'cambiar clave wifi' => ['cambiar la clave del wifi', $wifiCambio],
            'cambiar wifi' => ['cambiar el wifi', $wifiCambio],
            'nombre del wifi' => ['cual es el nombre del wifi', $wifiNombre],
            'como se llama wifi' => ['como se llama mi wifi', $wifiNombre],
            'mirar wifi conectados' => ['podes mirar mi wifi y los conectados ahora', $equipos],
            'que ves de mi red' => ['y que es lo que ves de mi red', $equipos],
            'quien esta conectado' => ['quien esta conectado', $equipos],
            'equipos conectados' => ['que equipos hay conectados', $equipos],
            'los conectados' => ['los conectados', $equipos],
            'ping no anda' => ['el ping no anda?', $ping],
            'mira el ping' => ['podes mirar el ping', $ping],
            'clave wifi actual' => ['pasame la clave del wifi', ['clave_wifi_actual' => true, 'wifi_cambio' => false]],

            // —— zona / seguimiento ——
            'alguna novedad' => ['alguna novedad', $zona],
            'sigue sin internet' => ['sigue sin internet', $zona],
            'todavia sin servicio' => ['todavia sin servicio', $zona],
            'cuando vuelve' => ['cuando vuelve', $zona],
            'sigue el corte' => ['sigue el corte', $zona],
            'gracias corto zona' => ['gracias', $zona],

            // —— con zona abierta, pregunta ajena ——
            'debo con zona' => ['cuanto debo', ['cuanto_abonar' => true, 'ajena_zona' => true, 'seguimiento_zona' => false]],
            'equipos con zona' => ['mirar mi wifi', ['equipos' => true, 'ajena_zona' => true, 'seguimiento_zona' => false]],
            'ping con zona' => ['el ping no anda', ['ping' => true, 'ajena_zona' => true, 'seguimiento_zona' => false]],
            'pago con zona' => ['a q cuenta deposito', ['datos_transferencia' => true, 'ajena_zona' => true, 'seguimiento_zona' => false]],

            // —— servicio / plan / estado ——
            'que tal mi servicio' => ['y que tal esta mi servicio ahora', $estado],
            'como esta mi internet' => ['como esta mi internet', $estado],
            'saber de mi servicio' => ['quiero saber de mi servicio', ['consulta_servicio' => true]],
            'mi plan' => ['cual es mi plan', ['consulta_servicio' => true]],
            'detalle servicio' => ['detalle de mi servicio', ['consulta_servicio' => true]],
            'quien es el titular' => ['quien es el titular', $titular],
            'a nombre de quien' => ['a nombre de quien esta', $titular],

            // —— catálogo / beneficios / condiciones ——
            'que planes hay' => ['que planes hay', $catalogo],
            'precios de planes' => ['precios de los planes', $catalogo],
            'cuanto cuesta fibra' => ['cuanto cuesta el plan de fibra', $catalogo],
            'beneficios' => ['que beneficios tiene', $beneficios],
            'estandar y premium' => ['diferencia entre estandar y premium', $beneficios],
            'condiciones' => ['condiciones del servicio', $condiciones],
            'comodato' => ['es comodato el equipo?', $condiciones],
            'fecha de pago' => ['cual es la fecha de pago', $condiciones],

            // —— tv / luz / cierre ——
            'televisor hdmi' => ['no anda el televisor', ['tv' => true]],
            'sin luz' => ['no hay luz en el router', ['luz' => true]],
            'ya funciona' => ['ya funciona gracias', ['ya_funciona' => true]],
            'muchas gracias' => ['muchas gracias', $cierre],
            'ok listo' => ['listo', $cierre],

            // —— impresora ——
            'impresora' => ['no me aparece la impresora', ['impresora' => true]],

            // —— formatos nuevos / typos ——
            'deposito sin tilde' => ['a q cuenta deposito ahora', $soloCuentas],
            'abonar typo' => ['cuanto tengo q abonar porfa', $soloSaldo],
            'wifi conectad typo' => ['mira mi wifi y los conectados', $equipos],
            'senal sin eñe' => ['sin senal de wifi', $falla],
            'buenas noches pago' => ['buenas noches a que cuenta deposito', $soloCuentas],
            'porfa saldo' => ['porfa cuanto debo', $soloSaldo],
            'necesito abonar' => ['necesito abonar cuanto es', ['cuanto_abonar' => true]],
            'me pasas cuentas' => ['me pasas las cuentas para depositar', $soloCuentas],
            'bnf o familiar' => ['me das bnf o banco familiar', ['datos_transferencia' => false]], // no alcanza solo el banco
            '100 mil no es saldo solo' => ['100mil yo creo', ['cuanto_abonar' => false, 'datos_transferencia' => false]],

            // —— corpus real (5 meses) ——
            'corpus se puede abonar' => ['Queria saber si se puede abonar ya', $soloSaldo],
            'corpus cedula numero' => ['Mi cédula numero es 6960028', $idCedula],
            'corpus documento puntos' => ['mi documento 4.061.491', $idCedula],
            'corpus ruteador' => ['Desde anteayer el ruteador estuvo sin conexión a internet', $falla],
            'corpus no alza' => ['el de mi casa no alza más el Internet', $falla],
            'corpus quiero pagar wifi' => ['Quiero pagar mi wifi', $soloSaldo],
            'corpus como pagar cuota' => ['Hola buenas tardes como tengo que pagar la cuota de del Wifi', $soloSaldo],
            'corpus como abonar dias' => ['Quisiera saber como puedo abonar por los días que estamos utilizando el servicio del wifi', ['cuanto_abonar' => true]],
            'corpus cuenta tranferir' => ['Me podrias pasar una cuenta para tranferir', $soloCuentas],
            'corpus nro cuenta pagar' => ['Buenas tardes a que nro. De cuenta puedo pagar por mi wifi porfavor.', ['datos_transferencia' => true]],
            'corpus debo abonar instalacion' => ['Debo abonar ya antes de la instalación?', ['cuanto_abonar' => true]],
            'corpus volver a abonar' => ['yo tengo q volver a abonar el 16 de este mes', ['cuanto_abonar' => true]],
            'corpus queremos wifi' => ['Queremos el wifi', ['instalacion' => true]],
            'corpus me interesa internet' => ['Me interesa el Internet', ['instalacion' => true]],
            'corpus para cuando wifi' => ['Para cuando el WiFi', ['instalacion' => true]],
            'corpus pedido wifi' => ['Referente al pedido de wifi', ['instalacion' => true]],

            // —— negativos: no confundir ——
            'hola no falla' => ['hola', ['falla' => false, 'datos_transferencia' => false, 'cuanto_abonar' => false]],
            'ubicacion no pago' => ['ahi te mando ubicacion', ['datos_transferencia' => false]],
            'cobertura' => ['tienen cobertura en yuty', ['cobertura' => true, 'falla' => false]],
            'instalacion' => ['quiero instalar internet', ['instalacion' => true, 'falla' => false]],
            'baja' => ['quiero dar de baja el servicio', ['baja' => true]],
            'cambio plan' => ['quiero cambiar de plan', ['cambio_plan' => true]],
            'no listar ajenas' => ['hay vecinos en el wifi', ['vecinos' => true, 'equipos' => true]],
        ];
    }

    public function test_suite_tiene_al_menos_100_casos(): void
    {
        $this->assertGreaterThanOrEqual(100, count(self::casosIntencion()));
    }

    public function test_respuestas_pago_no_inventan_verificar(): void
    {
        $canned = WhatsAppAgentService::textosCanned()['transferencia'];
        foreach ([
            FraseCliente::saldoYCuentas(['saldo' => 85000], $canned),
            FraseCliente::saldoYCuentas(null, $canned),
            FraseCliente::saldoPendiente(['saldo' => 0]),
            FraseCliente::listaEquiposVista([]),
            FraseCliente::listaEquiposVista(['Samsung']),
            FraseCliente::zonaSeguimiento('Gloria', 'Britez'),
            FraseCliente::REVISANDO_SERVICIO,
        ] as $reply) {
            $bajo = mb_strtolower($reply);
            $this->assertStringNotContainsString('estamos verificando', $bajo);
            $this->assertStringNotContainsString('a la brevedad', $bajo);
            $this->assertStringNotContainsString('costo', $bajo);
            $this->assertDoesNotMatchRegularExpression('/no listamos redes/iu', $reply);
            $this->assertDoesNotMatchRegularExpression('/CAMBIAR WIFI/u', $reply);
        }
    }

    public function test_sandbox_es_595000_no_595988(): void
    {
        $this->assertTrue(WhatsAppAgentService::esTelefonoTest('595000474032'));
        $this->assertFalse(WhatsAppAgentService::esTelefonoTest('595988474032'));
        $this->assertSame('595000474032', WhatsAppAgentService::telefonoSandbox('474032'));
    }

    /**
     * @return array<string, bool|string|null>
     */
    private function flags(string $texto): array
    {
        $cedula = SoporteTexto::cedulaEnTexto($texto);
        $tel = SoporteTexto::telefonoEnTexto($texto);

        return [
            'datos_transferencia' => WhatsAppAgentService::textoPareceDatosTransferencia($texto),
            'cuanto_abonar' => SoporteTexto::pareceCuantoAbonar($texto),
            'insiste_saldo' => SoporteTexto::pareceInsisteSaldo($texto),
            'falla' => SoporteTexto::pareceFalla($texto),
            'zona' => SoporteTexto::pareceSeguimientoZona($texto) || (SoporteTexto::pareceFalla($texto) && SoporteTexto::esSeguimientoZona(true, $texto)),
            'seguimiento_zona' => SoporteTexto::esSeguimientoZona(true, $texto),
            'ajena_zona' => SoporteTexto::preguntaAjenaAZona($texto) || WhatsAppAgentService::textoPareceDatosTransferencia($texto) || SoporteTexto::pareceCuantoAbonar($texto),
            'wifi_cambio' => SoporteTexto::pareceCambioWifi($texto),
            'wifi_nombre' => SoporteTexto::pareceConsultaNombreWifi($texto),
            'clave_wifi_actual' => SoporteTexto::parecePideClaveWifiActual($texto),
            'equipos' => SoporteTexto::pareceConsultaEquipos($texto),
            'vecinos' => SoporteTexto::pideVecinosWifi($texto),
            'ping' => SoporteTexto::pareceConsultaPing($texto),
            'catalogo' => SoporteTexto::pareceConsultaCatalogo($texto),
            'beneficios' => WhatsAppAgentService::textoPareceBeneficiosPlanes($texto),
            'condiciones' => WhatsAppAgentService::textoPareceCondicionesServicio($texto),
            'titular' => SoporteTexto::pareceConsultaTitular($texto),
            'estado_servicio' => SoporteTexto::pareceEstadoServicio($texto),
            'consulta_servicio' => SoporteTexto::pareceConsultaServicio($texto),
            'plazo_pago' => WhatsAppAgentService::textoParecePlazoPago($texto),
            'cierre' => SoporteTexto::pareceCierre($texto),
            'ya_funciona' => SoporteTexto::pareceYaFunciona($texto),
            'tv' => SoporteTexto::pareceTelevision($texto),
            'luz' => SoporteTexto::pareceLuz($texto),
            'impresora' => SoporteTexto::pareceImpresora($texto),
            'identificador' => SoporteTexto::esSoloIdentificador($texto),
            'cedula' => $cedula !== null,
            'telefono' => $tel !== null,
            'tigo' => WhatsAppAgentService::textoPideTigoMoney($texto),
            'cobertura' => WhatsAppAgentService::textoPareceConsultaCobertura($texto),
            'instalacion' => WhatsAppAgentService::textoPareceInstalacion($texto),
            'baja' => WhatsAppAgentService::textoPareceBajaServicio($texto),
            'cambio_plan' => WhatsAppAgentService::textoPareceCambioPlan($texto),
        ];
    }
}
