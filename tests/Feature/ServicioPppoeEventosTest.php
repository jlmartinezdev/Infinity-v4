<?php

namespace Tests\Feature;

use App\Http\Controllers\ServicioPppoeEventoController;
use App\Models\ServicioConexionEvento;
use App\Support\PppoeDesconexionContinua;
use App\Support\PppoeEventosResumen;
use Carbon\CarbonInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ServicioPppoeEventosTest extends TestCase
{
    private const UP = ServicioConexionEvento::TIPO_PPPOE_UP;

    private const DOWN = ServicioConexionEvento::TIPO_PPPOE_DOWN;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Este test solo corre contra sqlite en memoria.');
        }

        Carbon::setTestNow('2026-11-11 12:00:00');

        Schema::create('clientes', function (Blueprint $table) {
            $table->increments('cliente_id');
            $table->string('nombre');
            $table->string('apellido')->nullable();
            $table->string('cedula')->nullable();
            $table->timestamps();
        });

        Schema::create('routers', function (Blueprint $table) {
            $table->increments('router_id');
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('servicios', function (Blueprint $table) {
            $table->increments('servicio_id');
            $table->unsignedInteger('cliente_id')->nullable();
            $table->string('usuario_pppoe')->nullable();
            $table->string('ip')->nullable();
            $table->string('mac_address')->nullable();
            $table->timestamps();
        });

        Schema::create('servicio_conexion_eventos', function (Blueprint $table) {
            $table->increments('servicio_conexion_evento_id');
            $table->unsignedInteger('servicio_id');
            $table->string('tipo', 32);
            $table->string('fuente', 32)->nullable();
            $table->timestamp('ocurrio_at');
            $table->string('pppoe_estado', 16)->nullable();
            $table->string('usuario_pppoe', 100)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('mac_address', 32)->nullable();
            $table->unsignedInteger('router_id')->nullable();
            $table->string('uptime', 64)->nullable();
            $table->text('payload')->nullable();
            $table->timestamps();
        });

        DB::table('routers')->insert([
            ['router_id' => 1, 'nombre' => 'MK-N6-R1'],
            ['router_id' => 2, 'nombre' => 'MK-N2-R2'],
        ]);
        DB::table('clientes')->insert([
            ['cliente_id' => 1, 'nombre' => 'Nilsa', 'apellido' => 'Ojeda'],
            ['cliente_id' => 2, 'nombre' => 'Ignacio', 'apellido' => 'Miranda'],
            ['cliente_id' => 3, 'nombre' => 'Ana', 'apellido' => 'Martinez'],
        ]);
        DB::table('servicios')->insert([
            ['servicio_id' => 10, 'cliente_id' => 1, 'usuario_pppoe' => 'NILSA'],
            ['servicio_id' => 20, 'cliente_id' => 2, 'usuario_pppoe' => 'IGNACIO'],
            ['servicio_id' => 30, 'cliente_id' => 3, 'usuario_pppoe' => 'ANA'],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_resumen_cuenta_eventos_cortes_y_clientes_afectados(): void
    {
        $this->sembrar();

        $resumen = PppoeEventosResumen::totales($this->eventosDesde(now()->subDay()));

        $this->assertSame(
            ['total' => 8, 'conexiones' => 4, 'desconexiones' => 4, 'clientes_afectados' => 2],
            $resumen
        );
    }

    public function test_resumen_por_router_ordena_por_cortes(): void
    {
        $this->sembrar();

        $porRouter = PppoeEventosResumen::porRouter(
            $this->eventosDesde(now()->subDay()),
            [1 => 'MK-N6-R1', 2 => 'MK-N2-R2']
        );

        $this->assertSame(['MK-N6-R1', 'MK-N2-R2'], array_column($porRouter, 'nombre'));
        $this->assertSame([3, 1], array_column($porRouter, 'desconexiones'));
        $this->assertSame([3, 1], array_column($porRouter, 'conexiones'));
        $this->assertSame([100.0, 33.3], array_column($porRouter, 'pct'));
    }

    public function test_pantalla_usa_ultimas_24_horas_por_defecto(): void
    {
        $this->sembrar();

        $datos = $this->pantalla();

        $this->assertSame('24h', $datos['periodo']['clave']);
        $this->assertSame(8, $datos['resumen']['total']);
        // Nilsa (4 cortes... 3 en 24 h), Ana e Ignacio; el evento de hace 3 días queda afuera.
        $this->assertSame(3, $datos['grupos']->total());
    }

    public function test_tarjeta_de_desconexiones_filtra_la_tabla_sin_cambiar_los_totales(): void
    {
        $this->sembrar();

        $datos = $this->pantalla(['tipo' => self::DOWN, 'orden' => 'cortes']);

        $this->assertSame(8, $datos['resumen']['total']);
        $this->assertSame(4, $datos['resumen']['desconexiones']);

        $filas = collect($datos['grupos']->items());
        $this->assertSame([1, 3], $filas->pluck('cliente_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame([3, 1], $filas->pluck('desconexiones')->all());
        $this->assertSame('Nilsa Ojeda', $filas->first()->cliente_nombre);
    }

    public function test_filtro_de_router_no_esconde_los_otros_routers_del_resumen(): void
    {
        $this->sembrar();

        $datos = $this->pantalla(['router_id' => 2]);

        $this->assertSame(2, $datos['resumen']['total']);
        $this->assertCount(2, $datos['porRouter']);
    }

    public function test_periodo_todo_y_fechas_a_mano(): void
    {
        $this->sembrar();

        $todo = $this->pantalla(['periodo' => 'todo']);
        $this->assertSame(9, $todo['resumen']['total']);

        $fechas = $this->pantalla(['fecha_desde' => '2026-11-08', 'fecha_hasta' => '2026-11-08', 'periodo' => 'hoy']);
        $this->assertSame('fechas', $fechas['periodo']['clave']);
        $this->assertSame('del 08/11/2026 al 08/11/2026', $fechas['periodo']['etiqueta']);
        $this->assertSame(1, $fechas['resumen']['total']);
    }

    public function test_buscar_por_usuario_pppoe_no_falla_al_unir_con_servicios(): void
    {
        $this->sembrar();

        $datos = $this->pantalla(['buscar' => 'NILSA']);

        $this->assertSame(1, $datos['grupos']->total());
        $this->assertSame(6, $datos['resumen']['total']);
    }

    public function test_detalle_muestra_eventos_del_cliente_con_duracion_de_cada_estado(): void
    {
        $this->sembrar();

        $datos = $this->detalle(['cliente_id' => 1]);

        $this->assertSame('Nilsa Ojeda', $datos['titulo']);
        $this->assertCount(1, $datos['lineas']);
        $this->assertSame(['h1' => 1, 'h6' => 3, 'h24' => 3], array_intersect_key(
            $datos['lineas']->first()->conteo,
            ['h1' => 0, 'h6' => 0, 'h24' => 0]
        ));
        $this->assertSame('up', $datos['lineas']->first()->timeline['estado_actual']);

        $eventos = $datos['eventos'];
        $this->assertCount(6, $eventos);
        // El más reciente: se conectó hace 29 minutos y sigue así.
        $this->assertTrue($eventos[0]->conectado);
        $this->assertTrue($eventos[0]->sigue);
        $this->assertSame('29m', $eventos[0]->duracion);
        // El corte anterior duró un minuto.
        $this->assertFalse($eventos[1]->conectado);
        $this->assertFalse($eventos[1]->sigue);
        $this->assertSame('1m', $eventos[1]->duracion);
    }

    public function test_detalle_respeta_el_periodo_y_da_404_si_no_hay_eventos(): void
    {
        $this->sembrar();

        $this->assertCount(1, $this->detalle(['cliente_id' => 2])['eventos']);
        $this->assertCount(2, $this->detalle(['cliente_id' => 2, 'periodo' => 'todo'])['eventos']);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $this->detalle(['cliente_id' => 999]);
    }

    public function test_niveles_de_desconexion_continua_segun_umbrales(): void
    {
        $this->assertNull(PppoeDesconexionContinua::nivel(2, 2, 4));
        $this->assertSame('observar', PppoeDesconexionContinua::nivel(0, 3, 3));
        $this->assertSame('observar', PppoeDesconexionContinua::nivel(0, 0, 5));
        $this->assertSame('alto', PppoeDesconexionContinua::nivel(3, 3, 3));
        $this->assertSame('alto', PppoeDesconexionContinua::nivel(0, 6, 6));
        $this->assertSame('critico', PppoeDesconexionContinua::nivel(5, 5, 5));
        $this->assertSame('critico', PppoeDesconexionContinua::nivel(0, 0, 20));

        // La última hora sola no alcanza para "a observar": ese nivel no mira h1.
        $this->assertNull(PppoeDesconexionContinua::nivelDeVentana('h1', 2));
        $this->assertSame('alto', PppoeDesconexionContinua::nivelDeVentana('h1', 4));

        config(['pppoe_eventos.atencion.observar' => ['h1' => 2, 'h6' => null, 'h24' => null]]);
        $this->assertSame('observar', PppoeDesconexionContinua::nivel(2, 2, 2));
    }

    public function test_lista_de_atencion_ordena_por_gravedad_y_no_depende_del_periodo(): void
    {
        $this->sembrar();
        // Ana (router 2): 6 cortes en la última hora => crítico. Nilsa queda "a observar" (3 en 6 h).
        foreach ([50, 40, 30, 20, 10, 5] as $minutos) {
            $this->evento(30, self::UP, 2, now()->subMinutes($minutos + 1));
            $this->evento(30, self::DOWN, 2, now()->subMinutes($minutos));
        }

        $atencion = $this->pantalla(['periodo' => 'hoy', 'buscar' => 'no-existe'])['atencion'];

        $this->assertSame([30, 10], $atencion->pluck('servicio_id')->all());
        $this->assertSame(['critico', 'observar'], $atencion->pluck('nivel')->all());

        $ana = $atencion->first();
        $this->assertSame('Ana Martinez', $ana->nombre);
        $this->assertSame([6, 7, 7], [$ana->h1, $ana->h6, $ana->h24]);
        $this->assertSame(['h1' => 'critico', 'h6' => 'alto', 'h24' => 'observar'], $ana->ventanas);
        $this->assertFalse($ana->conectado);
        $this->assertSame('MK-N2-R2', $ana->router);
        $this->assertSame('5m', $ana->ultimo_corte_hace);

        // Con el filtro de router solo quedan las líneas de ese router.
        $soloRouter1 = $this->pantalla(['router_id' => 1])['atencion'];
        $this->assertSame([10], $soloRouter1->pluck('servicio_id')->all());
    }

    public function test_sin_desconexion_continua_la_lista_queda_vacia(): void
    {
        $this->evento(20, self::DOWN, 2, now()->subHours(2));
        $this->evento(20, self::UP, 2, now()->subHours(1));

        $this->assertTrue($this->pantalla()['atencion']->isEmpty());
    }

    /** @return array<string, mixed> */
    private function detalle(array $query): array
    {
        $request = Request::create('/servicios/pppoe-eventos/detalle', 'GET', $query);
        $this->app->instance('request', $request);

        return $this->app->make(ServicioPppoeEventoController::class)->detalle($request)->getData();
    }

    /** @return array<string, mixed> */
    private function pantalla(array $query = []): array
    {
        $request = Request::create('/servicios/pppoe-eventos', 'GET', $query);
        $this->app->instance('request', $request);

        return $this->app->make(ServicioPppoeEventoController::class)->index($request)->getData();
    }

    private function eventosDesde(CarbonInterface $desde)
    {
        return ServicioConexionEvento::query()
            ->whereIn('servicio_conexion_eventos.tipo', [self::UP, self::DOWN])
            ->where('servicio_conexion_eventos.ocurrio_at', '>=', $desde);
    }

    private function sembrar(): void
    {
        // Nilsa (router 1): 3 cortes y 3 reconexiones en las últimas horas.
        $this->evento(10, self::DOWN, 1, now()->subHours(5));
        $this->evento(10, self::UP, 1, now()->subHours(5)->addMinutes(2));
        $this->evento(10, self::DOWN, 1, now()->subHours(2));
        $this->evento(10, self::UP, 1, now()->subHours(2)->addMinutes(1));
        $this->evento(10, self::DOWN, 1, now()->subMinutes(30));
        $this->evento(10, self::UP, 1, now()->subMinutes(29));
        // Ana (router 2): un corte y sigue caída. Ignacio (router 2): solo se conectó.
        $this->evento(30, self::DOWN, 2, now()->subHours(3));
        $this->evento(20, self::UP, 2, now()->subHours(4));
        // Fuera de las últimas 24 h.
        $this->evento(20, self::DOWN, 2, Carbon::parse('2026-11-08 09:00:00'));
    }

    private function evento(int $servicioId, string $tipo, int $routerId, CarbonInterface $cuando): void
    {
        ServicioConexionEvento::create([
            'servicio_id' => $servicioId,
            'tipo' => $tipo,
            'fuente' => ServicioConexionEvento::FUENTE_WEBHOOK,
            'ocurrio_at' => $cuando,
            'pppoe_estado' => $tipo === self::UP ? 'up' : 'down',
            'usuario_pppoe' => 'USR'.$servicioId,
            'router_id' => $routerId,
        ]);
    }
}
