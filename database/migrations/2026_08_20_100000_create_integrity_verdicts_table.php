<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrity_verdicts', function (Blueprint $table) {
            $table->id();
            $table->string('device_name', 64)->nullable();
            $table->string('nonce', 128)->nullable()->index();
            $table->string('package_name', 128)->nullable();
            $table->string('app_recognition_verdict', 64)->nullable();
            $table->string('device_recognition_verdict', 255)->nullable();
            $table->string('app_licensing_verdict', 64)->nullable();
            $table->boolean('ok')->default(false);
            $table->string('error', 255)->nullable();
            $table->boolean('enforced')->default(false);
            $table->boolean('blocked')->default(false);
            $table->string('ip', 45)->nullable();
            $table->json('payload_summary')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->index(['ok', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrity_verdicts');
    }
};
