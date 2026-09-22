<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicio_hotspot')) {
            return;
        }

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            if (! Schema::hasColumn('servicio_hotspot', 'cliente_id')) {
                $table->unsignedInteger('cliente_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('servicio_hotspot', 'slot_numero')) {
                $table->unsignedTinyInteger('slot_numero')->nullable()->after('cliente_id');
            }
        });

        $filas = DB::table('servicio_hotspot')
            ->leftJoin('servicios', 'servicios.servicio_id', '=', 'servicio_hotspot.servicio_id')
            ->orderBy('servicios.cliente_id')
            ->orderBy('servicio_hotspot.id')
            ->get([
                'servicio_hotspot.id',
                'servicio_hotspot.username',
                'servicios.cliente_id',
            ]);

        $slotsPorCliente = [];
        $usernames = [];
        $eliminar = [];

        foreach ($filas as $fila) {
            $clienteId = $fila->cliente_id ? (int) $fila->cliente_id : 0;
            if ($clienteId < 1) {
                $eliminar[] = $fila->id;
                continue;
            }

            $slotsPorCliente[$clienteId] = ($slotsPorCliente[$clienteId] ?? 0) + 1;
            $slot = $slotsPorCliente[$clienteId];
            if ($slot > 3) {
                $eliminar[] = $fila->id;
                continue;
            }

            $username = trim((string) $fila->username);
            if ($username === '' || isset($usernames[$username])) {
                $username = ($username !== '' ? $username : 'hotspot').'-'.$fila->id;
            }
            $usernames[$username] = true;

            DB::table('servicio_hotspot')->where('id', $fila->id)->update([
                'cliente_id' => $clienteId,
                'slot_numero' => $slot,
                'username' => $username,
            ]);
        }

        if ($eliminar !== []) {
            DB::table('servicio_hotspot')->whereIn('id', $eliminar)->delete();
        }

        $this->reemplazarUniqueServicioIdPorFkSimple();

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('servicio_hotspot'));
            if (! $indexes->contains(fn (array $i) => in_array('cliente_id', $i['columns'], true) && in_array('slot_numero', $i['columns'], true) && ! empty($i['unique']))) {
                $table->unique(['cliente_id', 'slot_numero']);
            }
            if (! $indexes->contains(fn (array $i) => $i['columns'] === ['username'] && ! empty($i['unique']))) {
                $table->unique('username');
            }
        });

        $foreignNames = collect(Schema::getForeignKeys('servicio_hotspot'))->pluck('name');
        if (! $foreignNames->contains('servicio_hotspot_cliente_id_foreign')) {
            Schema::table('servicio_hotspot', function (Blueprint $table) {
                $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicio_hotspot')) {
            return;
        }

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $foreignNames = collect(Schema::getForeignKeys('servicio_hotspot'))->pluck('name');
            if ($foreignNames->contains('servicio_hotspot_cliente_id_foreign')) {
                $table->dropForeign('servicio_hotspot_cliente_id_foreign');
            }
        });

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('servicio_hotspot'));
            foreach ($indexes as $index) {
                if (empty($index['unique']) || ! empty($index['primary'])) {
                    continue;
                }
                $cols = $index['columns'];
                if ($cols === ['username'] || (in_array('cliente_id', $cols, true) && in_array('slot_numero', $cols, true))) {
                    $table->dropUnique($index['name']);
                }
            }
        });

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            if (Schema::hasColumn('servicio_hotspot', 'slot_numero')) {
                $table->dropColumn('slot_numero');
            }
            if (Schema::hasColumn('servicio_hotspot', 'cliente_id')) {
                $table->dropColumn('cliente_id');
            }
        });

        $indexes = collect(Schema::getIndexes('servicio_hotspot'));
        if (! $indexes->contains(fn (array $i) => $i['columns'] === ['servicio_id'] && ! empty($i['unique']))) {
            $this->dropForeignServicioId();
            Schema::table('servicio_hotspot', function (Blueprint $table) {
                $table->unique('servicio_id');
            });
            $this->addForeignServicioId();
        }
    }

    private function reemplazarUniqueServicioIdPorFkSimple(): void
    {
        $tieneUniqueServicio = collect(Schema::getIndexes('servicio_hotspot'))
            ->contains(fn (array $i) => $i['columns'] === ['servicio_id'] && ! empty($i['unique']));
        if (! $tieneUniqueServicio) {
            return;
        }

        $this->dropForeignServicioId();

        foreach (Schema::getIndexes('servicio_hotspot') as $index) {
            if (empty($index['unique']) || ! empty($index['primary'])) {
                continue;
            }
            if ($index['columns'] === ['servicio_id']) {
                Schema::table('servicio_hotspot', function (Blueprint $table) use ($index) {
                    $table->dropUnique($index['name']);
                });
            }
        }

        $this->addForeignServicioId();
    }

    private function dropForeignServicioId(): void
    {
        foreach (Schema::getForeignKeys('servicio_hotspot') as $fk) {
            if (in_array('servicio_id', $fk['columns'], true)) {
                Schema::table('servicio_hotspot', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk['name']);
                });
            }
        }
    }

    private function addForeignServicioId(): void
    {
        $yaExiste = collect(Schema::getForeignKeys('servicio_hotspot'))
            ->contains(fn (array $fk) => in_array('servicio_id', $fk['columns'], true));
        if ($yaExiste) {
            return;
        }

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->cascadeOnDelete();
        });
    }
};
