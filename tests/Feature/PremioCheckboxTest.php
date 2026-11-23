<?php

namespace Tests\Feature;

use App\Http\Controllers\Loyalty\PremioController;
use App\Models\Premio;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PremioCheckboxTest extends TestCase
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
    }

    public function test_destildar_los_checks_los_apaga_aunque_no_vengan_en_el_form(): void
    {
        $premio = $this->premioMarcado();

        app(PremioController::class)->update($this->pedido($premio, [
            'stock' => '4',
        ]), $premio);

        $premio->refresh();
        $this->assertFalse($premio->activo);
        $this->assertFalse($premio->destacado);
        $this->assertFalse($premio->requiere_aprobacion);
        $this->assertFalse($premio->una_vez_por_cliente);
        $this->assertSame(4, $premio->stock);
    }

    public function test_el_hidden_en_cero_apaga_los_checks_y_el_stock_ilimitado(): void
    {
        $premio = $this->premioMarcado();

        app(PremioController::class)->update($this->pedido($premio, [
            'activo' => '0',
            'destacado' => '0',
            'requiere_aprobacion' => '0',
            'una_vez_por_cliente' => '0',
            'stock_ilimitado' => '0',
        ]), $premio);

        $premio->refresh();
        $this->assertFalse($premio->activo);
        $this->assertFalse($premio->destacado);
        $this->assertFalse($premio->requiere_aprobacion);
        $this->assertFalse($premio->una_vez_por_cliente);
        $this->assertSame(0, $premio->stock);
    }

    public function test_los_checks_marcados_siguen_guardando_uno(): void
    {
        $premio = $this->premioMarcado();
        $premio->update([
            'activo' => false,
            'destacado' => false,
            'requiere_aprobacion' => false,
            'una_vez_por_cliente' => false,
            'stock' => 3,
        ]);

        app(PremioController::class)->update($this->pedido($premio, [
            'activo' => '1',
            'destacado' => '1',
            'requiere_aprobacion' => '1',
            'una_vez_por_cliente' => '1',
            'stock_ilimitado' => '1',
            'stock' => '3',
        ]), $premio);

        $premio->refresh();
        $this->assertTrue($premio->activo);
        $this->assertTrue($premio->destacado);
        $this->assertTrue($premio->requiere_aprobacion);
        $this->assertTrue($premio->una_vez_por_cliente);
        $this->assertNull($premio->stock);
    }

    private function premioMarcado(): Premio
    {
        return Premio::query()->create([
            'nombre' => 'Bienvenida App 10%',
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
            'stock' => null,
            'activo' => true,
            'destacado' => true,
            'requiere_aprobacion' => true,
            'una_vez_por_cliente' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function pedido(Premio $premio, array $extra): Request
    {
        return Request::create('/loyalty/premios/'.$premio->id, 'PUT', array_merge([
            'nombre' => $premio->nombre,
            'tipo' => Premio::TIPO_DESCUENTO,
            'puntos_requeridos' => 0,
            'descuento_porcentaje' => 10,
        ], $extra));
    }
}
