<?php

namespace Tests\Unit\Services;

use App\Services\HotspotPortalDeployService;
use App\Services\RadiusHotspotSyncService;
use Tests\TestCase;

class HotspotVentaVoucherTest extends TestCase
{
    public function test_el_voucher_lleva_clave_cuota_y_un_equipo(): void
    {
        $vence = new \DateTimeImmutable('2026-11-18 15:00:00');
        $atributos = RadiusHotspotSyncService::atributosVoucher('4821', 2, '5M/10M', $vence);

        $check = $atributos['check'];
        $this->assertSame(['Cleartext-Password', ':=', '4821'], $check[0]);
        $this->assertSame(['Simultaneous-Use', ':=', '1'], $check[1]);
        $this->assertSame(['Max-Data', ':=', (string) (2 * RadiusHotspotSyncService::BYTES_POR_GB)], $check[2]);
        $this->assertSame('Expiration', $check[3][0]);
        $this->assertSame('Nov 18 2026 15:00:00', $check[3][2]);

        $reply = $atributos['reply'];
        $this->assertSame(['Mikrotik-Rate-Limit', '=', '5M/10M'], $reply[1]);
        $this->assertSame('Mikrotik-Total-Limit', $reply[2][0]);
    }

    public function test_el_jardin_incluye_bancard_y_omite_localhost(): void
    {
        config([
            'hotspot.venta_api_url' => 'http://127.0.0.1/infinity-v4/public',
            'hotspot.venta_jardin_hosts' => ['comercios.bancard.com.py'],
        ]);

        $hosts = HotspotPortalDeployService::hostsJardinVenta();

        $this->assertContains('comercios.bancard.com.py', $hosts);
        $this->assertNotContains('127.0.0.1', $hosts);

        config(['hotspot.venta_api_url' => 'https://clientes.interplus.com.py']);
        $this->assertContains('clientes.interplus.com.py', HotspotPortalDeployService::hostsJardinVenta());
    }
}
