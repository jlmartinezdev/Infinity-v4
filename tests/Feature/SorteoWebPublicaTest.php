<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SorteoTicket;
use App\Services\Sorteo\SorteoQr;
use App\Services\Sorteo\SorteoService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SorteoWebPublicaTest extends TestCase
{
    private SorteoService $sorteo;

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('CACHE_STORE=array');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['CACHE_STORE'] = 'array';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['CACHE_STORE'] = 'array';

        parent::setUp();

        $conexion = (string) config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");
        if ($conexion !== 'sqlite' || $base !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('cliente_id');
            $table->string('cedula')->nullable();
            $table->string('nombre')->nullable();
            $table->string('apellido')->nullable();
            $table->string('telefono')->nullable();
            $table->string('estado')->default('activo');
            $table->timestamps();
        });
        Schema::create('factura_internas', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->string('tipo_factura')->default('servicio');
            $table->string('estado')->default('emitida');
            $table->decimal('total', 15, 2)->default(0);
            $table->date('periodo_desde')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->timestamps();
        });
        Schema::create('cobros', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->decimal('monto', 15, 2)->default(0);
            $table->dateTime('fecha_pago');
            $table->timestamps();
        });
        Schema::create('cobro_factura_interna', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cobro_id');
            $table->unsignedBigInteger('factura_interna_id');
            $table->decimal('monto', 15, 2);
        });

        $migration = require base_path('database/migrations/2026_11_22_210000_create_sorteo_navideno_tables.php');
        $migration->up();
        $google = require base_path('database/migrations/2026_11_23_150000_add_google_usuario_to_sorteo_resenas.php');
        $google->up();

        $this->sorteo = app(SorteoService::class);
    }

    public function test_el_qr_apunta_a_la_pagina_del_cliente(): void
    {
        $qr = SorteoQr::deCliente(1724);

        $this->assertSame('https://interplus.com.py/sorteo/1724', $qr['url']);
        $this->assertStringContainsString('<svg', $qr['svg']);
    }

    public function test_la_ficha_oculta_datos_y_el_celular_pide_la_cedula(): void
    {
        $cliente = Cliente::withoutEvents(fn () => Cliente::create([
            'cedula' => '4567891',
            'nombre' => 'JUAN CARLOS',
            'apellido' => 'PEREZ',
            'telefono' => null,
            'estado' => 'activo',
        ]));

        $ficha = $this->sorteo->fichaPublica($cliente);

        $this->assertSame('Juan C. P.', $ficha['nombre']);
        $this->assertTrue($ficha['falta_celular']);
        $this->assertTrue($ficha['pide_cedula']);
        $this->assertNull($ficha['celular_oculto']);
        $this->assertNull($ficha['google_usuario']);
        $this->assertArrayNotHasKey('cedula', $ficha);
        $this->assertNotNull($ficha['campana']);
        $this->assertNotEmpty($ficha['campana']['reglas']);

        try {
            $this->sorteo->guardarCelularPublico($cliente, '0981123456', '0000', 'María G.');
            $this->fail('La cédula incorrecta debía rechazarse.');
        } catch (DomainException $e) {
            $this->assertSame('Los últimos 4 números de la cédula no coinciden.', $e->getMessage());
        }

        $guardada = $this->sorteo->guardarCelularPublico($cliente->fresh(), '0981123456', '7891', 'María G.');

        $this->assertFalse($guardada['falta_celular']);
        $this->assertFalse($guardada['pide_cedula']);
        $this->assertSame('0981 *** 456', $guardada['celular_oculto']);
        $this->assertSame('María G.', $guardada['google_usuario']);
        $this->assertSame('+595981123456', $cliente->fresh()->telefono);
        $this->assertSame(0, SorteoTicket::query()->where('origen', SorteoTicket::ORIGEN_RESENA)->count());

        try {
            $this->sorteo->guardarCelularPublico($cliente->fresh(), '0981123456', '7891', 'Otro Nombre');
            $this->fail('El segundo envío debía rechazarse.');
        } catch (DomainException $e) {
            $this->assertSame('Ya enviaste tus datos para este sorteo.', $e->getMessage());
        }

        $this->assertSame('María G.', $this->sorteo->fichaPublica($cliente->fresh())['google_usuario']);
    }

    public function test_el_endpoint_exige_el_nombre_y_rechaza_el_segundo_envio(): void
    {
        config(['web_consulta.token' => 'token-de-prueba']);
        $cliente = Cliente::withoutEvents(fn () => Cliente::create([
            'cedula' => '4567891',
            'nombre' => 'JUAN CARLOS',
            'apellido' => 'PEREZ',
            'telefono' => null,
            'estado' => 'activo',
        ]));

        $this->postJson('/api/v1/web/sorteo', ['cliente_id' => $cliente->cliente_id], [
            'Authorization' => 'Bearer token-de-prueba',
        ])->assertOk()
            ->assertJsonPath('data.falta_celular', true)
            ->assertJsonPath('data.google_usuario', null);

        $this->postJson('/api/v1/web/sorteo/celular', [
            'cliente_id' => $cliente->cliente_id,
            'telefono' => '0981123456',
            'cedula_ultimos' => '7891',
        ], [
            'Authorization' => 'Bearer token-de-prueba',
        ])->assertStatus(422);

        $this->postJson('/api/v1/web/sorteo/celular', [
            'cliente_id' => $cliente->cliente_id,
            'telefono' => '0981123456',
            'nombre_google' => 'María G.',
            'cedula_ultimos' => '7891',
        ], [
            'Authorization' => 'Bearer token-de-prueba',
        ])->assertOk()
            ->assertJsonPath('data.falta_celular', false)
            ->assertJsonPath('data.google_usuario', 'María G.');

        $this->postJson('/api/v1/web/sorteo/celular', [
            'cliente_id' => $cliente->cliente_id,
            'telefono' => '0981123456',
            'nombre_google' => 'Otro Nombre',
            'cedula_ultimos' => '7891',
        ], [
            'Authorization' => 'Bearer token-de-prueba',
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Ya enviaste tus datos para este sorteo.');
    }
}
