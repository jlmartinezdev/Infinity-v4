<?php

namespace Tests\Unit\Support;

use App\Support\HotspotSesionOrigen;
use Tests\TestCase;

class HotspotSesionOrigenTest extends TestCase
{
    public function test_una_mac_sin_cuenta_no_es_un_locker(): void
    {
        $this->assertSame('sin-sesion', HotspotSesionOrigen::de('F6:AB:C8:90:99:85', false));
        $this->assertSame('sin-sesion', HotspotSesionOrigen::de('f6-ab-c8-90-99-85', false));
    }

    public function test_un_locker_gana_aunque_el_usuario_sea_una_mac(): void
    {
        $this->assertSame('locker', HotspotSesionOrigen::de('F6:AB:C8:90:99:85', true));
    }

    public function test_un_usuario_real_sin_locker_sigue_siendo_sin_locker(): void
    {
        $this->assertSame('sin-locker', HotspotSesionOrigen::de('1234567', false));
    }

    public function test_vacio_y_trial_se_conservan(): void
    {
        $this->assertSame('sin-usuario', HotspotSesionOrigen::de('', false));
        $this->assertSame('sin-usuario', HotspotSesionOrigen::de('   ', false));
        $this->assertSame('trial', HotspotSesionOrigen::de('T-trial-1', false));
        $this->assertSame('trial', HotspotSesionOrigen::de('T-C6:93:01:74:98:22', false));
    }
}
