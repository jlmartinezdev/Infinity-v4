<?php

namespace Tests\Unit\Services;

use App\Models\PortalClienteAccion;
use App\Services\Portal\PortalClienteAccionService;
use Tests\TestCase;

class PortalClienteAccionServiceTest extends TestCase
{
    public function test_titulo_wifi_incluye_ssid_sin_exponer_clave(): void
    {
        $this->assertSame(
            'Cambió clave Wi‑Fi «Casa Miguel»',
            PortalClienteAccionService::tituloWifi(false, true, 'Casa Miguel')
        );
        $this->assertSame(
            'Cambió nombre Wi‑Fi «Casa»',
            PortalClienteAccionService::tituloWifi(true, false, 'Casa')
        );
        $this->assertSame(
            PortalClienteAccion::TIPO_WIFI_SSID_PASSWORD,
            PortalClienteAccionService::tipoWifi(true, true)
        );
        $this->assertSame(
            PortalClienteAccion::TIPO_WIFI_PASSWORD,
            PortalClienteAccionService::tipoWifi(false, true)
        );
    }
}
