<?php

use App\Models\Permiso;
use App\Models\Rol;
use App\Support\PermisosCatalogo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorteo_campanas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('texto_ticket');
            $table->text('texto_invitacion')->nullable();
            $table->date('fecha_sorteo');
            $table->string('timezone', 64)->default('America/Asuncion');
            $table->date('periodo_uso_desde');
            $table->date('periodo_uso_hasta');
            $table->dateTime('pago_limite');
            $table->string('criterio_puntualidad', 20)->default('vencimiento');
            $table->unsignedTinyInteger('dia_puntualidad')->default(5);
            $table->json('meses_puntualidad_100');
            $table->boolean('exige_app')->default(true);
            $table->boolean('permite_multiples_premios')->default(false);
            $table->boolean('activa')->default(false);
            $table->boolean('imprimir_en_recibo')->default(true);
            $table->timestamps();
        });

        Schema::create('sorteo_premio_categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('sorteo_campanas')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre');
            $table->unsignedInteger('cupo')->default(5);
            $table->decimal('porcentaje', 5, 2)->nullable();
            $table->json('ventanas_emision');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['campana_id', 'codigo']);
        });

        Schema::create('promo_ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave')->unique();
            $table->boolean('activo')->default(true);
            $table->decimal('porcentaje', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sorteo_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('sorteo_campanas')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->unsignedInteger('numero');
            $table->string('origen', 20);
            $table->unsignedBigInteger('cobro_id')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('anulado_por')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();
            $table->unique('numero');
            $table->unique(['campana_id', 'cliente_id', 'origen']);
            $table->index(['campana_id', 'estado']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });

        Schema::create('sorteo_resenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('sorteo_campanas')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->unsignedBigInteger('cobro_id')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->unsignedInteger('marcada_por')->nullable();
            $table->timestamp('marcada_at')->nullable();
            $table->timestamps();
            $table->unique(['campana_id', 'cliente_id']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });

        Schema::create('sorteo_ganadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campana_id')->constrained('sorteo_campanas')->cascadeOnDelete();
            $table->unsignedInteger('cliente_id');
            $table->foreignId('categoria_id')->constrained('sorteo_premio_categorias')->cascadeOnDelete();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->unique(['campana_id', 'cliente_id', 'categoria_id']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });

        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('cliente_id');
            $table->string('tipo', 30);
            $table->decimal('porcentaje', 5, 2);
            $table->foreignId('campana_id')->nullable()->constrained('sorteo_campanas')->nullOnDelete();
            $table->foreignId('ganador_id')->nullable()->constrained('sorteo_ganadores')->nullOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('factura_interna_id')->nullable();
            $table->string('ventana', 7)->default('');
            $table->timestamp('canjeado_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('anulado_por')->nullable();
            $table->timestamp('anulado_at')->nullable();
            $table->timestamps();
            $table->unique(['cliente_id', 'tipo', 'ventana']);
            $table->index(['estado', 'ventana']);
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
        });

        $campanaId = DB::table('sorteo_campanas')->insertGetId([
            'nombre' => 'Sorteo Navideño 2026',
            'texto_ticket' => 'SORTEO NAVIDENO INTERPLUS',
            'texto_invitacion' => 'Si el cliente deja una reseña en nuestras redes, tiene doble chance. Anotá su celular para llamarlo si gana.',
            'fecha_sorteo' => '2026-12-23',
            'timezone' => 'America/Asuncion',
            'periodo_uso_desde' => '2026-11-01',
            'periodo_uso_hasta' => '2026-11-30',
            'pago_limite' => '2026-12-05 23:59:59',
            'criterio_puntualidad' => 'vencimiento',
            'dia_puntualidad' => 5,
            'meses_puntualidad_100' => json_encode(['2026-10', '2026-11', '2026-12']),
            'exige_app' => true,
            'permite_multiples_premios' => false,
            'activa' => true,
            'imprimir_en_recibo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ahora = now();
        DB::table('sorteo_premio_categorias')->insert([
            [
                'campana_id' => $campanaId,
                'codigo' => 'apto_100',
                'nombre' => 'Apto 100%',
                'cupo' => 5,
                'porcentaje' => 100,
                'ventanas_emision' => json_encode(['2027-01', '2027-02']),
                'orden' => 1,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'campana_id' => $campanaId,
                'codigo' => 'apto_50',
                'nombre' => 'Apto 50%',
                'cupo' => 5,
                'porcentaje' => 50,
                'ventanas_emision' => json_encode(['2027-01', '2027-02']),
                'orden' => 2,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            [
                'campana_id' => $campanaId,
                'codigo' => 'cajas',
                'nombre' => 'Cajas navideñas',
                'cupo' => 5,
                'porcentaje' => null,
                'ventanas_emision' => json_encode([]),
                'orden' => 3,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
        ]);

        DB::table('promo_ajustes')->insert([
            'clave' => 'bienvenida_app',
            'activo' => true,
            'porcentaje' => 10,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);

        if (! Schema::hasTable('permisos')) {
            return;
        }

        $codigos = ['sorteo.ver', 'sorteo.editar'];
        foreach (PermisosCatalogo::filasParaSeeder() as $fila) {
            if (! in_array($fila['codigo'] ?? '', $codigos, true)) {
                continue;
            }
            Permiso::updateOrCreate(['codigo' => $fila['codigo']], $fila);
        }

        $ids = Permiso::query()->whereIn('codigo', $codigos)->pluck('id')->all();
        $admin = Rol::whereRaw('LOWER(descripcion) = ?', ['administrador'])->first();
        if ($admin && $ids !== []) {
            $admin->permisos()->syncWithoutDetaching($ids);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
        Schema::dropIfExists('sorteo_ganadores');
        Schema::dropIfExists('sorteo_resenas');
        Schema::dropIfExists('sorteo_tickets');
        Schema::dropIfExists('promo_ajustes');
        Schema::dropIfExists('sorteo_premio_categorias');
        Schema::dropIfExists('sorteo_campanas');

        if (! Schema::hasTable('permisos')) {
            return;
        }

        $ids = DB::table('permisos')->whereIn('codigo', ['sorteo.ver', 'sorteo.editar'])->pluck('id');
        if ($ids->isNotEmpty() && Schema::hasTable('rol_permiso')) {
            DB::table('rol_permiso')->whereIn('permiso_id', $ids)->delete();
        }
        DB::table('permisos')->whereIn('codigo', ['sorteo.ver', 'sorteo.editar'])->delete();
    }
};
