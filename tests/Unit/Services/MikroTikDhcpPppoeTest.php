<?php

namespace Tests\Unit\Services;

use App\Services\MikroTikService;
use Tests\TestCase;

class MikroTikDhcpPppoeTest extends TestCase
{
    public function test_mapea_leases_dhcp_bound_y_estaticos(): void
    {
        $leases = MikroTikService::mapearDhcpLeases([
            [
                'address' => '10.0.0.2',
                'mac-address' => 'AA:BB:CC:DD:EE:FF',
                'host-name' => 'onu1',
                'status' => 'waiting',
                'server' => 'dhcp1',
                'expires-after' => '1m',
                'dynamic' => 'true',
            ],
            [
                'address' => '10.0.0.1',
                'mac-address' => '11:22:33:44:55:66',
                'host-name' => 'cpe',
                'status' => 'bound',
                'server' => 'dhcp1',
                'expires-after' => '10m',
                'dynamic' => 'false',
            ],
        ]);

        $this->assertCount(2, $leases);
        $this->assertTrue($leases[0]['active']);
        $this->assertTrue($leases[0]['static']);
        $this->assertSame('10.0.0.1', $leases[0]['address']);
        $this->assertFalse($leases[1]['active']);
    }

    public function test_mapea_sesiones_pppoe_y_omite_otros_servicios(): void
    {
        $sesiones = MikroTikService::mapearPppoeSesiones([
            ['name' => 'user-b', 'address' => '10.1.1.2/32', 'uptime' => '1h', 'caller-id' => 'AA:BB', 'service' => 'pppoe'],
            ['name' => 'l2tp-x', 'address' => '10.1.1.9', 'uptime' => '2h', 'service' => 'l2tp'],
            ['name' => 'user-a', 'address' => '10.1.1.1', 'uptime' => '3h', 'caller-id' => '', 'service' => ''],
        ]);

        $this->assertCount(2, $sesiones);
        $this->assertSame('user-a', $sesiones[0]['name']);
        $this->assertSame('10.1.1.2', $sesiones[1]['address']);
    }
}
