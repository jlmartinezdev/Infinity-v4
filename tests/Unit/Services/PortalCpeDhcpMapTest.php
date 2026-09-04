<?php

namespace Tests\Unit\Services;

use App\Services\Portal\PortalCpeDhcpService;
use Tests\TestCase;

class PortalCpeDhcpMapTest extends TestCase
{
    public function test_hosts_tr069_del_panel_al_contrato_app(): void
    {
        $clients = PortalCpeDhcpService::mapToClients([
            [
                'ip' => '192.168.1.50',
                'mac' => 'AA:BB:CC:DD:EE:FF',
                'hostname' => 'android-phone',
                'rssi' => null,
                'downlink' => null,
                'source' => 'lan',
            ],
            [
                'ip' => '192.168.1.10',
                'mac' => '11:22:33:44:55:66',
                'hostname' => null,
                'source' => 'lan',
            ],
            [
                'ip' => null,
                'mac' => '00:11:22:33:44:55',
                'hostname' => 'solo-wifi',
                'source' => 'wifi',
            ],
        ]);

        $this->assertCount(2, $clients);
        $this->assertSame('android-phone', $clients[0]['hostname']);
        $this->assertSame('aa:bb:cc:dd:ee:ff', $clients[0]['mac']);
        $this->assertSame('192.168.1.50', $clients[0]['ip']);
        $this->assertNull($clients[0]['online']);
        $this->assertNull($clients[0]['lease_expires_at']);
        $this->assertArrayNotHasKey('rssi', $clients[0]);
        $this->assertArrayNotHasKey('source', $clients[0]);
        $this->assertSame('192.168.1.10', $clients[1]['ip']);
    }

    public function test_mac_normalizada_minusculas(): void
    {
        $this->assertSame('48:57:54:43:25:1e', PortalCpeDhcpService::normalizarMac('48575443251E'));
        $this->assertSame('aa:bb:cc:dd:ee:ff', PortalCpeDhcpService::normalizarMac('AA-BB-CC-DD-EE-FF'));
    }
}
