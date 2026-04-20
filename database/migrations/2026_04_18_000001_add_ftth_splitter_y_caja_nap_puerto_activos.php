<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_naps', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_naps', 'splitter_segundo_nivel')) {
                $table->unsignedTinyInteger('splitter_segundo_nivel')
                    ->nullable()
                    ->after('tipo')
                    ->comment('8 = 1x8, 16 = 1x16 clientes en puertos FTTH');
            }
        });

        if (! Schema::hasTable('caja_nap_puerto_activos')) {
            Schema::create('caja_nap_puerto_activos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('caja_nap_id');
                $table->unsignedTinyInteger('numero_puerto');
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->decimal('potencia_cliente', 5, 3)->nullable();
                $table->timestamps();

                $table->foreign('caja_nap_id')->references('caja_nap_id')->on('caja_naps')->cascadeOnDelete();
                $table->foreign('servicio_id')->references('servicio_id')->on('servicios')->nullOnDelete();
                $table->unique(['caja_nap_id', 'numero_puerto']);
                $table->index('servicio_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('caja_nap_puerto_activos');

        Schema::table('caja_naps', function (Blueprint $table) {
            if (Schema::hasColumn('caja_naps', 'splitter_segundo_nivel')) {
                $table->dropColumn('splitter_segundo_nivel');
            }
        });
    }
};
