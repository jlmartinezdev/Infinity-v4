<?php

namespace Tests\Unit\Services;

use App\Models\Olt;
use App\Models\Plan;
use App\Models\Router;
use App\Models\RouterIpPool;
use App\Models\Servicio;
use App\Models\ServicioConexionEvento;
use App\Models\TipoTecnologia;
use App\Services\Huawei\HuaweiOnuService;
use App\Services\MikroTikService;
use App\Services\Olt\VsolGponClient;
use App\Services\RegistroSenalInstalacionService;
use App\Services\Ubnt\UbntAntenaService;
use Tests\TestCase;

class RegistroSenalInstalacionServiceTest extends TestCase
{
    public function test_fibra_lee_potencia_rx_desde_olt(): void
    {
        $mikrotik = $this->createMock(MikroTikService::class);
        $vsol = $this->createMock(VsolGponClient::class);
        $ubnt = $this->createMock(UbntAntenaService::class);
        $huawei = $this->createMock(HuaweiOnuService::class);

        $mikrotik->method('consultarClienteRed')->willReturn(['success' => true, 'mac' => null]);
        $huawei->expects($this->never())->method('leerOptica');
        $vsol->expects($this->once())
            ->method('localizarMacYConsultarOnu')
            ->willReturn([
                'success' => true,
                'pon_port' => 2,
                'onu_index' => 7,
                'rx_power_dbm' => -21.45,
                'tx_power_dbm' => 2.10,
                'estado' => 'up',
            ]);

        $lectura = $this->service($mikrotik, $vsol, $ubnt, $huawei)
            ->leerParaServicio($this->servicioFibra());

        $this->assertTrue($lectura['ok']);
        $this->assertSame(ServicioConexionEvento::TIPO_SENAL_OPTICA, $lectura['tipo']);
        $this->assertEquals(-21.45, $lectura['valor']);
        $this->assertStringContainsString('Potencia óptica RX', $lectura['mensaje']);
        $this->assertStringContainsString('-21,45 dBm', $lectura['mensaje']);
    }

    public function test_fibra_usa_onu_huawei_si_olt_no_tiene_mac(): void
    {
        $mikrotik = $this->createMock(MikroTikService::class);
        $vsol = $this->createMock(VsolGponClient::class);
        $ubnt = $this->createMock(UbntAntenaService::class);
        $huawei = $this->createMock(HuaweiOnuService::class);

        $vsol->expects($this->never())->method('localizarMacYConsultarOnu');
        $huawei->method('puedeOperar')->willReturn(true);
        $huawei->expects($this->once())->method('leerOptica')->willReturn([
            'success' => true,
            'rx_power_dbm' => -18.2,
            'tx_power_dbm' => 1.9,
            'via' => 'web',
        ]);

        $servicio = $this->servicioFibra();
        $servicio->mac_address = null;

        $lectura = $this->service($mikrotik, $vsol, $ubnt, $huawei)
            ->leerParaServicio($servicio);

        $this->assertTrue($lectura['ok']);
        $this->assertEquals(-18.2, $lectura['valor']);
        $this->assertSame('web', $lectura['payload']['via']);
    }

    public function test_antena_lee_intensidad_desde_wstalist(): void
    {
        $mikrotik = $this->createMock(MikroTikService::class);
        $vsol = $this->createMock(VsolGponClient::class);
        $ubnt = $this->createMock(UbntAntenaService::class);
        $huawei = $this->createMock(HuaweiOnuService::class);

        $mikrotik->method('consultarClienteRed')->willReturn(['success' => true, 'mac' => 'AA:BB:CC:DD:EE:01']);
        $vsol->expects($this->never())->method('localizarMacYConsultarOnu');
        $ubnt->expects($this->once())
            ->method('consultarWstalist')
            ->with('10.20.0.8', 'AA:BB:CC:DD:EE:01')
            ->willReturn([
                'success' => true,
                'signal_dbm' => -62.0,
                'snr_db' => 28.0,
                'comando' => 'wstalist',
            ]);

        $lectura = $this->service($mikrotik, $vsol, $ubnt, $huawei)
            ->leerParaServicio($this->servicioAntena());

        $this->assertTrue($lectura['ok']);
        $this->assertSame(ServicioConexionEvento::TIPO_SENAL_ANTENA, $lectura['tipo']);
        $this->assertEquals(-62.0, $lectura['valor']);
        $this->assertStringContainsString('Intensidad de antena', $lectura['mensaje']);
        $this->assertStringContainsString('-62,00 dBm', $lectura['mensaje']);
    }

