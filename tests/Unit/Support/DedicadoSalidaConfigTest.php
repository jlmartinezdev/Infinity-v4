<?php

namespace Tests\Unit\Support;

use App\Support\DedicadoSalidaConfig;
use Tests\TestCase;

class DedicadoSalidaConfigTest extends TestCase
{
    public function test_modos_y_comentarios_del_dedicado(): void
    {
        $this->assertSame('tigo', DedicadoSalidaConfig::MODO_TIGO);
        $this->assertSame('ufinet', DedicadoSalidaConfig::MODO_UFINET);
        $this->assertSame('10.200.3.4', DedicadoSalidaConfig::PRIVADA);
        $this->assertSame('200.26.179.93', DedicadoSalidaConfig::PUBLICA);
        $this->assertSame('to_tigo', DedicadoSalidaConfig::TABLA_TIGO);
        $this->assertSame('Dedicado N1 1:1 Tigo', DedicadoSalidaConfig::RULE_COMMENT);
    }

    public function test_perfil_server_usa_la_94(): void
    {
        $server = \App\Support\Nat11SalidaConfig::perfil(\App\Support\Nat11SalidaConfig::SERVER);

        $this->assertSame('10.200.1.2', $server['privada']);
        $this->assertSame('200.26.179.94', $server['publica']);
        $this->assertSame('NAT 1:1 Server - SALIDA (OPTIMIZADO)', $server['nat_salida']);
        $this->assertSame('NAT 1:1 Server - ENTRADA (OPTIMIZADO)', $server['nat_entrada']);
        $this->assertSame('Server 1:1 Ufinet', $server['rule_ufinet']);
        $this->assertSame('186.33.34.14', $server['publica_ufinet']);
        $this->assertSame('NAT 1:1 Server Ufinet - SALIDA', $server['nat_salida_ufinet']);
        $this->assertNotEmpty($server['lan']);
    }
}
