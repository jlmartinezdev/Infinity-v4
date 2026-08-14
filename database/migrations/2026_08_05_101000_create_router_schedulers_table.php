<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('router_schedulers', function (Blueprint $table) {
            $table->increments('router_scheduler_id');
            $table->string('nombre', 128)->unique();
            $table->text('on_event')->nullable();
            $table->string('start_date', 32)->nullable();
            $table->string('start_time', 32)->nullable();
            $table->string('interval', 64)->nullable();
            $table->string('owner', 64)->nullable();
            $table->string('policy', 255)->nullable();
            $table->boolean('disabled')->default(false);
            $table->string('comment', 255)->nullable();
            $table->unsignedInteger('router_origen_id')->nullable();
            $table->string('notas', 255)->nullable();
            $table->timestamp('leido_en')->nullable();
            $table->timestamp('sincronizado_en')->nullable();
            $table->timestamps();

            $table->foreign('router_origen_id')
                ->references('router_id')
                ->on('routers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('router_schedulers');
    }
};
