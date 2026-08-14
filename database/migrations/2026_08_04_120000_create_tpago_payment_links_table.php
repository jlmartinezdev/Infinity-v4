<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpago_payment_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('factura_interna_id')->nullable()->index();
            $table->unsignedBigInteger('cliente_id')->index();
            $table->unsignedBigInteger('cobro_id')->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('description', 255);
            $table->string('reference_id', 80)->nullable()->index();
            $table->string('link_alias', 40)->nullable()->unique();
            $table->string('link_url', 1000)->nullable();
            $table->unsignedBigInteger('tpago_link_id')->nullable();
            $table->string('status', 40)->default('pending')->index();
            $table->string('ticket_number', 80)->nullable()->index();
            $table->string('authorization_code', 40)->nullable();
            $table->string('response_code', 10)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('factura_interna_id')
                ->references('id')
                ->on('factura_internas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpago_payment_links');
    }
};
