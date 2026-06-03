<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sifen_configuracion', function (Blueprint $table) {
            $table->id();

            $table->string('ruc', 8)->comment('RUC emisor sin DV');
            $table->unsignedTinyInteger('dv_ruc');
            $table->unsignedTinyInteger('tipo_contribuyente')->default(2)
                ->comment('1=Persona física, 2=Persona jurídica');

            $table->string('razon_social', 255);
            $table->string('nombre_fantasia', 255)->nullable();

            $table->string('numero_timbrado', 8);
            $table->unsignedSmallInteger('establecimiento')->default(1);
            $table->unsignedSmallInteger('punto_expedicion')->default(1);
            $table->date('timbrado_vigencia_desde');
            $table->date('timbrado_vigencia_hasta')->nullable();

            $table->string('codigo_actividad_economica', 10)->nullable();
            $table->string('descripcion_actividad_economica', 255)->nullable();

            $table->string('direccion', 255);
            $table->string('numero_casa', 10)->default('0');
            $table->unsignedSmallInteger('departamento')->default(1);
            $table->string('departamento_descripcion', 50)->default('CAPITAL');
            $table->unsignedSmallInteger('distrito')->default(1);
            $table->string('distrito_descripcion', 50)->default('ASUNCION (DISTRITO)');
            $table->unsignedSmallInteger('ciudad')->default(1);
            $table->string('ciudad_descripcion', 50)->default('ASUNCION (DISTRITO)');

            $table->string('telefono', 20);
            $table->string('email', 100);

            $table->string('csc_id', 4)->nullable()->comment('IdCSC para QR');
            $table->string('csc_token', 32)->nullable()->comment('CSC alfanumérico 32 chars');

            $table->unsignedInteger('ultimo_numero_factura')->default(0);
            $table->string('serie_actual', 2)->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sifen_configuracion');
    }
};
