<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteo_resenas', function (Blueprint $table) {
            $table->string('nombre_google', 80)->nullable()->after('telefono');
            $table->timestamp('enviada_at')->nullable()->after('nombre_google');
        });
    }

    public function down(): void
    {
        Schema::table('sorteo_resenas', function (Blueprint $table) {
            $table->dropColumn(['nombre_google', 'enviada_at']);
        });
    }
};
