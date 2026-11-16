<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WhatsappEncargoNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappEncargoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('roles', function (Blueprint $table) {
            $table->increments('rol_id');
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->increments('usuario_id');
            $table->unsignedInteger('rol_id')->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->string('name');
            $table->string('email');
            $table->string('contrasena')->nullable();
            $table->text('permisos')->nullable();
            $table->string('estado')->default('activo');
            $table->string('cargo')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_contactos', function (Blueprint $table) {
            $table->id();
            $table->string('telefono', 20)->unique();
            $table->string('nombre', 200)->nullable();
            $table->unsignedInteger('cliente_id')->nullable();
            $table->unsignedInteger('whatsapp_asunto_id')->nullable();
            $table->unsignedInteger('encargado_usuario_id')->nullable();
            $table->timestamp('encargado_at')->nullable();
            $table->timestamp('ultimo_visto_at')->nullable();
            $table->unsignedInteger('mensajes_count')->default(0);
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['rol_id' => 1, 'descripcion' => 'Administrador', 'created_at' => now(), 'updated_at' => now()],
            ['rol_id' => 2, 'descripcion' => 'Tecnico', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->usuario(1, 'Ana', 1);
        $this->usuario(2, 'Beto', 1);
        $this->usuario(3, 'Carla', 2, null, []);
    }

    public function test_encargarse_transferir_y_soltar(): void
    {
        Notification::fake();
        $ana = User::query()->find(1);
        $beto = User::query()->find(2);
        $carla = User::query()->find(3);

        $this->actingAs($carla)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '595981111111',
        ])->assertForbidden();

        $toma = $this->actingAs($ana)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '0981111111',
        ]);
        $toma->assertOk();
        $toma->assertJsonPath('telefono', '595981111111');
        $toma->assertJsonPath('encargado.nombre', 'Ana');
        $toma->assertJsonPath('encargado.mio', true);

        $this->actingAs($beto)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '595981111111',
        ])->assertStatus(409);

        $this->actingAs($beto)->getJson('/whatsapp/encargo?tel=595981111111')
            ->assertOk()
            ->assertJsonPath('encargado.nombre', 'Ana')
            ->assertJsonPath('encargado.mio', false);

        $this->actingAs($beto)->postJson('/whatsapp/encargo/transferir', [
            'telefono' => '595981111111',
            'usuario_id' => 1,
        ])->assertForbidden();

        $paso = $this->actingAs($ana)->postJson('/whatsapp/encargo/transferir', [
            'telefono' => '595981111111',
            'usuario_id' => 2,
        ]);
        $paso->assertOk();
        $paso->assertJsonPath('encargado.nombre', 'Beto');
        $paso->assertJsonPath('encargado.mio', false);
        Notification::assertSentTo($beto, WhatsappEncargoNotification::class, function (WhatsappEncargoNotification $aviso) {
            return str_contains($aviso->mensaje, 'Ana te pasó el chat')
                && str_contains($aviso->url, 'tel=595981111111');
        });

        $this->actingAs($ana)->postJson('/whatsapp/encargo/soltar', [
            'telefono' => '595981111111',
        ])->assertForbidden();

        $this->actingAs($beto)->postJson('/whatsapp/encargo/soltar', [
            'telefono' => '595981111111',
        ])->assertOk()->assertJsonPath('encargado', null);

        $this->actingAs($ana)->getJson('/whatsapp/encargos')
            ->assertOk()
            ->assertJsonPath('encargos', []);
    }

    public function test_el_encargo_de_ayer_no_sigue_hoy(): void
    {
        $ana = User::query()->find(1);
        $beto = User::query()->find(2);

        $this->actingAs($ana)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '595981111111',
        ])->assertOk();

        DB::table('whatsapp_contactos')->update([
            'encargado_at' => now()->subDay(),
        ]);

        $this->actingAs($beto)->getJson('/whatsapp/encargo?tel=595981111111')
            ->assertOk()
            ->assertJsonPath('encargado', null);

        $this->actingAs($ana)->postJson('/whatsapp/encargo/soltar', [
            'telefono' => '595981111111',
        ])->assertForbidden();

        $this->assertSame(1, app(\App\Services\WhatsApp\WhatsappEncargoService::class)->liberarVencidos());

        $this->actingAs($beto)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '595981111111',
        ])->assertOk()->assertJsonPath('encargado.nombre', 'Beto');
    }

    public function test_el_punto_verde_solo_si_el_staff_esta_en_el_panel(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->nullable();
            $table->integer('last_activity');
        });

        $ana = User::query()->find(1);
        $this->actingAs($ana)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '595981111111',
        ])->assertOk()->assertJsonPath('encargado.en_linea', false);

        DB::table('sessions')->insert([
            'id' => 'sesion-ana',
            'user_id' => 1,
            'last_activity' => time() - 600,
        ]);

        $this->actingAs($ana)->getJson('/whatsapp/encargo?tel=595981111111')
            ->assertOk()
            ->assertJsonPath('encargado.en_linea', false);

        $servicio = new \App\Services\WhatsApp\WhatsappEncargoService(
            $this->app->make(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class)
        );

        $this->assertFalse($servicio->estado($ana, '595981111111')['encargado']['en_linea']);

        DB::table('sessions')->where('id', 'sesion-ana')->update(['last_activity' => time()]);

        $servicio = new \App\Services\WhatsApp\WhatsappEncargoService(
            $this->app->make(\App\Services\WhatsApp\WhatsAppPhoneNormalizer::class)
        );
        $this->assertTrue($servicio->estado($ana, '595981111111')['encargado']['en_linea']);
    }

    public function test_el_menu_cuenta_los_chats_con_mensajes_sin_leer(): void
    {
        Schema::create('whatsapp_mensajes', function (Blueprint $table) {
            $table->id();
            $table->string('direccion', 20);
            $table->string('telefono', 20);
            $table->string('contacto_nombre')->nullable();
            $table->string('tipo', 40)->default('text');
            $table->text('cuerpo')->nullable();
            $table->string('estado', 30)->default('recibido');
            $table->timestamps();
        });

        $ahora = now();
        DB::table('whatsapp_mensajes')->insert([
            ['direccion' => 'entrada', 'telefono' => '595981111111', 'estado' => 'recibido', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['direccion' => 'entrada', 'telefono' => '595981111111', 'estado' => 'recibido', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['direccion' => 'entrada', 'telefono' => '595982222222', 'estado' => 'leido', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['direccion' => 'salida', 'telefono' => '595983333333', 'estado' => 'enviado', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['direccion' => 'entrada', 'telefono' => '595984444444', 'estado' => 'recibido', 'created_at' => $ahora, 'updated_at' => $ahora],
            ['direccion' => 'entrada', 'telefono' => '595985555555', 'estado' => 'recibido', 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
        ]);

        \App\Models\WhatsappMensaje::olvidarChatsSinLeer();

        $ana = User::query()->find(1);
        $corte = $this->actingAs($ana)->getJson('/whatsapp/sin-leer')
            ->assertOk()
            ->assertJsonPath('chats', 2)
            ->assertJsonPath('nuevos', []);
        $ultimo = (int) $corte->json('ultimo_id');
        $this->assertGreaterThan(0, $ultimo);

        DB::table('whatsapp_mensajes')->insert([
            'direccion' => 'entrada',
            'telefono' => '595986666666',
            'contacto_nombre' => 'Laura',
            'cuerpo' => 'Hola, hay internet?',
            'estado' => 'recibido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uno = $this->actingAs($ana)->getJson('/whatsapp/sin-leer?desde='.$ultimo)
            ->assertOk()
            ->assertJsonPath('avisos', 1)
            ->assertJsonPath('nuevos.0.nombre', 'Laura')
            ->assertJsonPath('nuevos.0.cuerpo', 'Hola, hay internet?');
        $this->assertSame((int) $uno->json('nuevos.0.id'), (int) $uno->json('ultimo_id'));

        DB::table('whatsapp_mensajes')->insert([
            [
                'direccion' => 'entrada',
                'telefono' => '595986666666',
                'contacto_nombre' => 'Laura',
                'cuerpo' => 'Sigue sin andar',
                'estado' => 'recibido',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'direccion' => 'entrada',
                'telefono' => '595987777777',
                'contacto_nombre' => 'Pedro',
                'cuerpo' => 'El ultimo aviso',
                'estado' => 'recibido',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $resumen = $this->actingAs($ana)->getJson('/whatsapp/sin-leer?desde='.$ultimo)
            ->assertOk()
            ->assertJsonCount(1, 'nuevos')
            ->assertJsonPath('avisos', 3)
            ->assertJsonPath('nuevos.0.nombre', 'Pedro')
            ->assertJsonPath('nuevos.0.cuerpo', 'El ultimo aviso');
        $this->assertSame((int) $resumen->json('nuevos.0.id'), (int) $resumen->json('ultimo_id'));
    }

    public function test_el_aviso_y_la_transferencia_quedan_en_quien_tiene_permiso(): void
    {
        Notification::fake();
        $ana = User::query()->find(1);

        $this->actingAs(User::query()->find(3))->getJson('/whatsapp/sin-leer')->assertForbidden();

        $lista = $this->actingAs($ana)->getJson('/whatsapp/encargo/staff');
        $lista->assertOk();
        $nombres = collect($lista->json('usuarios'))->pluck('name')->all();
        $this->assertContains('Beto', $nombres);
        $this->assertNotContains('Carla', $nombres);

        $this->actingAs($ana)->postJson('/whatsapp/encargo/tomar', [
            'telefono' => '0981111111',
        ])->assertOk();

        $this->actingAs($ana)->postJson('/whatsapp/encargo/transferir', [
            'telefono' => '595981111111',
            'usuario_id' => 3,
        ])->assertNotFound();

        Notification::assertNothingSent();
    }

    private function usuario(int $id, string $nombre, int $rolId, ?int $clienteId = null, array $permisos = []): void
    {
        DB::table('users')->insert([
            'usuario_id' => $id,
            'rol_id' => $rolId,
            'cliente_id' => $clienteId,
            'name' => $nombre,
            'email' => strtolower($nombre).'@test.local',
            'contrasena' => 'secreto',
            'permisos' => json_encode($permisos),
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
