<?php

namespace Tests\Feature;

use App\Models\NodoApWireless;
use App\Models\NodoApWirelessCaida;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NodoApWirelessCaidaHistorialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Este test solo corre contra sqlite en memoria.');
        }

        Carbon::setTestNow(Carbon::parse('2026-11-20 17:00:00'));

        Schema::create('nodo_aps_wireless', function (Blueprint $table) {
            $table->increments('ap_id');
            $table->unsignedInteger('nodo_id');
            $table->string('nombre');
            $table->string('ip');
            $table->boolean('activo')->default(true);
            $table->boolean('ping_ok')->nullable();
            $table->unsignedInteger('ping_latencia_ms')->nullable();
            $table->timestamp('ping_at')->nullable();
            $table->timestamp('ping_caido_desde')->nullable();
            $table->string('ping_error')->nullable();
            $table->unsignedSmallInteger('ping_fallos_seguidos')->default(0);
            $table->boolean('ping_alerta_enviada')->default(false);
            $table->timestamps();
        });

        Schema::create('nodo_ap_wireless_caidas', function (Blueprint $table) {
            $table->increments('caida_id');
            $table->unsignedInteger('ap_id');
            $table->timestamp('inicio');
            $table->timestamp('fin')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('nodo_ap_wireless_caidas');
        Schema::dropIfExists('nodo_aps_wireless');
        parent::tearDown();
    }

    public function test_al_volver_el_ping_guarda_inicio_y_fin_sin_borrar(): void
    {
        $inicio = Carbon::parse('2026-11-20 16:10:00');
        $ap = $this->ap([
            'ping_ok' => false,
            'ping_caido_desde' => $inicio,
            'ping_fallos_seguidos' => 4,
        ]);

        $ap->aplicarResultadoPing(['ok' => true, 'latency_ms' => 3, 'error' => null]);

        $filas = NodoApWirelessCaida::query()->where('ap_id', $ap->ap_id)->get();
        $this->assertCount(1, $filas);
        $this->assertSame($inicio->toDateTimeString(), $filas[0]->inicio->toDateTimeString());
        $this->assertSame('2026-11-20 17:00:00', $filas[0]->fin->toDateTimeString());
        $this->assertNull($ap->fresh()->ping_caido_desde);
    }

    public function test_cada_corte_agrega_una_fila_y_la_anterior_queda(): void
    {
        $ap = $this->ap(['ping_ok' => true]);

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00'));
        $ap->aplicarResultadoPing(['ok' => false, 'latency_ms' => null, 'error' => 'Sin respuesta']);

        Carbon::setTestNow(Carbon::parse('2026-11-20 10:20:00'));
        $ap->refresh();
        $ap->aplicarResultadoPing(['ok' => true, 'latency_ms' => 2, 'error' => null]);

        Carbon::setTestNow(Carbon::parse('2026-11-20 15:00:00'));
        $ap->refresh();
        $ap->aplicarResultadoPing(['ok' => false, 'latency_ms' => null, 'error' => 'Sin respuesta']);

        $filas = NodoApWirelessCaida::query()->where('ap_id', $ap->ap_id)->orderBy('caida_id')->get();
        $this->assertCount(2, $filas);
        $this->assertSame('2026-11-20 10:00:00', $filas[0]->inicio->toDateTimeString());
        $this->assertSame('2026-11-20 10:20:00', $filas[0]->fin->toDateTimeString());
        $this->assertSame('2026-11-20 15:00:00', $filas[1]->inicio->toDateTimeString());
        $this->assertNull($filas[1]->fin);
    }

    private function ap(array $extra): NodoApWireless
    {
        return NodoApWireless::query()->create(array_merge([
            'nodo_id' => 1,
            'nombre' => 'AP prueba',
            'ip' => '10.0.0.8',
            'activo' => true,
        ], $extra));
    }
}
