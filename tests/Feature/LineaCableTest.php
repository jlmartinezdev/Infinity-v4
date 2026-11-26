<?php

namespace Tests\Feature;

use App\Models\CajaFusion;
use App\Models\LineaCable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LineaCableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $conexion = (string) config('database.default');
        if ($conexion !== 'sqlite' || config("database.connections.{$conexion}.database") !== ':memory:') {
            $this->fail('Esta prueba solo puede correr sobre sqlite en memoria.');
        }

        foreach (['linea_cables', 'fibra_colores', 'nodos', 'cajas_fusion'] as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::create('fibra_colores', function (Blueprint $t) {
            $t->increments('fibra_color_id');
            $t->string('nombre');
            $t->string('codigo_hex')->nullable();
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
        Schema::create('nodos', function (Blueprint $t) {
            $t->increments('nodo_id');
            $t->string('descripcion');
            $t->timestamps();
        });
        Schema::create('cajas_fusion', function (Blueprint $t) {
            $t->increments('caja_fusion_id');
            $t->string('codigo', 60)->unique();
            $t->string('ubicacion', 200)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lon', 10, 7)->nullable();
            $t->text('notas')->nullable();
            $t->timestamps();
        });
        Schema::create('linea_cables', function (Blueprint $t) {
            $t->increments('linea_cable_id');
            $t->string('nombre', 100)->nullable();
            $t->unsignedInteger('fibra_color_id');
            $t->string('tipo_cable', 30)->default('distribucion');
            $t->unsignedSmallInteger('cantidad_hilos')->default(12);
            $t->string('estado', 30)->default('activo');
            $t->string('origen_tipo', 50);
            $t->unsignedInteger('origen_id');
            $t->string('destino_tipo', 50);
            $t->unsignedInteger('destino_id');
            $t->decimal('longitud_metros', 8, 2)->nullable();
            $t->json('coordenadas')->nullable();
            $t->json('hilos_detalle')->nullable();
            $t->text('notas')->nullable();
            $t->timestamps();
        });

        DB::table('fibra_colores')->insert(['fibra_color_id' => 1, 'nombre' => 'Negro', 'codigo_hex' => '#000000', 'activo' => true]);
        DB::table('nodos')->insert([
            ['nodo_id' => 1, 'descripcion' => 'Nodo Centro'],
            ['nodo_id' => 2, 'descripcion' => 'Nodo Norte'],
        ]);

        $this->withoutMiddleware([\Illuminate\Auth\Middleware\Authenticate::class, \App\Http\Middleware\CheckPermiso::class]);
    }

    private function cable(array $extra = []): LineaCable
    {
        return LineaCable::create(array_merge([
            'nombre'         => 'TRON-12',
            'fibra_color_id' => 1,
            'tipo_cable'     => 'troncal',
            'cantidad_hilos' => 12,
            'estado'         => 'activo',
            'origen_tipo'    => 'nodo',
            'origen_id'      => 1,
            'destino_tipo'   => 'nodo',
            'destino_id'     => 2,
        ], $extra));
    }

    private function datosForm(array $extra = []): array
    {
        return array_merge([
            'tipo_cable'     => 'troncal',
            'cantidad_hilos' => 12,
            'estado'         => 'activo',
            'fibra_color_id' => 1,
            'origen_tipo'    => 'nodo',
            'origen_id'      => 1,
            'destino_tipo'   => 'nodo',
            'destino_id'     => 2,
        ], $extra);
    }

    public function test_guardar_pelos_conserva_atenuacion_y_destino_que_el_modal_no_manda(): void
    {
        $cable = $this->cable(['hilos_detalle' => [
            ['numero' => 3, 'estado' => 'iluminado', 'uso' => 'PON 1', 'destino_detalle' => 'NAP-07', 'atenuacion_db' => 0.4],
        ]]);

        $this->post(route('sistema.lineas-cable.pelos.guardar', $cable), [
            'pelos' => [
                ['numero' => 3, 'estado' => 'reservado', 'uso' => 'PON 2'],
                ['numero' => 99, 'estado' => 'iluminado', 'uso' => 'no existe'],
            ],
        ])->assertRedirect();

        $pelo = collect($cable->fresh()->pelos)->firstWhere('numero', 3);
        $this->assertSame('reservado', $pelo['estado']);
        $this->assertSame('PON 2', $pelo['uso']);
        $this->assertSame('NAP-07', $pelo['destino_detalle']);
        $this->assertSame(0.4, $pelo['atenuacion_db']);
        $this->assertCount(12, $cable->fresh()->hilos_detalle);
    }

    public function test_no_se_guarda_un_cable_con_destino_inexistente_o_igual_al_origen(): void
    {
        $this->post(route('sistema.lineas-cable.store'), $this->datosForm(['destino_id' => 999]))
            ->assertSessionHasErrors('destino_id');

        $this->post(route('sistema.lineas-cable.store'), $this->datosForm(['destino_id' => 1]))
            ->assertSessionHasErrors('destino_id');

        $this->assertSame(0, LineaCable::count());

        $this->post(route('sistema.lineas-cable.store'), $this->datosForm())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('sistema.lineas-cable.index'));
        $this->assertSame(1, LineaCable::count());
    }

    public function test_editar_sin_nombre_genera_uno(): void
    {
        $cable = $this->cable();

        $this->put(route('sistema.lineas-cable.update', $cable), $this->datosForm(['nombre' => '']))
            ->assertSessionHasNoErrors();

        $this->assertStringStartsWith('TRON-12FO-', $cable->fresh()->nombre);
    }

    public function test_precargar_extremos_resuelve_etiquetas_sin_consultas_por_fila(): void
    {
        $cables = collect([$this->cable(), $this->cable(['destino_id' => 1, 'origen_id' => 2])]);
        $cables = LineaCable::whereKey($cables->pluck('linea_cable_id'))->get();

        LineaCable::precargarExtremos($cables);

        DB::enableQueryLog();
        $etiquetas = $cables->map(fn ($c) => $c->origen_etiqueta.' → '.$c->destino_etiqueta)->all();
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(['Nodo: Nodo Centro → Nodo: Nodo Norte', 'Nodo: Nodo Norte → Nodo: Nodo Centro'], $etiquetas);
    }

    public function test_las_rutas_exigen_el_permiso_de_lineas_de_cable(): void
    {
        $esperado = [
            'sistema.lineas-cable.index'        => 'permiso:ftth-lineas-cable.ver',
            'sistema.lineas-cable.create'       => 'permiso:ftth-lineas-cable.crear',
            'sistema.lineas-cable.store'        => 'permiso:ftth-lineas-cable.crear',
            'sistema.lineas-cable.edit'         => 'permiso:ftth-lineas-cable.editar',
            'sistema.lineas-cable.update'       => 'permiso:ftth-lineas-cable.editar',
            'sistema.lineas-cable.pelos.guardar' => 'permiso:ftth-lineas-cable.editar',
            'sistema.cajas-fusion.store'        => 'permiso:ftth-lineas-cable.crear',
            'sistema.lineas-cable.destroy'      => 'permiso:ftth-lineas-cable.eliminar',
        ];

        foreach ($esperado as $nombre => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($nombre)->gatherMiddleware(), $nombre);
        }
    }

    public function test_el_listado_muestra_kpis_extremos_y_escapa_el_nombre_en_el_confirm(): void
    {
        $this->cable(['nombre' => "O'Brien", 'longitud_metros' => 1500, 'hilos_detalle' => [['numero' => 1, 'estado' => 'iluminado']]]);

        $html = $this->get(route('sistema.lineas-cable.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Nodo: Nodo Centro', $html);
        $this->assertMatchesRegularExpression('/>\s*1\s*<\/span>\s*<span[^>]*>\s*\/ 12 iluminados/', $html);
        $this->assertStringNotContainsString("cable O&#039;Brien?')", $html);
        $this->assertStringContainsString('Cajas de fusión', $html);
        $this->assertStringContainsString('Caja de fusión', $html);
        $this->assertStringContainsString('abrirModalCajaFusion()', $html);
        $this->assertStringContainsString('CF-001', $html);
        $this->assertStringContainsString('caja-fusion-satelite', $html);
        $this->assertStringContainsString("mapTypeId: 'hybrid'", $html);
    }

    public function test_un_cable_puede_continuar_en_otro_y_no_en_si_mismo(): void
    {
        $troncal = $this->cable(['nombre' => 'TRONCAL']);
        $continua = $this->cable(['nombre' => 'CONTINUA', 'origen_id' => 2, 'destino_id' => 1]);

        $this->put(route('sistema.lineas-cable.update', $continua), $this->datosForm([
            'nombre' => 'CONTINUA',
            'origen_tipo' => 'linea_cable',
            'origen_id' => $troncal->linea_cable_id,
            'cantidad_hilos' => 6,
        ]))->assertSessionHasNoErrors();

        $continua->refresh();
        $this->assertSame('linea_cable', $continua->origen_tipo);
        $this->assertSame('Cable: TRONCAL', $continua->origen_etiqueta);
        $this->assertSame(6, $continua->cantidad_hilos);

        $this->put(route('sistema.lineas-cable.update', $troncal), $this->datosForm([
            'destino_tipo' => 'linea_cable',
            'destino_id' => $troncal->linea_cable_id,
        ]))->assertSessionHasErrors('destino_id');
    }

    public function test_la_caja_de_fusion_une_cables_y_el_pelo_cambia_de_color(): void
    {
        $this->post(route('sistema.cajas-fusion.store'), [
            'codigo' => 'MANUAL',
            'ubicacion' => 'Poste 12',
            'lat' => -26.6125,
            'lon' => -56.2463,
        ])->assertRedirect(route('sistema.lineas-cable.index'));

        $caja = CajaFusion::query()->first();
        $this->assertNotNull($caja);
        $this->assertSame('CF-001', $caja->codigo);
        $this->assertEqualsWithDelta(-26.6125, $caja->lat, 0.000001);
        $this->assertEqualsWithDelta(-56.2463, $caja->lon, 0.000001);

        $this->post(route('sistema.lineas-cable.store'), $this->datosForm([
            'nombre' => 'SALIDA',
            'destino_tipo' => 'caja_fusion',
            'destino_id' => $caja->caja_fusion_id,
            'cantidad_hilos' => 12,
        ]))->assertSessionHasNoErrors();

        $cable = LineaCable::where('nombre', 'SALIDA')->first();
        $this->assertSame('Fusión: CF-001', $cable->destino_etiqueta);

        $this->post(route('sistema.cajas-fusion.store'), [
            'ubicacion' => 'Poste 13',
        ])->assertRedirect(route('sistema.lineas-cable.index'));
        $this->assertSame('CF-002', CajaFusion::where('ubicacion', 'Poste 13')->value('codigo'));

        $this->post(route('sistema.lineas-cable.pelos.guardar', $cable), [
            'cantidad_hilos' => 4,
            'pelos' => [
                ['numero' => 1, 'estado' => 'iluminado', 'uso' => 'Continúa', 'color_pos' => 7],
            ],
        ])->assertRedirect();

        $fresco = $cable->fresh();
        $this->assertSame(4, $fresco->cantidad_hilos);
        $pelo = collect($fresco->pelos)->firstWhere('numero', 1);
        $this->assertSame('Rojo', $pelo['color_pelo']);
        $this->assertSame('Continúa', $pelo['uso']);
        $this->assertCount(4, $fresco->pelos);
    }

    public function test_el_cable_guarda_punto_de_inicio_y_punto_de_fin(): void
    {
        $this->post(route('sistema.lineas-cable.store'), $this->datosForm([
            'nombre' => 'GPS-1',
            'inicio_lat' => '-26,600000 -56,450000',
            'fin_lat' => -26.61,
            'fin_lon' => -56.46,
        ]))->assertSessionHasNoErrors();

        $cable = LineaCable::where('nombre', 'GPS-1')->first();
        $this->assertEqualsWithDelta(-26.6, $cable->coordenadas[0][0], 0.000001);
        $this->assertEqualsWithDelta(-56.45, $cable->coordenadas[0][1], 0.000001);
        $this->assertEqualsWithDelta(-26.61, $cable->coordenadas[1][0], 0.000001);

        $cable->update(['coordenadas' => [[-26.1, -56.1], [-26.2, -56.2], [-26.3, -56.3]]]);
        $this->put(route('sistema.lineas-cable.update', $cable), $this->datosForm([
            'nombre' => 'GPS-1',
            'inicio_lat' => -26.11,
            'inicio_lon' => -56.11,
            'fin_lat' => -26.33,
            'fin_lon' => -56.33,
        ]))->assertSessionHasNoErrors();

        $coords = $cable->fresh()->coordenadas;
        $this->assertCount(3, $coords);
        $this->assertEqualsWithDelta(-26.11, $coords[0][0], 0.000001);
        $this->assertEqualsWithDelta(-26.2, $coords[1][0], 0.000001);
        $this->assertEqualsWithDelta(-26.33, $coords[2][0], 0.000001);

        $this->put(route('sistema.lineas-cable.update', $cable), $this->datosForm([
            'nombre' => 'GPS-1',
            'inicio_lat' => -26.11,
        ]))->assertSessionHasErrors(['inicio_lon', 'fin_lat', 'fin_lon']);

        $this->put(route('sistema.lineas-cable.update', $cable), $this->datosForm([
            'nombre' => 'GPS-1',
            'inicio_lat' => '',
            'inicio_lon' => '',
            'fin_lat' => '',
            'fin_lon' => '',
        ]))->assertSessionHasNoErrors();
        $this->assertNull($cable->fresh()->coordenadas);
    }

    public function test_el_cable_guarda_el_recorrido_por_la_calle(): void
    {
        $this->post(route('sistema.lineas-cable.store'), $this->datosForm([
            'nombre' => 'RUTA-1',
            'inicio_lat' => -26.6,
            'inicio_lon' => -56.45,
            'fin_lat' => -26.61,
            'fin_lon' => -56.46,
            'coordenadas' => [
                [-26.6, -56.45],
                [-26.604, -56.453],
                [-26.607, -56.456],
                [-26.61, -56.46],
            ],
        ]))->assertSessionHasNoErrors();

        $coords = LineaCable::where('nombre', 'RUTA-1')->first()->coordenadas;
        $this->assertCount(4, $coords);
        $this->assertEqualsWithDelta(-26.604, $coords[1][0], 0.000001);
        $this->assertEqualsWithDelta(-56.456, $coords[2][1], 0.000001);
        $this->assertEqualsWithDelta(-26.61, $coords[3][0], 0.000001);
    }
}
