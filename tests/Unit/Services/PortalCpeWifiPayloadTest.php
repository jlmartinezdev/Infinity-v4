<?php

namespace Tests\Unit\Services;

use App\Models\Cliente;
use App\Services\GenieAcs\GenieAcsService;
use App\Services\Portal\PortalCpeWifiService;
use Tests\TestCase;

class PortalCpeWifiPayloadTest extends TestCase
{
    public function test_map_wifi_no_expone_paths_ni_clave(): void
    {
        $mapped = PortalCpeWifiService::mapWifiForPortal([
            [
                'id' => 'ap-1',
                'ssid' => 'Interplus-5G',
                'enabled' => true,
                'band' => '5GHz',
                'security' => 'WPA2-Personal',
                'passphrase_path' => 'Device.WiFi.AccessPoint.1.Security.KeyPassphrase',
                'mode_path' => 'Device.WiFi.AccessPoint.1.Security.ModeEnabled',
            ],
            [
                'id' => '',
                'ssid' => 'omitir',
            ],
        ]);

        $this->assertCount(1, $mapped);
        $this->assertSame([
            'id' => 'ap-1',
            'ssid' => 'Interplus-5G',
            'enabled' => true,
            'band' => '5GHz',
        ], $mapped[0]);
        $this->assertArrayNotHasKey('passphrase_path', $mapped[0]);
        $this->assertArrayNotHasKey('security', $mapped[0]);
    }

    public function test_hint_no_acs_manda_faq_local(): void
    {
        $this->assertStringContainsString(
            '192.168.1.1',
            PortalCpeWifiService::hintPara(PortalCpeWifiService::REASON_NO_ACS)
        );
    }

    public function test_password_corta_no_pega_al_acs(): void
    {
        $acs = $this->createMock(GenieAcsService::class);
        $acs->expects($this->never())->method('setPassword');
        $acs->expects($this->never())->method('setWifi');
        $acs->expects($this->never())->method('resumen');

        $huawei = $this->createMock(\App\Services\Huawei\HuaweiOnuService::class);
        $huawei->expects($this->never())->method('cambiarWifi');

        $cliente = new Cliente;
        $cliente->cliente_id = 1;

        $result = (new PortalCpeWifiService($acs, $huawei))->cambiar($cliente, 'corta');

        $this->assertFalse($result['success']);
        $this->assertSame(422, $result['http']);
        $this->assertSame('invalid_password', $result['data']['reason']);
    }

    public function test_ssid_invalido_no_pega_al_acs(): void
    {
        $acs = $this->createMock(GenieAcsService::class);
        $acs->expects($this->never())->method('setWifi');

        $huawei = $this->createMock(\App\Services\Huawei\HuaweiOnuService::class);
        $huawei->expects($this->never())->method('cambiarWifi');

        $cliente = new Cliente;
        $cliente->cliente_id = 1;

        $largo = str_repeat('a', 33);
        $result = (new PortalCpeWifiService($acs, $huawei))->cambiar($cliente, null, null, null, $largo);

        $this->assertFalse($result['success']);
        $this->assertSame('invalid_ssid', $result['data']['reason']);
        $this->assertNotNull(PortalCpeWifiService::validarSsid($largo));
        $this->assertNull(PortalCpeWifiService::validarSsid('Casa Miguel'));
    }

    public function test_map_huawei_ssids(): void
    {
        $mapped = PortalCpeWifiService::mapHuaweiSsidsForPortal([
            'Familia Gonzalez-2.4',
            'Familia Gonzalez-2.4_5G',
            'Sofi-2.4G',
            'Sofi-5G-',
            'WILL/DYN-5G',
        ]);

        $this->assertCount(5, $mapped);
        $this->assertSame('2.4GHz', $mapped[0]['band']);
        $this->assertSame('5GHz', $mapped[1]['band']);
        $this->assertSame('2.4GHz', $mapped[2]['band']);
        $this->assertSame('5GHz', $mapped[3]['band']); // Sofi-5G- (guión final)
        $this->assertSame('5GHz', $mapped[4]['band']);
    }

    public function test_infer_band_from_ssid(): void
    {
        $this->assertSame('5GHz', PortalCpeWifiService::inferBandFromSsid('Sofi-5G-'));
        $this->assertSame('5GHz', PortalCpeWifiService::inferBandFromSsid('Flia Benitez-5G-'));
        $this->assertSame('2.4GHz', PortalCpeWifiService::inferBandFromSsid('Sofi-2.4G'));
        $this->assertSame('2.4GHz', PortalCpeWifiService::inferBandFromSsid('Casa'));
    }
}
