<?php

namespace Tests\Unit\Support;

use App\Models\HotspotPerfil;
use App\Support\HotspotPerfilesMikrotik;
use Tests\TestCase;

class HotspotPerfilesMikrotikTest extends TestCase
{
    public function test_vacios_se_envian_como_sin_limite_y_usuarios_a_la_vez_solo_si_hay_valor(): void
    {
        $this->assertSame(
            ['rate-limit' => '', 'idle-timeout' => 'none', 'session-timeout' => '0s'],
            HotspotPerfilesMikrotik::atributos(new HotspotPerfil(['nombre' => 'Libre']))
        );
        $this->assertSame(
            ['rate-limit' => '5M/10M', 'idle-timeout' => '5m', 'session-timeout' => '1d', 'shared-users' => '2'],
            HotspotPerfilesMikrotik::atributos(new HotspotPerfil([
                'nombre' => 'x', 'rate_limit' => '5M/10M', 'idle_timeout' => '5m', 'session_timeout' => '1d', 'shared_users' => '2',
            ]))
        );
    }

    public function test_plan_crea_actualiza_y_deja_igual(): void
    {
        $perfiles = [
            new HotspotPerfil(['nombre' => 'Nuevo', 'rate_limit' => '5M/10M']),
            new HotspotPerfil(['nombre' => 'Viejo', 'rate_limit' => '5M/10M', 'idle_timeout' => '90m']),
            new HotspotPerfil(['nombre' => 'Igual', 'rate_limit' => '2M/4M', 'session_timeout' => '1d']),
            new HotspotPerfil(['nombre' => 'Sin tope']),
        ];
        $router = [
            ['.id' => '*0', 'name' => 'default', 'shared-users' => '1', 'idle-timeout' => 'none', 'session-timeout' => '0s'],
            ['.id' => '*1', 'name' => 'Viejo', 'rate-limit' => '2M/2M', 'idle-timeout' => '1h30m', 'session-timeout' => '0s'],
            ['.id' => '*2', 'name' => 'Igual', 'rate-limit' => '2M/4M', 'idle-timeout' => 'none', 'session-timeout' => '1d'],
            ['.id' => '*3', 'name' => 'Sin tope', 'rate-limit' => '1M/1M', 'idle-timeout' => '5m', 'session-timeout' => '0s'],
            ['.id' => '*4', 'name' => 'renombrado-viejo'],
        ];

        $plan = HotspotPerfilesMikrotik::plan($perfiles, $router);
        $porNombre = collect($plan['perfiles'])->keyBy('nombre');

        $this->assertSame('crear', $porNombre['Nuevo']['accion']);
        $this->assertSame(['rate-limit' => '5M/10M'], $porNombre['Nuevo']['atributos']);

        $this->assertSame('actualizar', $porNombre['Viejo']['accion']);
        $this->assertSame('*1', $porNombre['Viejo']['id']);
        $this->assertSame(['rate-limit'], $porNombre['Viejo']['cambios']);

        $this->assertSame('igual', $porNombre['Igual']['accion']);

        $this->assertSame('actualizar', $porNombre['Sin tope']['accion']);
        $this->assertSame(['rate-limit' => '', 'idle-timeout' => 'none'], $porNombre['Sin tope']['atributos']);

        $this->assertSame(['renombrado-viejo'], $plan['sobrantes']);
    }
}
