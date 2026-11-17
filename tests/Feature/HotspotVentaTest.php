<?php

namespace Tests\Feature;

use App\Models\HotspotPlan;
use App\Models\HotspotVenta;
use App\Models\TpagoPaymentLink;
use App\Services\Hotspot\HotspotVentaService;
use App\Services\Tpago\TpagoPaymentLinkService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HotspotVentaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $conexion = (string) config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        Schema::dropIfExists('hotspot_ventas');
        Schema::dropIfExists('hotspot_planes');
        Schema::dropIfExists('servicio_hotspot');

        Schema::create('hotspot_planes', function (Blueprint $table) {
            $table->id('hotspot_plan_id');
            $table->string('nombre', 80);
            $table->unsignedInteger('gigas');
            $table->unsignedInteger('precio');
            $table->unsignedSmallInteger('vigencia_horas')->default(24);
            $table->string('rate_limit', 50)->nullable();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('hotspot_ventas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hotspot_plan_id')->nullable();
            $table->string('token', 64)->unique();
            $table->string('mac', 32)->nullable();
            $table->unsignedInteger('monto');
            $table->unsignedInteger('gigas');
            $table->unsignedSmallInteger('vigencia_horas')->default(24);
            $table->string('rate_limit', 50)->nullable();
            $table->string('estado', 20)->default('pendiente');
            $table->string('username', 32)->nullable();
            $table->string('password', 16)->nullable();
            $table->unsignedBigInteger('tpago_payment_link_id')->nullable();
            $table->string('checkout_url', 1000)->nullable();
            $table->boolean('radius_ok')->default(false);
            $table->timestamp('pagado_at')->nullable();
            $table->timestamp('vence_at')->nullable();
            $table->timestamps();
        });

        Schema::create('servicio_hotspot', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->nullable();
        });
    }

    public function test_el_portal_lista_solo_planes_visibles(): void
    {
        HotspotPlan::query()->create([
            'nombre' => '2 GB',
            'gigas' => 2,
            'precio' => 10000,
            'vigencia_horas' => 24,
            'activo' => true,
            'orden' => 1,
        ]);
        HotspotPlan::query()->create([
            'nombre' => 'Oculto',
            'gigas' => 5,
            'precio' => 20000,
            'vigencia_horas' => 24,
            'activo' => false,
        ]);

        $this->getJson('/api/hotspot/planes')
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonCount(1, 'planes')
            ->assertJsonPath('planes.0.nombre', '2 GB')
            ->assertJsonPath('planes.0.gigas', 2)
            ->assertJsonPath('planes.0.precio_texto', '10.000 Gs.');
    }

    public function test_comprar_abre_tpago_y_el_pago_entrega_usuario(): void
    {
        $plan = HotspotPlan::query()->create([
            'nombre' => '1 GB',
            'gigas' => 1,
            'precio' => 5000,
            'vigencia_horas' => 24,
            'rate_limit' => '2M/5M',
            'activo' => true,
        ]);

        $link = new TpagoPaymentLink;
        $link->id = 15;
        $link->link_url = 'https://comercios.bancard.com.py/links?alias=ABC';

        $mock = \Mockery::mock(TpagoPaymentLinkService::class);
        $mock->shouldReceive('disponible')->andReturn(true);
        $mock->shouldReceive('paraVentaHotspot')->once()->andReturn([
            'link' => $link,
            'checkout_url' => $link->link_url,
            'link_alias' => 'ABC',
            'amount' => 5000,
            'reused' => false,
            'provider' => 'tpago',
            'expires_at' => null,
        ]);
        $this->app->instance(TpagoPaymentLinkService::class, $mock);

        $respuesta = $this->postJson('/api/hotspot/comprar', [
            'plan_id' => $plan->getKey(),
            'mac' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('checkout_url', $link->link_url)
            ->assertJsonPath('gigas', 1)
            ->assertJsonMissingPath('password');

        $token = $respuesta->json('token');
        $this->getJson('/api/hotspot/ventas/'.$token)
            ->assertOk()
            ->assertJsonPath('estado', 'pendiente')
            ->assertJsonMissingPath('password');

        $venta = HotspotVenta::query()->where('token', $token)->firstOrFail();
        $this->assertSame('aa:bb:cc:dd:ee:ff', $venta->mac);
        $this->assertSame(15, (int) $venta->tpago_payment_link_id);

        $link->reference_id = 'HS-'.$venta->id.'-20261117120000';
        app(HotspotVentaService::class)->confirmarPago($link);

        $pagada = $this->getJson('/api/hotspot/ventas/'.$token)->assertOk();
        $pagada->assertJsonPath('estado', 'pagado');
        $usuario = $pagada->json('username');
        $this->assertMatchesRegularExpression('/^9\d{9}$/', (string) $usuario);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $pagada->json('password'));
    }

    public function test_un_plan_inexistente_no_abre_pago(): void
    {
        $this->postJson('/api/hotspot/comprar', ['plan_id' => 99])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'Ese plan no está a la venta.');
    }
}
