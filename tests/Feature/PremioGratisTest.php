<?php

namespace Tests\Feature;

use App\Models\Canje;
use App\Models\Premio;
use App\Services\Loyalty\CanjeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class PremioGratisTest extends TestCase
{
    private CanjeService $canjes;

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

        $this->canjes = app(CanjeService::class);
    }

    public function test_descuento_de_cero_puntos_se_canjea_con_saldo_cero(): void
    {
        $premio = $this->premioDescuento(['puntos_requeridos' => 0, 'tope_anual_por_cliente' => 2]);

        $canje = $this->canjes->crear(7, $premio->id);

        $this->assertSame(0, $canje->puntos_usados);
        $this->assertSame(Canje::MOD_DESCUENTO, $canje->modalidad);
        $this->assertTrue($premio->fresh()->toPortalArray()['gratis']);
        $this->assertSame(0, $premio->fresh()->toPortalArray()['puntos_requeridos']);
    }

    public function test_el_tope_anual_sigue_aplicando_al_premio_gratis(): void
    {
        $premio = $this->premioDescuento(['puntos_requeridos' => 0, 'tope_anual_por_cliente' => 1]);
        $this->canjes->crear(7, $premio->id);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('tope anual');
        $this->canjes->crear(7, $premio->id);
    }

    public function test_una_vez_por_cliente_bloquea_aunque_el_canje_anterior_sea_de_otro_ano(): void
    {
        $premio = $this->premioDescuento([
            'puntos_requeridos' => 0,
            'una_vez_por_cliente' => true,
            'tope_anual_por_cliente' => 5,
        ]);
        $primero = $this->canjes->crear(7, $premio->id);
        $primero->forceFill(['created_at' => now()->subYear()])->save();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('una vez');
        $this->canjes->crear(7, $premio->id);
    }

    public function test_otro_tipo_no_se_canjea_con_cero_puntos(): void
    {
        $premio = Premio::query()->create([
            'nombre' => 'Taza',
            'tipo' => Premio::TIPO_FISICO,
            'puntos_requeridos' => 0,
            'activo' => true,
            'stock' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->canjes->crear(7, $premio->id);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function premioDescuento(array $extra = []): Premio
    {
        return Premio::query()->create(array_merge([
            'nombre' => '10% en la factura',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'activo' => true,
            'stock' => null,
        ], $extra));
    }
}
