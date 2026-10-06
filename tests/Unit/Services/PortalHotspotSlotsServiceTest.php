<?php

namespace Tests\Unit\Services;

use App\Models\ServicioHotspot;
use Tests\TestCase;

class PortalHotspotSlotsServiceTest extends TestCase
{
    public function test_slots_libres_y_username(): void
    {
        $this->assertSame([1, 2, 3], ServicioHotspot::slotsDisponibles());
        $this->assertSame([2, 3], ServicioHotspot::slotsLibresDe([1]));
        $this->assertSame([3], ServicioHotspot::slotsLibresDe([1, 2]));
        $this->assertSame([], ServicioHotspot::slotsLibresDe([1, 2, 3]));
        $this->assertSame(4, ServicioHotspot::PIN_DIGITOS);
        $this->assertSame(3, ServicioHotspot::MAX_POR_CLIENTE);
    }
}
