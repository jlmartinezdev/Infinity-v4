<?php

namespace Tests\Unit\Support;

use App\Models\Olt;
use App\Support\OltN6DesvioConfig;
use PHPUnit\Framework\TestCase;

class OltN6DesvioConfigTest extends TestCase
{
    public function test_reconoce_olt_ua(): void
    {
        $olt = new Olt(['codigo' => 'OLT-UA', 'ip' => '172.16.30.200']);
        $this->assertTrue(OltN6DesvioConfig::aplica($olt));
    }

    public function test_ignora_otro_olt(): void
    {
        $olt = new Olt(['codigo' => 'OLT-YAGUARETECORA', 'ip' => '172.16.10.245']);
        $this->assertFalse(OltN6DesvioConfig::aplica($olt));
    }

    public function test_desvio_es_host_32_para_no_pisar_lan_n2(): void
    {
        $this->assertSame('172.16.30.254/32', OltN6DesvioConfig::IP_DESVIO);
        $this->assertSame('bridge_lan', OltN6DesvioConfig::IFACE_LAN_N2);
        $this->assertSame('bridge-lan', OltN6DesvioConfig::BRIDGE_LAN_N6);
    }
}
