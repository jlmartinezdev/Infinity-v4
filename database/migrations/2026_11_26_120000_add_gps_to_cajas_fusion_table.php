<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cajas_fusion', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('ubicacion');
            $table->decimal('lon', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('cajas_fusion', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lon']);
        });
    }
};
