<?php

namespace Tests\Feature;

use App\Http\Controllers\Loyalty\NovedadController;
use App\Models\Novedad;
use App\Services\FcmPushService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class NovedadAvisoAperturaTest extends TestCase
{
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

        Schema::create('novedades', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 200);
            $table->string('subtitulo', 300)->nullable();
            $table->string('imagen')->nullable();
            $table->string('accion_url', 500)->nullable();
            $table->string('tipo', 20)->default('promo');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->boolean('mostrar_al_abrir')->default(false);
            $table->unsignedTinyInteger('segundos_al_abrir')->nullable();
            $table->date('vigente_desde')->nullable();
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();
        });
    }

    public function test_el_portal_no_manda_fechas_y_si_el_aviso_al_abrir(): void
    {
        $novedad = Novedad::query()->create([
            'titulo' => 'Sorteo Navideño',
            'tipo' => 'promo',
            'orden' => 1,
            'activa' => true,
            'mostrar_al_abrir' => true,
            'segundos_al_abrir' => 8,
            'vigente_desde' => '2026-12-01',
            'vigente_hasta' => '2026-12-24',
        ]);

        $portal = $novedad->toPortalArray();

        $this->assertArrayNotHasKey('vigente_desde', $portal);
        $this->assertArrayNotHasKey('vigente_hasta', $portal);
        $this->assertArrayNotHasKey('vigencia_desde', $portal);
        $this->assertTrue($portal['mostrar_al_abrir']);
        $this->assertSame(8, $portal['segundos_al_abrir']);
        $this->assertSame(8, $portal['tiempo_muestra_seg']);
        $this->assertSame(8, $portal['segundos_muestra']);
        $this->assertSame(8, $portal['duracion_seg']);
    }

    public function test_fuera_de_vigencia_no_se_publica(): void
    {
        Novedad::query()->create([
            'titulo' => 'Vencida',
            'tipo' => 'aviso',
            'activa' => true,
            'vigente_hasta' => now()->subDay()->toDateString(),
        ]);
        $vigente = Novedad::query()->create([
            'titulo' => 'Hoy',
            'tipo' => 'aviso',
            'activa' => true,
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => now()->toDateString(),
        ]);

        $ids = Novedad::publicadas()->pluck('id')->all();

        $this->assertSame([(int) $vigente->id], array_map('intval', $ids));
    }

    public function test_destildar_activa_y_aviso_los_apaga(): void
    {
        $novedad = Novedad::query()->create([
            'titulo' => 'Promo',
            'tipo' => 'promo',
            'activa' => true,
            'mostrar_al_abrir' => true,
            'segundos_al_abrir' => 5,
        ]);

        app(NovedadController::class)->update(Request::create('/loyalty/novedades/'.$novedad->id, 'PUT', [
            'titulo' => 'Promo',
            'tipo' => 'promo',
            'orden' => 2,
        ]), $novedad);

        $novedad->refresh();
        $this->assertFalse($novedad->activa);
        $this->assertFalse($novedad->mostrar_al_abrir);
        $this->assertSame(5, (int) $novedad->segundos_al_abrir);
        $this->assertFalse($novedad->toPortalArray()['mostrar_al_abrir']);
    }

    public function test_el_push_del_cliente_abre_el_historial(): void
    {
        $fcm = new FcmPushService;
        $data = $fcm->dataCliente(['tipo' => 'aviso']);
        $this->assertSame('com.isp.clientes.OPEN_NOTIFICATION', $data['click_action']);

        $propio = $fcm->dataCliente(['click_action' => 'FLUTTER_NOTIFICATION_CLICK']);
        $this->assertSame('FLUTTER_NOTIFICATION_CLICK', $propio['click_action']);

        $metodo = new ReflectionMethod(FcmPushService::class, 'messageBase');
        $mensaje = $metodo->invoke($fcm, 'Titulo', 'Cuerpo', $data, 'interplus_avisos_v2');

        $this->assertSame('Titulo', $mensaje['data']['title']);
        $this->assertSame('Cuerpo', $mensaje['data']['body']);
        $this->assertSame('Titulo', $mensaje['data']['titulo']);
        $this->assertSame('Cuerpo', $mensaje['data']['mensaje']);
        $this->assertSame('com.isp.clientes.OPEN_NOTIFICATION', $mensaje['data']['click_action']);
    }
}
