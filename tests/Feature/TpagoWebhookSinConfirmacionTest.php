<?php

namespace Tests\Feature;

use App\Models\Cobro;
use App\Models\TpagoPaymentLink;
use App\Services\FacturacionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TpagoWebhookSinConfirmacionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Este test solo corre contra sqlite en memoria.');
        }

        config([
            'tpago.enabled' => true,
            'tpago.public_key' => 'apps/public',
            'tpago.private_key' => 'private',
            'tpago.commerce_code' => '1',
            'tpago.branch_code' => '1',
            'tpago.base_url' => 'https://tpago.test',
            'tpago.callback_user' => '',
            'tpago.callback_password' => '',
            'tpago.verify_ip' => false,
        ]);

        Schema::dropIfExists('tpago_payment_links');
        Schema::create('tpago_payment_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_interna_id')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('cobro_id')->nullable();
            $table->unsignedInteger('amount');
            $table->string('description');
            $table->string('reference_id')->nullable();
            $table->string('link_alias')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedBigInteger('tpago_link_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('ticket_number')->nullable();
            $table->string('authorization_code')->nullable();
            $table->string('response_code')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function test_sin_auth_acepta_el_callback_pero_no_cobra_si_tpago_no_confirma(): void
    {
        $facturacion = \Mockery::mock(FacturacionService::class);
        $facturacion->shouldNotReceive('registrarCobro');
        $this->app->instance(FacturacionService::class, $facturacion);

        TpagoPaymentLink::query()->create([
            'cliente_id' => 1,
            'amount' => 5000,
            'description' => 'Factura',
            'link_alias' => 'PSHLA13790',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://tpago.test/*' => Http::response(['status' => 'success', 'payments' => []], 200),
        ]);

        $this->postJson('/api/v1/webhooks/tpago', [
            'payment' => [
                'hook_alias' => 'PSHLA13790',
                'status' => 'confirmed',
                'response_code' => '00',
                'amount' => 5000,
                'ticket_number' => '5723103463',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('messages.0.key', 'Confirmed');

        $link = TpagoPaymentLink::query()->where('link_alias', 'PSHLA13790')->first();
        $this->assertNotNull($link);
        $this->assertNull($link->cobro_id);
        $this->assertSame('pending_verify', $link->status);
    }

    public function test_cobra_el_monto_de_la_api_y_no_el_del_cuerpo(): void
    {
        $cobro = new Cobro;
        $cobro->id = 55;

        $facturacion = \Mockery::mock(FacturacionService::class);
        $facturacion->shouldReceive('registrarCobro')
            ->once()
            ->withArgs(function (array $data): bool {
                return (int) $data['monto'] === 5000
                    && (int) $data['cliente_id'] === 1;
            })
            ->andReturn($cobro);
        $facturacion->shouldReceive('sumarSaldoAFavorCliente')->once();
        $this->app->instance(FacturacionService::class, $facturacion);

        TpagoPaymentLink::query()->create([
            'cliente_id' => 1,
            'amount' => 5000,
            'description' => 'Factura',
            'link_alias' => 'PSHLA13790',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://tpago.test/*' => Http::response([
                'status' => 'success',
                'payments' => [[
                    'amount' => '5000.0',
                    'response_code' => '00',
                    'status' => 'confirmed',
                    'ticket_number' => '5723103463',
                    'authorization_code' => 'AUTH',
                ]],
            ], 200),
        ]);

        $this->postJson('/api/v1/webhooks/tpago', [
            'payment' => [
                'hook_alias' => 'PSHLA13790',
                'status' => 'confirmed',
                'response_code' => '00',
                'amount' => 1,
                'ticket_number' => 'falso',
            ],
        ])->assertOk();

        $link = TpagoPaymentLink::query()->where('link_alias', 'PSHLA13790')->first();
        $this->assertSame(55, (int) $link->cobro_id);
        $this->assertSame('confirmed', $link->status);
        $this->assertSame('5723103463', $link->ticket_number);
    }
}
