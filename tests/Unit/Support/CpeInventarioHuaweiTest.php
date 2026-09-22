<?php

namespace Tests\Unit\Support;

use App\Models\Servicio;
use App\Support\CpeInventario;
use Tests\TestCase;

class CpeInventarioHuaweiTest extends TestCase
{
    public function test_clave_huawei_detecta_catalogo_y_nombre(): void
    {
        $this->assertTrue(CpeInventario::claveEsHuawei('huawei'));
        $this->assertTrue(CpeInventario::claveEsHuawei('huawei_eg8145v5'));
        $this->assertFalse(CpeInventario::claveEsHuawei('vsol'));
        $this->assertFalse(CpeInventario::claveEsHuawei(null));
    }

    public function test_huawei_usa_ssh_y_no_acs(): void
    {
        $servicio = new Servicio([
            'cpe_onu' => 'huawei',
            'cpe_acceso' => 'acs',
            'tr069_serial' => 'ABCD1234',
        ]);

        $this->assertTrue(CpeInventario::esHuaweiOnu($servicio));
        $this->assertTrue(CpeInventario::usaSshCpe($servicio));
        $this->assertFalse(CpeInventario::usaAcs($servicio));
    }

    public function test_acs_sigue_activo_en_onu_no_huawei(): void
    {
        $servicio = new Servicio([
            'cpe_onu' => 'vsol',
            'cpe_acceso' => 'acs',
            'tr069_serial' => 'ABCD1234',
        ]);

        $this->assertFalse(CpeInventario::esHuaweiOnu($servicio));
        $this->assertTrue(CpeInventario::usaAcs($servicio));
        $this->assertFalse(CpeInventario::usaSshCpe($servicio));
    }

    public function test_resumen_huawei_muestra_ssh_aunque_el_acceso_guarde_acs(): void
    {
        $servicio = new Servicio([
            'cpe_onu' => 'huawei',
            'cpe_acceso' => 'acs',
        ]);

        $this->assertStringContainsString('SSH', (string) CpeInventario::resumen($servicio));
        $this->assertStringNotContainsString('ACS', (string) CpeInventario::resumen($servicio));
    }
}
