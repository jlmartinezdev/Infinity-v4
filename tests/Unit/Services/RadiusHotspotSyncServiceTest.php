<?php

namespace Tests\Unit\Services;

use App\Models\HotspotPerfil;
use App\Models\Servicio;
use App\Models\ServicioHotspot;
use App\Services\RadiusHotspotSyncService;
use Tests\TestCase;

class RadiusHotspotSyncServiceTest extends TestCase
{
    public function test_timeout_a_segundos(): void
    {
        $this->assertSame(90, RadiusHotspotSyncService::timeoutASegundos('90'));
        $this->assertSame(3661, RadiusHotspotSyncService::timeoutASegundos('1:01:01'));
        $this->assertSame(3600, RadiusHotspotSyncService::timeoutASegundos('1h'));
        $this->assertSame(1800, RadiusHotspotSyncService::timeoutASegundos('30m'));
        $this->assertSame(5400, RadiusHotspotSyncService::timeoutASegundos('1h30m'));
        $this->assertNull(RadiusHotspotSyncService::timeoutASegundos('none'));
        $this->assertNull(RadiusHotspotSyncService::timeoutASegundos(''));
    }

    public function test_atributos_de_usuario_activo(): void
    {
        $perfil = new HotspotPerfil([
            'rate_limit' => '10M/10M',
            'shared_users' => '2',
            'idle_timeout' => '30m',
            'session_timeout' => '1h',
        ]);
        $servicio = new Servicio(['estado' => Servicio::ESTADO_ACTIVO]);
        $hotspot = new ServicioHotspot(['username' => 'cli01', 'password' => 'clave']);
        $hotspot->setRelation('hotspotPerfil', $perfil);
        $hotspot->setRelation('servicio', $servicio);

        $radius = new RadiusHotspotSyncService;
        $check = $radius->atributosCheck($hotspot);
        $reply = $radius->atributosReply($hotspot);

        $this->assertContains(['Cleartext-Password', ':=', 'clave'], $check);
        $this->assertContains(['Simultaneous-Use', ':=', '2'], $check);
        $this->assertFalse(collect($check)->contains(fn ($fila) => $fila[0] === 'Auth-Type'));
        $this->assertContains(['Mikrotik-Rate-Limit', '=', '10M/10M'], $reply);
        $this->assertContains(['Acct-Interim-Interval', '=', '60'], $reply);
        $this->assertContains(['Idle-Timeout', '=', '1800'], $reply);
        $this->assertContains(['Session-Timeout', '=', '3600'], $reply);
    }

    public function test_perfil_con_cuota_escribe_max_data_y_limite_mikrotik(): void
    {
        $perfil = new HotspotPerfil([
            'cuota_gb' => 5,
            'rate_limit' => '10M/10M',
        ]);
        $servicio = new Servicio(['estado' => Servicio::ESTADO_ACTIVO]);
        $hotspot = new ServicioHotspot(['username' => 'cli01', 'password' => 'clave']);
        $hotspot->setRelation('hotspotPerfil', $perfil);
        $hotspot->setRelation('servicio', $servicio);

        $radius = new RadiusHotspotSyncService;
        $check = $radius->atributosCheck($hotspot);
        $reply = $radius->atributosReply($hotspot);

        $this->assertContains(['Max-Data', ':=', (string) (5 * RadiusHotspotSyncService::BYTES_POR_GB)], $check);
        $this->assertFalse(collect($check)->contains(fn ($fila) => $fila[0] === 'Auth-Type'));
        $this->assertContains(['Mikrotik-Total-Limit', '=', '1073741824'], $reply);
        $this->assertContains(['Mikrotik-Total-Limit-Gigawords', '=', '1'], $reply);
    }

    public function test_cuota_agotada_rechaza_y_no_manda_limite_cero(): void
    {
        $perfil = new HotspotPerfil(['cuota_gb' => 1]);
        $servicio = new Servicio(['estado' => Servicio::ESTADO_ACTIVO]);
        $hotspot = new ServicioHotspot(['username' => 'cli01', 'password' => 'clave']);
        $hotspot->setRelation('hotspotPerfil', $perfil);
        $hotspot->setRelation('servicio', $servicio);

        $usados = RadiusHotspotSyncService::BYTES_POR_GB;
        $radius = new RadiusHotspotSyncService;
        $check = $radius->atributosCheck($hotspot, $usados);
        $reply = $radius->atributosReply($hotspot, $usados);

        $this->assertContains(['Auth-Type', ':=', 'Reject'], $check);
        $this->assertFalse(collect($reply)->contains(fn ($fila) => $fila[0] === 'Mikrotik-Total-Limit'));
    }

