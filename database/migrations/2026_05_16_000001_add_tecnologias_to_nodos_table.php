<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodos', function (Blueprint $table) {
            $table->boolean('tecnologia_gpon')->default(true)->after('ciudad');
            $table->boolean('tecnologia_wireless')->default(true)->after('tecnologia_gpon');
        });
    }

    public function down(): void
    {
        Schema::table('nodos', function (Blueprint $table) {
            $table->dropColumn(['tecnologia_gpon', 'tecnologia_wireless']);
        });
    }
};
