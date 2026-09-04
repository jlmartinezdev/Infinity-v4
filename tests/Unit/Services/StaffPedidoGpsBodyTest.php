<?php

namespace Tests\Unit\Services;

use App\Models\Pedido;
use App\Services\Staff\StaffPedidoInstalacionService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffPedidoGpsBodyTest extends TestCase
{
    private function service(): StaffPedidoInstalacionService
    {
        return new StaffPedidoInstalacionService;
    }

    public function test_sin_gps_devuelve_null(): void
    {
        $this->assertNull($this->service()->resolverGpsBody([
            'estado' => 'en_camino',
            'ubicacion' => '-25.2867123, -57.6470456',
        ]));
    }

    public function test_acepta_lat_lng_como_manda_la_app(): void
    {
        $gps = $this->service()->resolverGpsBody([
            'lat' => -25.2867123,
            'lng' => -57.6470456,
            'maps_gps' => '-25.2867123, -57.6470456',
            'ubicacion' => '-25.2867123, -57.6470456',
        ]);

        $this->assertNotNull($gps);
        $this->assertEqualsWithDelta(-25.2867123, $gps['lat'], 1e-6);
        $this->assertEqualsWithDelta(-57.6470456, $gps['lon'], 1e-6);
        $this->assertSame('-25.2867123, -57.6470456', $gps['maps_gps']);
    }

    public function test_acepta_lon_y_solo_maps_gps(): void
    {
        $conLon = $this->service()->resolverGpsBody([
            'lat' => -25.28,
            'lon' => -57.64,
        ]);
        $this->assertNotNull($conLon);
        $this->assertEqualsWithDelta(-25.28, $conLon['lat'], 1e-6);
        $this->assertEqualsWithDelta(-57.64, $conLon['lon'], 1e-6);
        $this->assertSame('-25.28, -57.64', $conLon['maps_gps']);

        $soloMaps = $this->service()->resolverGpsBody([
            'maps_gps' => '-25.28, -57.64',
        ]);
        $this->assertNotNull($soloMaps);
        $this->assertEqualsWithDelta(-25.28, $soloMaps['lat'], 1e-6);
        $this->assertEqualsWithDelta(-57.64, $soloMaps['lon'], 1e-6);
    }

    public function test_gps_incompleto_falla(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->resolverGpsBody(['lat' => -25.28]);
    }

    public function test_gps_fuera_de_rango_falla(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->resolverGpsBody(['lat' => 91, 'lng' => -57.64]);
    }

    public function test_acepta_alias_longitud(): void
    {
        $gps = $this->service()->resolverGpsBody([
            'lat' => -25.28,
            'longitud' => -57.64,
        ]);

        $this->assertNotNull($gps);
        $this->assertEqualsWithDelta(-57.64, $gps['lon'], 1e-6);
    }

    public function test_aplicar_gps_no_pisa_ubicacion(): void
    {
        $pedido = new Pedido([
            'ubicacion' => 'TATUKUA',
            'maps_gps' => '-26.45, -56.10',
            'lat' => -26.45,
            'lon' => -56.10,
        ]);

        $this->service()->aplicarGps($pedido, [
            'lat' => -25.2867,
            'lon' => -57.647,
            'maps_gps' => '-25.2867, -57.647',
        ]);

        $this->assertSame('TATUKUA', $pedido->ubicacion);
        $this->assertSame(-25.2867, $pedido->lat);
        $this->assertSame(-57.647, $pedido->lon);
        $this->assertSame('-25.2867, -57.647', $pedido->maps_gps);
    }
}
