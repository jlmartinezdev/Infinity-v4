<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_zona_avisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('incidente_id')->index();
            $table->unsignedBigInteger('router_id')->nullable()->index();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('telefono', 32)->index();
            $table->string('nombre_pila', 80)->nullable();
            $table->string('apellido', 120)->nullable();
            $table->boolean('sandbox')->default(false);
            $table->timestamp('notificado_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['incidente_id', 'telefono']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_zona_avisos');
    }
};
