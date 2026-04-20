<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (!Schema::hasColumn('servicios', 'app_tv')) {
                $table->boolean('app_tv')->default(false)->after('saldo_a_favor');
            }
            if (!Schema::hasColumn('servicios', 'cantidad_perfil_app')) {
                $table->unsignedTinyInteger('cantidad_perfil_app')->nullable()->after('app_tv');
            }
            if (!Schema::hasColumn('servicios', 'precio_app')) {
                $table->decimal('precio_app', 10, 2)->nullable()->after('cantidad_perfil_app');
            }
        });

        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'cliente_id')) {
                $table->unsignedBigInteger('servicio_id')->nullable()->after('tv_cuenta_id');
            }
        });

        if (Schema::hasColumn('tv_cuenta_asignaciones', 'cliente_id') && Schema::hasColumn('tv_cuenta_asignaciones', 'servicio_id')) {
            $rows = DB::table('tv_cuenta_asignaciones')->select('id', 'cliente_id')->get();
            foreach ($rows as $row) {
                $servicioId = DB::table('servicios')
                    ->where('cliente_id', (int) $row->cliente_id)
                    ->orderBy('servicio_id')
                    ->value('servicio_id');

                if ($servicioId) {
                    DB::table('tv_cuenta_asignaciones')
                        ->where('id', $row->id)
                        ->update(['servicio_id' => $servicioId]);
                }
            }
        }

        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'cliente_id')) {
                $table->dropForeign(['cliente_id']);
                $table->dropUnique(['tv_cuenta_id', 'cliente_id']);
                $table->dropColumn('cliente_id');
            }

            if (Schema::hasColumn('tv_cuenta_asignaciones', 'servicio_id')) {
                $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->cascadeOnDelete();
                $table->unique(['tv_cuenta_id', 'servicio_id'], 'tv_cuenta_servicio_unico');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'servicio_id')) {
                $table->dropForeign(['servicio_id']);
                $table->dropUnique('tv_cuenta_servicio_unico');
                $table->unsignedInteger('cliente_id')->nullable()->after('tv_cuenta_id');
            }
        });

        if (Schema::hasColumn('tv_cuenta_asignaciones', 'cliente_id') && Schema::hasColumn('tv_cuenta_asignaciones', 'servicio_id')) {
            $rows = DB::table('tv_cuenta_asignaciones')->select('id', 'servicio_id')->get();
            foreach ($rows as $row) {
                $clienteId = DB::table('servicios')
                    ->where('servicio_id', (int) $row->servicio_id)
                    ->value('cliente_id');

                if ($clienteId) {
                    DB::table('tv_cuenta_asignaciones')
                        ->where('id', $row->id)
                        ->update(['cliente_id' => $clienteId]);
                }
            }
        }

        Schema::table('tv_cuenta_asignaciones', function (Blueprint $table) {
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'cliente_id')) {
                $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
                $table->unique(['tv_cuenta_id', 'cliente_id']);
            }
            if (Schema::hasColumn('tv_cuenta_asignaciones', 'servicio_id')) {
                $table->dropColumn('servicio_id');
            }
        });

        Schema::table('servicios', function (Blueprint $table) {
            $drops = [];
            if (Schema::hasColumn('servicios', 'app_tv')) {
                $drops[] = 'app_tv';
            }
            if (Schema::hasColumn('servicios', 'cantidad_perfil_app')) {
                $drops[] = 'cantidad_perfil_app';
            }
            if (Schema::hasColumn('servicios', 'precio_app')) {
                $drops[] = 'precio_app';
            }

            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
