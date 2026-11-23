<?php

namespace Tests\Feature;

use App\Models\Canje;
use App\Models\ClientePuntos;
use App\Models\Premio;
use App\Services\Loyalty\CatalogoPremiosPortal;
use App\Services\Portal\PortalFeatureFlagsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalPremiosCatalogoTest extends TestCase
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

        config(['loyalty.limite_canjes_mes' => 20]);

        Schema::create('premios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('tipo')->nullable();
            $table->string('imagen')->nullable();
            $table->unsignedInteger('puntos_requeridos')->default(0);
            $table->decimal('descuento_porcentaje', 5, 2)->nullable();
            $table->decimal('descuento_monto', 15, 2)->nullable();
            $table->integer('stock')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('destacado')->default(false);
            $table->string('etiqueta')->nullable();
            $table->unsignedTinyInteger('tier')->nullable();
            $table->boolean('requiere_aprobacion')->default(false);
            $table->unsignedInteger('tope_anual_por_cliente')->nullable();
            $table->boolean('una_vez_por_cliente')->default(false);
            $table->timestamps();
        });

        Schema::create('canjes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->unsignedBigInteger('premio_id');
            $table->unsignedInteger('puntos_usados')->default(0);
            $table->string('modalidad');
            $table->string('estado')->default('PENDIENTE');
            $table->text('notas')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cliente_puntos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id')->unique();
            $table->integer('saldo')->default(0);
            $table->timestamps();
        });

        Schema::create('portal_app_config', function (Blueprint $table) {
            $table->id();
            $table->json('flags')->nullable();
            $table->json('pago_online')->nullable();
            $table->json('metodos_pago')->nullable();
            $table->json('referidos')->nullable();
            $table->json('whatsapp')->nullable();
            $table->json('resumen')->nullable();
            $table->json('faqs')->nullable();
            $table->timestamps();
        });
    }

    public function test_el_catalogo_incluye_gratis_y_no_oculta_por_tier(): void
    {
        $gratis = Premio::query()->create([
            'nombre' => 'Bienvenida App 10%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'activo' => true,
            'stock' => null,
            'destacado' => true,
            'tier' => 1,
            'una_vez_por_cliente' => true,
            'etiqueta' => 'nuevo',
        ]);
        Premio::query()->create([
            'nombre' => 'Canjealo 5%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 500,
            'descuento_porcentaje' => 5,
            'activo' => true,
            'stock' => null,
            'tier' => 1,
        ]);
        Premio::query()->create([
            'nombre' => 'Apagado',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'activo' => false,
            'stock' => null,
        ]);
        Premio::query()->create([
            'nombre' => 'Sin stock',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'activo' => true,
            'stock' => 0,
        ]);

        $lista = app(CatalogoPremiosPortal::class)->listar(9);
        $nombres = $lista->pluck('nombre')->all();

        $this->assertSame(['Bienvenida App 10%', 'Canjealo 5%'], $nombres);
        $destacado = $lista->first();
        $this->assertTrue($destacado['gratis']);
        $this->assertSame('GRATIS', $destacado['etiqueta_puntos']);
        $this->assertSame('nuevo', $destacado['badge']);
        $this->assertTrue($destacado['puede_canjear']);
        $this->assertSame($gratis->id, app(CatalogoPremiosPortal::class)->destacado(9)['id']);
        $this->assertFalse($lista->last()['puede_canjear']);
        $this->assertSame('Te faltan puntos.', $lista->last()['motivo']);
    }

    public function test_una_vez_sale_del_catalogo_y_el_cupon_queda_pendiente(): void
    {
        $premio = Premio::query()->create([
            'nombre' => 'Bienvenida App 10%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'activo' => true,
            'destacado' => true,
            'una_vez_por_cliente' => true,
            'stock' => null,
        ]);
        $canje = Canje::query()->create([
            'cliente_id' => 9,
            'premio_id' => $premio->id,
            'puntos_usados' => 0,
            'modalidad' => Canje::MOD_DESCUENTO,
            'estado' => Canje::ESTADO_PENDIENTE,
        ]);

        $this->assertCount(0, app(CatalogoPremiosPortal::class)->listar(9));
        $this->assertNull(app(CatalogoPremiosPortal::class)->destacado(9));
        $portal = $canje->fresh('premio')->toPortalArray();
        $this->assertSame('pendiente', $portal['estado_portal']);
        $this->assertSame('Pendiente', $portal['estado_label']);
        $this->assertTrue($portal['gratis']);
    }

    public function test_cupon_aplicado_en_factura_y_tope_anual(): void
    {
        $premio = Premio::query()->create([
            'nombre' => 'Canjealo 5%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 5,
            'activo' => true,
            'stock' => null,
            'tope_anual_por_cliente' => 1,
        ]);
        ClientePuntos::query()->create(['cliente_id' => 9, 'saldo' => 0]);
        $canje = Canje::query()->create([
            'cliente_id' => 9,
            'premio_id' => $premio->id,
            'puntos_usados' => 0,
            'modalidad' => Canje::MOD_DESCUENTO,
            'estado' => Canje::ESTADO_APLICADO,
        ]);

        $fila = app(CatalogoPremiosPortal::class)->listar(9)->first();
        $this->assertFalse($fila['puede_canjear']);
        $this->assertSame('Alcanzaste el tope anual de este premio.', $fila['motivo']);
        $portal = $canje->fresh('premio')->toPortalArray();
        $this->assertSame('aplicado', $portal['estado_portal']);
        $this->assertSame('Aplicado en factura', $portal['estado_label']);
    }

    public function test_los_flags_premios_y_puntos_salen_habilitados(): void
    {
        $flags = collect(app(PortalFeatureFlagsService::class)->flags(9))->keyBy('key');

        $this->assertSame('enabled', $flags['premios']['state']);
        $this->assertSame('enabled', $flags['puntos']['state']);
        $this->assertSame(0, $flags['puntos']['metadata']['saldo']);
    }
}
