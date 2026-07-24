<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_registro_otps', function (Blueprint $table) {
            $table->id();
            $table->string('telefono', 30)->index();
            $table->string('telefono_sufijo', 12)->index();
            $table->string('codigo', 10);
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_registro_otps');
    }
};
