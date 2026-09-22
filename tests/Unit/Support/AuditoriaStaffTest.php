<?php

namespace Tests\Unit\Support;

use App\Models\User;
use App\Support\AuditoriaStaff;
use Tests\TestCase;

class AuditoriaStaffTest extends TestCase
{
    public function test_omite_sin_actor_y_portal(): void
    {
        $this->assertFalse(AuditoriaStaff::debeRegistrar(null, 'created'));
        $this->assertFalse(AuditoriaStaff::debeRegistrar(new User(['cliente_id' => 9]), 'updated', ['nombre']));
    }

    public function test_registra_alta_de_staff(): void
    {
        $staff = new User(['cliente_id' => null]);

        $this->assertTrue($staff->esStaff());
        $this->assertTrue(AuditoriaStaff::debeRegistrar($staff, 'created'));
        $this->assertTrue(AuditoriaStaff::debeRegistrar($staff, 'updated', ['estado']));
        $this->assertTrue(AuditoriaStaff::debeRegistrar($staff, 'deleted'));
    }

    public function test_omite_solo_telemetria_aunque_sea_staff(): void
    {
        $staff = new User(['cliente_id' => null]);

        $this->assertFalse(AuditoriaStaff::debeRegistrar($staff, 'updated', ['ultimo_acceso_at', 'updated_at']));
        $this->assertFalse(AuditoriaStaff::debeRegistrar($staff, 'updated', ['last_synced', 'ros_id']));
        $this->assertFalse(AuditoriaStaff::debeRegistrar($staff, 'updated', ['updated_at']));
    }

    public function test_actor_staff_elige_el_usuario_de_mesa(): void
    {
        $portal = new User(['cliente_id' => 4, 'name' => 'Portal']);
        $staff = new User(['cliente_id' => null, 'name' => 'Caja']);

        $this->assertNull(AuditoriaStaff::actorStaff($portal, null));
        $this->assertSame($staff, AuditoriaStaff::actorStaff($portal, $staff));
    }
}
