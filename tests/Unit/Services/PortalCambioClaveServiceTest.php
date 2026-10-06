<?php

namespace Tests\Unit\Services;

use App\Services\Portal\PortalAccionTicketService;
use App\Services\Portal\PortalCambioClaveService;
use Tests\TestCase;

class PortalCambioClaveServiceTest extends TestCase
{
    public function test_constantes_y_asunto(): void
    {
        $this->assertSame('Cambio de Contraseña App', PortalCambioClaveService::ASUNTO_NOMBRE);
        $this->assertSame(PortalAccionTicketService::ASUNTO_CLAVE_APP, PortalCambioClaveService::ASUNTO_NOMBRE);
        $this->assertSame('Cambio de Contraseña en Router Wifi', PortalAccionTicketService::ASUNTO_CLAVE_WIFI);
        $this->assertSame(6, PortalCambioClaveService::CLAVE_MIN);
        $this->assertSame(64, PortalCambioClaveService::CLAVE_MAX);
    }
}
