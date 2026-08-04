<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_app_config', function (Blueprint $table) {
            $table->id();
            $table->json('flags')->nullable();
            $table->json('pago_online')->nullable();
            $table->json('referidos')->nullable();
            $table->json('whatsapp')->nullable();
            $table->json('resumen')->nullable();
            $table->json('faqs')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_app_config');
    }
};
