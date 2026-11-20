<?php

namespace Tests\Feature;

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

        foreach (['linea_cables', 'fibra_colores', 'nodos'] as $tabla) {
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
    }
}
