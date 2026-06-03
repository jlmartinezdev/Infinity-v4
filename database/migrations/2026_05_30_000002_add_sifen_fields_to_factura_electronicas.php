<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->string('set_codigo_seguridad', 9)->nullable()->after('set_cdc')
                ->comment('dCodSeg — código seguridad CDC');
            $table->string('set_serie', 2)->nullable()->after('numero')
                ->comment('dSerieNum timbrado');
            $table->dateTime('set_fecha_emision_de')->nullable()->after('fecha_emision')
                ->comment('dFeEmiDE fecha/hora exacta del DE');
            $table->text('set_xml_respuesta')->nullable()->after('set_estado_envio')
                ->comment('Protocolo XML respuesta SIFEN');
        });
    }

    public function down(): void
    {
        Schema::table('factura_electronicas', function (Blueprint $table) {
            $table->dropColumn([
                'set_codigo_seguridad',
                'set_serie',
                'set_fecha_emision_de',
                'set_xml_respuesta',
            ]);
        });
    }
};