    public function test_restante_resta_lo_ya_consumido(): void
    {
        $perfil = new HotspotPerfil(['cuota_gb' => 2]);
        $hotspot = new ServicioHotspot(['username' => 'cli01', 'password' => 'clave']);
        $hotspot->setRelation('hotspotPerfil', $perfil);
        $hotspot->setRelation('servicio', new Servicio(['estado' => Servicio::ESTADO_ACTIVO]));

        $usados = RadiusHotspotSyncService::BYTES_POR_GB;
        $reply = (new RadiusHotspotSyncService)->atributosReply($hotspot, $usados);

        $this->assertContains(['Mikrotik-Total-Limit', '=', (string) RadiusHotspotSyncService::BYTES_POR_GB], $reply);
        $this->assertContains(['Mikrotik-Total-Limit-Gigawords', '=', '0'], $reply);
    }

    public function test_limite_volumen_mikrotik(): void
    {
        $this->assertSame(
            [
                ['Mikrotik-Total-Limit', '=', '1073741824'],
                ['Mikrotik-Total-Limit-Gigawords', '=', '0'],
            ],
            RadiusHotspotSyncService::mikrotikAtributosVolumen(RadiusHotspotSyncService::BYTES_POR_GB)
        );
        $this->assertNull(RadiusHotspotSyncService::cuotaGbABytes(null));
        $this->assertSame('5 GB', RadiusHotspotSyncService::bytesAEtiqueta(5 * RadiusHotspotSyncService::BYTES_POR_GB));
        $this->assertSame(1, RadiusHotspotSyncService::bytesAMb(RadiusHotspotSyncService::BYTES_POR_MB));
        $this->assertSame('1 GB / 10 GB', RadiusHotspotSyncService::consumoEtiqueta(RadiusHotspotSyncService::BYTES_POR_GB, 10));
        $this->assertSame('0 MB · ilimitado', RadiusHotspotSyncService::consumoEtiqueta(0, null));
        $vista = RadiusHotspotSyncService::consumoVista(0, 10);
        $this->assertTrue($vista['tiene_cuota']);
        $this->assertSame(0.0, $vista['porcentaje']);
        $this->assertSame('0', $vista['centro_num']);
        $this->assertSame('MB', $vista['centro_unit']);
        $mitad = RadiusHotspotSyncService::consumoVista(5 * RadiusHotspotSyncService::BYTES_POR_GB, 10);
        $this->assertSame(50.0, $mitad['porcentaje']);
        $this->assertSame('5', $mitad['centro_num']);
        $this->assertSame('GB', $mitad['centro_unit']);
        $this->assertFalse(RadiusHotspotSyncService::consumoVista(0, null)['tiene_cuota']);
        $this->assertSame(
            ['11000000' => 1164432309],
            RadiusHotspotSyncService::combinarConsumoSesionViva(
                ['11000000' => ['cerradas' => 0, 'abiertas' => 0]],
                ['11000000' => 1164432309]
            )
        );
        $this->assertSame(
            ['u1' => 150],
            RadiusHotspotSyncService::combinarConsumoSesionViva(
                ['u1' => ['cerradas' => 100, 'abiertas' => 40]],
                ['u1' => 50]
            )
        );
    }

    public function test_sync_falla_rapido_si_radius_no_escucha(): void
    {
        config([
            'radius.enabled' => true,
            'database.connections.radius.host' => '127.0.0.1',
            'database.connections.radius.port' => 1,
        ]);

        $inicio = microtime(true);
        $result = (new RadiusHotspotSyncService)->sync(new ServicioHotspot([
            'username' => 'x',
            'password' => 'y',
        ]));

        $this->assertFalse($result['success']);
        $this->assertLessThan(3, microtime(true) - $inicio);
    }

    public function test_servicio_suspendido_queda_en_reject(): void
    {
        $servicio = new Servicio(['estado' => Servicio::ESTADO_SUSPENDIDO]);
        $hotspot = new ServicioHotspot(['username' => 'cli01', 'password' => 'clave']);
        $hotspot->setRelation('servicio', $servicio);
        $hotspot->setRelation('hotspotPerfil', null);

        $check = (new RadiusHotspotSyncService)->atributosCheck($hotspot);

        $this->assertContains(['Auth-Type', ':=', 'Reject'], $check);
    }
}
