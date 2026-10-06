<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $keep = DB::table('factura_electronica_lista_cliente')
            ->selectRaw('MIN(id) as id')
            ->groupBy('cliente_id')
            ->pluck('id');

        if ($keep->isNotEmpty()) {
            DB::table('factura_electronica_lista_cliente')
                ->whereNotIn('id', $keep->all())
                ->delete();
        }

        DB::table('factura_electronica_listas')
            ->whereNotIn('id', function ($q) {
                $q->select('lista_id')->from('factura_electronica_lista_cliente');
            })
            ->delete();

        Schema::table('factura_electronica_lista_cliente', function (Blueprint $table) {
            $table->unique('cliente_id');
        });
    }

    public function down(): void
    {
        Schema::table('factura_electronica_lista_cliente', function (Blueprint $table) {
            $table->dropUnique(['cliente_id']);
        });
    }
};
