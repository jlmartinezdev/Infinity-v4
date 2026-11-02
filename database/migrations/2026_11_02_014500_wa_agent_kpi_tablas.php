<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_agent_eventos', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ocurrido_at')->index();
            $table->string('telefono', 32)->nullable()->index();
            $table->unsignedBigInteger('cliente_id')->nullable()->index();
            $table->unsignedBigInteger('mensaje_id')->nullable()->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->unsignedBigInteger('pedido_id')->nullable()->index();
            $table->unsignedBigInteger('cobro_id')->nullable()->index();
            $table->string('tipo', 40)->index();
            $table->string('modo', 64)->nullable()->index();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->boolean('sandbox')->default(false)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sandbox', 'ocurrido_at', 'tipo']);
        });

        Schema::create('wa_agent_uso_modelo', function (Blueprint $table) {
            $table->id();
            $table->timestamp('ocurrido_at')->index();
            $table->string('proveedor', 32)->index();
            $table->string('modelo', 80)->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('llamadas')->default(1);
            $table->decimal('costo_usd_estimado', 12, 6)->default(0);
            $table->unsignedBigInteger('mensaje_id')->nullable()->index();
            $table->boolean('sandbox')->default(false)->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['sandbox', 'ocurrido_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_agent_uso_modelo');
        Schema::dropIfExists('wa_agent_eventos');
    }
};
