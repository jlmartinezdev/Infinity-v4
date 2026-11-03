<?php

namespace Tests\Unit\Services;

use App\Services\N7PoolIpGuard;
use Tests\TestCase;

class N7PoolIpGuardTest extends TestCase
{
    public function test_el_companero_de_cada_n7_es_el_otro(): void
    {
        $this->assertSame('MK-N7-02', N7PoolIpGuard::nombreCompanero('MK-N7-01'));
        $this->assertSame('MK-N7-01', N7PoolIpGuard::nombreCompanero('MK-N7-02'));
        $this->assertNull(N7PoolIpGuard::nombreCompanero('MK-N2-BORDE'));
    }

    public function test_una_ip_de_dolores_cae_en_el_pool_de_n7_02_y_no_en_el_de_n7_01(): void
    {
        $this->assertTrue(N7PoolIpGuard::ipDentroDeRango('10.7.9.100', '10.7.9.0/24'));
        $this->assertFalse(N7PoolIpGuard::ipDentroDeRango('10.7.9.100', '10.7.8.1/24'));
        $this->assertTrue(N7PoolIpGuard::ipDentroDeRango('10.7.8.50', '10.7.8.1/24'));
        $this->assertFalse(N7PoolIpGuard::ipDentroDeRango('10.7.10.1', '10.7.9.0/24'));
    }
}
