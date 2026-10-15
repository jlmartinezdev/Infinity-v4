<?php

namespace Tests\Unit\Services;

use App\Models\TvCuenta;
use App\Services\WhatsApp\WhatsAppTvCuentaInboundService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class WhatsAppTvCuentaInboundServiceTest extends TestCase
{
    public function test_parsea_lumix_con_parentesis(): void
    {
        $parsed = WhatsAppTvCuentaInboundService::parsear('(Lumix: cuenta@tv.es, xxxxx)');

        $this->assertSame(TvCuenta::APP_LUMIX, $parsed['aplicacion']);
        $this->assertSame('cuenta@tv.es', $parsed['usuario_app']);
        $this->assertSame('xxxxx', $parsed['password']);
    }

    public function test_parsea_suffixed_sin_parentesis(): void
    {
        $parsed = WhatsAppTvCuentaInboundService::parsear('Suffixed: user@tv.com, clave123');

        $this->assertSame(TvCuenta::APP_SUFFIXED, $parsed['aplicacion']);
        $this->assertSame('user@tv.com', $parsed['usuario_app']);
        $this->assertSame('clave123', $parsed['password']);
    }

    public function test_parsea_con_texto_alrededor(): void
    {
        $parsed = WhatsAppTvCuentaInboundService::parsear("Listo\n(Lumix: cuenta@tv.es, xxxxx)\nGracias");

        $this->assertSame('cuenta@tv.es', $parsed['usuario_app']);
        $this->assertSame('xxxxx', $parsed['password']);
    }

    public function test_ignora_texto_que_solo_menciona_lumix(): void
    {
        $this->assertNull(WhatsAppTvCuentaInboundService::parsear('Tengo un problema con Lumix'));
    }

    public function test_nombre_usa_app_y_fecha(): void
    {
        $fecha = Carbon::parse('2026-10-15');

        $this->assertSame('Lumix_15-10-2026', WhatsAppTvCuentaInboundService::nombreBase(TvCuenta::APP_LUMIX, $fecha));
        $this->assertSame('Suffixed_15-10-2026', WhatsAppTvCuentaInboundService::nombreBase(TvCuenta::APP_SUFFIXED, $fecha));
    }

    public function test_arma_precios_de_20000_por_perfil_lumix(): void
    {
        $attrs = WhatsAppTvCuentaInboundService::armarAtributos(
            ['aplicacion' => TvCuenta::APP_LUMIX, 'usuario_app' => 'a@tv.es', 'password' => 'x'],
            Carbon::parse('2026-10-15'),
            'Lumix_15-10-2026'
        );

        $this->assertSame(20000.0, $attrs['precio_pantalla_1']);
        $this->assertSame(20000.0, $attrs['precio_pantalla_4']);
        $this->assertNull($attrs['precio_perfil_1']);
        $this->assertSame('2026-11-15', $attrs['vencimiento_pago']);
    }

    public function test_arma_precios_de_20000_por_perfil_suffixed(): void
    {
        $attrs = WhatsAppTvCuentaInboundService::armarAtributos(
            ['aplicacion' => TvCuenta::APP_SUFFIXED, 'usuario_app' => 'a@tv.es', 'password' => 'x'],
            Carbon::parse('2026-10-15'),
            'Suffixed_15-10-2026'
        );

        $this->assertSame(20000.0, $attrs['precio_perfil_1']);
        $this->assertSame(20000.0, $attrs['precio_perfil_3']);
        $this->assertSame('Perfil 1', $attrs['perfil_1']);
        $this->assertNull($attrs['precio_pantalla_1']);
    }
}