    public function test_tecnologia_desconocida_se_omite(): void
    {
        $servicio = new Servicio;
        $servicio->servicio_id = 99;
        $servicio->ip = '10.0.0.9';
        $plan = new Plan;
        $plan->nombre = 'Plan TV';
        $tech = new TipoTecnologia;
        $tech->descripcion = 'Televisión';
        $plan->setRelation('tipoTecnologia', $tech);
        $servicio->setRelation('plan', $plan);
        $servicio->setRelation('cliente', null);
        $servicio->setRelation('pool', null);
        $servicio->setRelation('cajaNapPuertoActivo', null);

        $lectura = $this->service(
            $this->createMock(MikroTikService::class),
            $this->createMock(VsolGponClient::class),
            $this->createMock(UbntAntenaService::class),
            $this->createMock(HuaweiOnuService::class),
        )->leerParaServicio($servicio);

        $this->assertFalse($lectura['ok']);
        $this->assertTrue($lectura['omitido']);
        $this->assertNull($lectura['mensaje']);
    }

    public function test_resumen_pedido_concatena_lecturas_y_avisos(): void
    {
        $texto = RegistroSenalInstalacionService::textoResumenPedido([
            'mensajes' => ['Potencia óptica RX -21,45 dBm registrada.'],
            'warnings' => ['El servicio no tiene IP para leer la intensidad de la antena.'],
        ]);

        $this->assertStringContainsString('Potencia óptica RX -21,45 dBm registrada.', $texto);
        $this->assertStringContainsString('El servicio no tiene IP para leer la intensidad de la antena.', $texto);
    }

    public function test_fuente_instalacion_tiene_etiqueta(): void
    {
        $evento = new ServicioConexionEvento;
        $evento->fuente = ServicioConexionEvento::FUENTE_INSTALACION;

        $this->assertSame('Finalizar instalación', $evento->etiquetaFuente());
    }

    private function service(
        MikroTikService $mikrotik,
        VsolGponClient $vsol,
        UbntAntenaService $ubnt,
        HuaweiOnuService $huawei,
    ): RegistroSenalInstalacionService {
        return new RegistroSenalInstalacionService($mikrotik, $vsol, $ubnt, $huawei);
    }

    private function servicioFibra(): Servicio
    {
        $servicio = new Servicio;
        $servicio->servicio_id = 11;
        $servicio->ip = '10.10.0.20';
        $servicio->mac_address = 'AA:BB:CC:DD:EE:FF';
        $servicio->usuario_pppoe = 'fibra11';

        $tech = new TipoTecnologia;
        $tech->descripcion = 'Fibra GPON';
        $plan = new Plan;
        $plan->nombre = 'Fibra 200';
        $plan->setRelation('tipoTecnologia', $tech);

        $olt = new Olt;
        $olt->olt_id = 4;
        $olt->ip = '192.168.1.2';
        $olt->gestion_password = 'secret';

        $router = new Router;
        $router->router_id = 3;
        $router->setRelation('nodo', null);

        $pool = new RouterIpPool;
        $pool->olt_id = 4;
        $pool->router_id = 3;
        $pool->setRelation('olt', $olt);
        $pool->setRelation('router', $router);

        $servicio->setRelation('plan', $plan);
        $servicio->setRelation('pool', $pool);
        $servicio->setRelation('cliente', null);
        $servicio->setRelation('cajaNapPuertoActivo', null);

        return $servicio;
    }

    private function servicioAntena(): Servicio
    {
        $servicio = new Servicio;
        $servicio->servicio_id = 22;
        $servicio->ip = '10.20.0.8';
        $servicio->mac_address = '11:22:33:44:55:66';
        $servicio->usuario_pppoe = 'antena22';
        $servicio->cpe_antena = 'ubiquiti';

        $tech = new TipoTecnologia;
        $tech->descripcion = 'Wireless antena';
        $plan = new Plan;
        $plan->nombre = 'Radio 10';
        $plan->setRelation('tipoTecnologia', $tech);

        $router = new Router;
        $router->router_id = 8;
        $router->setRelation('nodo', null);

        $pool = new RouterIpPool;
        $pool->router_id = 8;
        $pool->setRelation('router', $router);
        $pool->setRelation('olt', null);

        $servicio->setRelation('plan', $plan);
        $servicio->setRelation('pool', $pool);
        $servicio->setRelation('cliente', null);
        $servicio->setRelation('cajaNapPuertoActivo', null);

        return $servicio;
    }
}
