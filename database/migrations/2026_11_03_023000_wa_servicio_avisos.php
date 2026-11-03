<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_servicio_avisos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('servicio_id')->unique();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->string('telefono', 32);
            $table->string('nombre_pila', 80)->nullable();
            $table->string('apellido', 120)->nullable();
            $table->boolean('sandbox')->default(false);
            $table->timestamp('notificado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_servicio_avisos');
    }
};
