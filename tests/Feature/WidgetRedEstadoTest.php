<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WidgetRedEstadoTest extends TestCase
{
    private const TOKEN = 'widget-token-de-prueba';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Este test solo corre contra sqlite en memoria.');
        }

        Carbon::setTestNow(Carbon::parse('2026-11-18 14:46:00'));
        config(['widget_red.token' => self::TOKEN]);

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
            $table->text('acceso_rapido')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();
        });

        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('nombre')->nullable();
            $table->string('categoria')->default('General');
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::create('rol_permiso', function (Blueprint $table) {
            $table->unsignedInteger('rol_id');
            $table->unsignedBigInteger('permiso_id');
            $table->primary(['rol_id', 'permiso_id']);
        });

        Schema::create('nodos', function (Blueprint $table) {
            $table->increments('nodo_id');
            $table->string('descripcion');
            $table->timestamps();
        });

        Schema::create('routers', function (Blueprint $table) {
            $table->increments('router_id');
            $table->unsignedInteger('nodo_id')->nullable();
            $table->string('nombre');
            $table->string('ip')->nullable();
            $table->string('password')->nullable();
            $table->string('estado')->nullable();
            $table->timestamp('ping_caido_desde')->nullable();
            $table->timestamps();
        });

        Schema::create('nodo_aps_wireless', function (Blueprint $table) {
            $table->increments('ap_id');
            $table->unsignedInteger('nodo_id')->nullable();
            $table->string('nombre');
            $table->string('ip')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('ping_ok')->nullable();
            $table->timestamp('ping_caido_desde')->nullable();
            $table->timestamps();
        });

        Schema::create('olts', function (Blueprint $table) {
            $table->increments('olt_id');
            $table->unsignedInteger('nodo_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('estado')->nullable();
            $table->string('gestion_password')->nullable();
            $table->timestamp('onus_synced_at')->nullable();
            $table->text('onus_sync_error')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->insert([
            'rol_id' => 2,
            'descripcion' => 'Tecnico',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodos')->insert([
            ['nodo_id' => 3, 'descripcion' => 'Nodo 3', 'created_at' => now(), 'updated_at' => now()],
            ['nodo_id' => 6, 'descripcion' => 'Nodo 6', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->usuario(1, 'Ana', ['sistema.ver', 'sistema-red-monitoreo.ver']);
        $this->usuario(2, 'Beto', ['sistema.ver']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sin_token_rechaza(): void
    {
        $this->getJson('/api/v1/widget/red')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_token_incorrecto_rechaza(): void
    {
        $this->getJson('/api/v1/widget/red', [
            'Authorization' => 'Bearer otro-token',
        ])->assertUnauthorized();
    }

    public function test_token_vacio_en_config_rechaza(): void
    {
        config(['widget_red.token' => '']);

        $this->getJson('/api/v1/widget/red', [
            'Authorization' => 'Bearer '.self::TOKEN,
        ])->assertUnauthorized();
    }

    public function test_la_sesion_staff_no_autoriza_el_endpoint(): void
    {
        $this->actingAs(User::query()->find(1))
            ->getJson('/api/v1/widget/red')
            ->assertUnauthorized();
    }

    public function test_conteos_y_caidas_desde_el_estado_guardado(): void
    {
        $routerDesde = now()->subHours(3);
        $apDesde = now()->subMinutes(40);

        DB::table('routers')->insert([
            [
                'router_id' => 1,
                'nodo_id' => 6,
                'nombre' => 'MK-N6',
                'ip' => '10.6.0.1',
                'password' => 'clave-router-secreta',
                'estado' => 'desconectado',
                'ping_caido_desde' => $routerDesde->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'router_id' => 2,
                'nodo_id' => 3,
                'nombre' => 'MK-N2',
                'ip' => '10.3.0.1',
                'password' => null,
                'estado' => 'conectado',
                'ping_caido_desde' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'router_id' => 3,
                'nodo_id' => null,
                'nombre' => 'CORE-RARO',
                'ip' => '10.0.0.9',
                'password' => null,
                'estado' => 'desconocido',
                'ping_caido_desde' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('nodo_aps_wireless')->insert([
            [
                'ap_id' => 1,
                'nodo_id' => 3,
                'nombre' => 'Torre Norte',
                'ip' => '10.3.1.10',
                'activo' => 1,
                'ping_ok' => 0,
                'ping_caido_desde' => $apDesde->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ap_id' => 2,
                'nodo_id' => 3,
                'nombre' => 'Sector Este',
                'ip' => '10.3.1.11',
                'activo' => 1,
                'ping_ok' => 1,
                'ping_caido_desde' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ap_id' => 3,
                'nodo_id' => 3,
                'nombre' => 'Sector Oeste',
                'ip' => '10.3.1.12',
                'activo' => 1,
                'ping_ok' => null,
                'ping_caido_desde' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ap_id' => 4,
                'nodo_id' => 3,
                'nombre' => 'AP-BAJA',
                'ip' => '10.3.1.99',
                'activo' => 0,
                'ping_ok' => 0,
                'ping_caido_desde' => $routerDesde->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->sembrarOltsSinSenal();

        $response = $this->getJson('/api/v1/widget/red', [
            'Authorization' => 'Bearer '.self::TOKEN,
        ]);

        $response->assertOk();
        $response->assertJsonPath('generated_at', now()->toIso8601String());
        $response->assertJsonPath('counts.ap', ['online' => 1, 'offline' => 1, 'unknown' => 1]);
        $response->assertJsonPath('counts.router', ['online' => 1, 'offline' => 1, 'unknown' => 1]);
        $response->assertJsonPath('counts.olt', ['online' => 0, 'offline' => 0, 'unknown' => 2]);

        $caidas = $response->json('caidas');
        $this->assertCount(2, $caidas);
        $this->assertSame(['type', 'name', 'nodo', 'since'], array_keys($caidas[0]));
        $this->assertSame([
            'type' => 'router',
            'name' => 'MK-N6',
            'nodo' => 'Nodo 6',
            'since' => $routerDesde->toIso8601String(),
        ], $caidas[0]);
        $this->assertSame([
            'type' => 'ap',
            'name' => 'Torre Norte',
            'nodo' => 'Nodo 3',
            'since' => $apDesde->toIso8601String(),
        ], $caidas[1]);

        $cuerpo = $response->getContent();
        $this->assertStringNotContainsString('clave-router-secreta', $cuerpo);
        $this->assertStringNotContainsString('clave-olt-secreta', $cuerpo);
        $this->assertStringNotContainsString('10.6.0.1', $cuerpo);
        $this->assertStringNotContainsString('OLT-NORTE', $cuerpo);
        $this->assertStringNotContainsString('AP-BAJA', $cuerpo);
        $this->assertNotContains('olt', array_column($caidas, 'type'));
    }

    public function test_la_vista_previa_es_solo_staff_y_muestra_la_tarjeta(): void
    {
        $this->sembrarEquiposMinimos();

        $this->get('/sistema/widget-red')->assertRedirect('/login');

        $this->actingAs(User::query()->find(2))
            ->get('/sistema/widget-red')
            ->assertForbidden();

        $this->actingAs(User::query()->find(2))
            ->getJson('/sistema/widget-red/datos')
            ->assertForbidden();

        $html = $this->actingAs(User::query()->find(1))
            ->get('/sistema/widget-red');
        $html->assertOk();
        $html->assertSee('Equipos caídos');
        $html->assertSee('Torre Norte');
        $html->assertSee('MK-N6');
        $html->assertSee('sin dato');
        $html->assertSee('AP');
        $html->assertSee('Router');
        $html->assertSee('OLT');
        $html->assertDontSee('clave-olt-secreta');

        $datos = $this->actingAs(User::query()->find(1))
            ->getJson('/sistema/widget-red/datos');
        $datos->assertOk();
        $datos->assertJsonPath('counts.olt.unknown', 2);
        $datos->assertJsonPath('counts.olt.online', 0);
        $datos->assertJsonPath('counts.olt.offline', 0);
        $datos->assertJsonPath('caidas.1.name', 'Torre Norte');
    }

    public function test_latencia_de_equipos_en_linea(): void
    {
        $response = $this->getJson('/api/v1/widget/red', ['Authorization' => 'Bearer '.self::TOKEN]);
        $response->assertOk();
        $response->assertJsonPath('latencia', [
            'ap' => ['promedio' => null, 'max' => null],
            'router' => ['promedio' => null, 'max' => null],
            'lentos' => [],
        ]);

        Schema::table('routers', fn (Blueprint $table) => $table->unsignedInteger('ping_latencia_ms')->nullable());
        Schema::table('nodo_aps_wireless', fn (Blueprint $table) => $table->unsignedInteger('ping_latencia_ms')->nullable());

        $fila = fn (array $datos) => $datos + ['created_at' => now(), 'updated_at' => now()];
        DB::table('routers')->insert([
            $fila(['router_id' => 1, 'nombre' => 'MK-A', 'ip' => '10.0.0.1', 'estado' => 'conectado', 'ping_latencia_ms' => 4]),
            $fila(['router_id' => 2, 'nombre' => 'MK-B', 'ip' => '10.0.0.2', 'estado' => 'conectado', 'ping_latencia_ms' => 9]),
            $fila(['router_id' => 3, 'nombre' => 'MK-CAIDO', 'ip' => '10.0.0.3', 'estado' => 'desconectado', 'ping_latencia_ms' => 999]),
        ]);
        DB::table('nodo_aps_wireless')->insert([
            $fila(['ap_id' => 1, 'nombre' => 'AP-1', 'ip' => '10.1.0.1', 'activo' => 1, 'ping_ok' => 1, 'ping_latencia_ms' => 30]),
            $fila(['ap_id' => 2, 'nombre' => 'AP-2', 'ip' => '10.1.0.2', 'activo' => 1, 'ping_ok' => 1, 'ping_latencia_ms' => 11]),
            $fila(['ap_id' => 3, 'nombre' => 'AP-SIN-MEDIDA', 'ip' => '10.1.0.3', 'activo' => 1, 'ping_ok' => 1, 'ping_latencia_ms' => null]),
            $fila(['ap_id' => 4, 'nombre' => 'AP-BAJA', 'ip' => '10.1.0.4', 'activo' => 0, 'ping_ok' => 1, 'ping_latencia_ms' => 500]),
        ]);

        $response = $this->getJson('/api/v1/widget/red', ['Authorization' => 'Bearer '.self::TOKEN]);
        $response->assertOk();
        $response->assertJsonPath('latencia.ap', ['promedio' => 21, 'max' => 30]);
        $response->assertJsonPath('latencia.router', ['promedio' => 7, 'max' => 9]);
        $response->assertJsonPath('latencia.lentos', [
            ['type' => 'ap', 'name' => 'AP-1', 'ms' => 30],
            ['type' => 'ap', 'name' => 'AP-2', 'ms' => 11],
            ['type' => 'router', 'name' => 'MK-B', 'ms' => 9],
            ['type' => 'router', 'name' => 'MK-A', 'ms' => 4],
        ]);
        $this->assertStringNotContainsString('10.1.0.1', $response->getContent());
    }

    private function sembrarEquiposMinimos(): void
    {
        DB::table('routers')->insert([
            'router_id' => 1,
            'nodo_id' => 6,
            'nombre' => 'MK-N6',
            'ip' => '10.6.0.1',
            'estado' => 'desconectado',
            'ping_caido_desde' => now()->subHour()->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('nodo_aps_wireless')->insert([
            'ap_id' => 1,
            'nodo_id' => 3,
            'nombre' => 'Torre Norte',
            'ip' => '10.3.1.10',
            'activo' => 1,
            'ping_ok' => 0,
            'ping_caido_desde' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->sembrarOltsSinSenal();
    }

    private function sembrarOltsSinSenal(): void
    {
        DB::table('olts')->insert([
            [
                'olt_id' => 1,
                'nodo_id' => 6,
                'codigo' => 'OLT-NORTE',
                'estado' => 'activo',
                'gestion_password' => 'clave-olt-secreta',
                'onus_synced_at' => now()->subMinutes(2)->format('Y-m-d H:i:s'),
                'onus_sync_error' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'olt_id' => 2,
                'nodo_id' => 3,
                'codigo' => 'OLT-SUR',
                'estado' => 'activo',
                'gestion_password' => 'clave-olt-secreta',
                'onus_synced_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'onus_sync_error' => 'timeout de gestion',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $permisos
     */
    private function usuario(int $id, string $nombre, array $permisos): void
    {
        DB::table('users')->insert([
            'usuario_id' => $id,
            'rol_id' => 2,
            'cliente_id' => null,
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
