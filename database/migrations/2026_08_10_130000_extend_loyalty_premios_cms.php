<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            $table->string('etiqueta', 20)->nullable()->after('destacado');
            $table->unsignedTinyInteger('tier')->nullable()->after('etiqueta');
            $table->boolean('requiere_aprobacion')->default(false)->after('tier');
            $table->unsignedInteger('tope_anual_por_cliente')->nullable()->after('requiere_aprobacion');
        });

        // stock null = sin límite (antes era NOT NULL default 0)
        DB::statement('ALTER TABLE premios MODIFY stock INT UNSIGNED NULL DEFAULT NULL');

        Schema::table('loyalty_reglas', function (Blueprint $table) {
            $table->string('frecuencia', 30)->nullable()->after('evento');
            $table->unsignedInteger('orden')->default(0)->after('frecuencia');
            $table->unsignedTinyInteger('fase')->nullable()->after('orden');
            $table->boolean('visible_portal')->default(true)->after('fase');
            $table->index(['activa', 'visible_portal', 'orden']);
        });

        Schema::create('puntos_lotes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->foreignId('puntos_movimiento_id')->nullable()->constrained('puntos_movimientos')->nullOnDelete();
            $table->unsignedInteger('puntos_iniciales');
            $table->unsignedInteger('puntos_restantes');
            $table->timestamp('vence_at')->nullable();
            $table->string('origen', 40)->default('credito'); // bienvenida|regla|ajuste|reversa|credito|backfill
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            $table->index(['cliente_id', 'vence_at']);
            $table->index(['cliente_id', 'puntos_restantes']);
            $table->index(['vence_at', 'puntos_restantes']);
        });

        // Backfill: un lote por cliente con el saldo actual (sin fecha de vencimiento).
        $cuentas = DB::table('cliente_puntos')->where('saldo', '>', 0)->get(['cliente_id', 'saldo']);
        foreach ($cuentas as $cuenta) {
            DB::table('puntos_lotes')->insert([
                'cliente_id' => $cuenta->cliente_id,
                'puntos_movimiento_id' => null,
                'puntos_iniciales' => (int) $cuenta->saldo,
                'puntos_restantes' => (int) $cuenta->saldo,
                'vence_at' => null,
                'origen' => 'backfill',
                'meta' => json_encode(['nota' => 'Saldo migrado a lotes FIFO']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Defaults de presentación portal en reglas existentes
        DB::table('loyalty_reglas')->where('codigo', 'bienvenida_app')->update([
            'frecuencia' => 'unica_vez',
            'orden' => 1,
            'fase' => 1,
            'visible_portal' => true,
            'nombre' => 'Bono de bienvenida',
            'descripcion' => 'La primera vez que abrís la app',
        ]);
        DB::table('loyalty_reglas')->where('codigo', 'pago_recibido')->update([
            'frecuencia' => 'mensual',
            'orden' => 10,
            'fase' => 3,
            'visible_portal' => true,
            'nombre' => 'Pago puntual',
            'descripcion' => 'Según el día del mes en que pagás tu factura de servicio',
        ]);
        DB::table('loyalty_reglas')->whereNull('frecuencia')->update([
            'frecuencia' => 'por_evento',
            'visible_portal' => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('puntos_lotes');

        Schema::table('loyalty_reglas', function (Blueprint $table) {
            $table->dropIndex(['activa', 'visible_portal', 'orden']);
            $table->dropColumn(['frecuencia', 'orden', 'fase', 'visible_portal']);
        });

        Schema::table('premios', function (Blueprint $table) {
            $table->dropColumn(['etiqueta', 'tier', 'requiere_aprobacion', 'tope_anual_por_cliente']);
        });

        DB::table('premios')->whereNull('stock')->update(['stock' => 0]);
        DB::statement('ALTER TABLE premios MODIFY stock INT UNSIGNED NOT NULL DEFAULT 0');
    }
};

