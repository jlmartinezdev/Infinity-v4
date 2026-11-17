<?php

namespace Tests\Unit\Support;

use App\Models\HotspotPerfil;
use App\Support\HotspotPerfilFormulario;
use Tests\TestCase;

class HotspotPerfilFormularioTest extends TestCase
{
    public function test_campos_desde_un_perfil_simple(): void
    {
        $perfil = new HotspotPerfil([
            'nombre' => '10 GB',
            'rate_limit' => '5M/10M',
            'cuota_gb' => 10,
            'shared_users' => '2',
            'idle_timeout' => '5m',
            'session_timeout' => '1d',
        ]);

        $campos = HotspotPerfilFormulario::campos($perfil);

        $this->assertSame('10', $campos['bajada_mbps']);
        $this->assertSame('5', $campos['subida_mbps']);
        $this->assertSame('', $campos['rate_limit_avanzado']);
        $this->assertSame('10', $campos['cuota_gb']);
        $this->assertSame('2', $campos['shared_users']);
        $this->assertSame(['5', 'm'], [$campos['inactividad_valor'], $campos['inactividad_unidad']]);
        $this->assertSame(['1', 'd'], [$campos['sesion_valor'], $campos['sesion_unidad']]);
        $this->assertSame([], $campos['invalidos']);
    }

    public function test_rate_limit_con_rafagas_va_a_avanzado_y_lo_invalido_se_avisa(): void
    {
        $campos = HotspotPerfilFormulario::campos(new HotspotPerfil([
            'nombre' => 'raro',
            'rate_limit' => '2M/10M 4M/20M 1M/5M 8/8',
            'idle_timeout' => '1x',
            'session_timeout' => '90m',
        ]));

        $this->assertSame('', $campos['bajada_mbps']);
        $this->assertSame('2M/10M 4M/20M 1M/5M 8/8', $campos['rate_limit_avanzado']);
        $this->assertSame(['inactividad' => '1x'], $campos['invalidos']);
        $this->assertSame(['90', 'm'], [$campos['sesion_valor'], $campos['sesion_unidad']]);

        $malo = HotspotPerfilFormulario::campos(new HotspotPerfil(['nombre' => 'x', 'rate_limit' => 'rapido']));
        $this->assertSame(['velocidad' => 'rapido'], $malo['invalidos']);
        $this->assertSame('', $malo['rate_limit_avanzado']);
    }

    public function test_atributos_arman_el_formato_mikrotik(): void
    {
        $atributos = HotspotPerfilFormulario::atributos([
            'nombre' => '  Plaza  ',
            'bajada_mbps' => '10',
            'subida_mbps' => '1.5',
            'cuota_gb' => '5',
            'shared_users' => '2',
            'inactividad_valor' => '10',
            'inactividad_unidad' => 'm',
            'sesion_valor' => '2',
            'sesion_unidad' => 'h',
        ]);

        $this->assertSame([
            'nombre' => 'Plaza',
            'rate_limit' => '1500k/10M',
            'cuota_gb' => 5,
            'shared_users' => '2',
            'idle_timeout' => '10m',
            'session_timeout' => '2h',
        ], $atributos);
    }

    public function test_vacios_quedan_sin_limite_y_avanzado_manda(): void
    {
        $vacio = HotspotPerfilFormulario::atributos(['nombre' => 'Libre', 'bajada_mbps' => '', 'subida_mbps' => '', 'inactividad_valor' => '']);
        $this->assertNull($vacio['rate_limit']);
        $this->assertNull($vacio['cuota_gb']);
        $this->assertNull($vacio['shared_users']);
        $this->assertNull($vacio['idle_timeout']);

        $avanzado = HotspotPerfilFormulario::atributos(['nombre' => 'x', 'rate_limit_avanzado' => '2M/10M 4M/20M']);
        $this->assertSame('2M/10M 4M/20M', $avanzado['rate_limit']);
    }

    public function test_formato_avanzado_valido(): void
    {
        $this->assertMatchesRegularExpression(HotspotPerfilFormulario::REGEX_RATE_LIMIT, '10M');
        $this->assertMatchesRegularExpression(HotspotPerfilFormulario::REGEX_RATE_LIMIT, '2M/10M 4M/20M 1M/5M 8/8 8 512k/1M');
        $this->assertDoesNotMatchRegularExpression(HotspotPerfilFormulario::REGEX_RATE_LIMIT, 'rapido');
        $this->assertDoesNotMatchRegularExpression(HotspotPerfilFormulario::REGEX_RATE_LIMIT, '10M/');
    }
}
