<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recibo_secuencias', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('ultimo_valor');
        });

        $base = 10010000000; // siguiente recibo será 10010000001 → 001-001-0000001
        $ultimo = DB::table('cobros')->orderByDesc('id')->first();
        if ($ultimo && !empty($ultimo->numero_recibo)) {
            $nr = (string) $ultimo->numero_recibo;
            if (preg_match('/^\d{3}-\d{3}-\d{7}$/', $nr)) {
                $base = max($base, (int) str_replace('-', '', $nr));
            } else {
                $n = (int) preg_replace('/\D/', '', $nr);
                $base = max($base, $n);
            }
        }

        DB::table('recibo_secuencias')->insert([
            'id' => 1,
            'ultimo_valor' => $base,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('recibo_secuencias');
    }
};
