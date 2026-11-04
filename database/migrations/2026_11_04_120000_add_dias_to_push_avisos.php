<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_avisos', function (Blueprint $table) {
            $table->unsignedTinyInteger('dias')->nullable()->after('destino');
        });
    }

    public function down(): void
    {
        Schema::table('push_avisos', function (Blueprint $table) {
            $table->dropColumn('dias');
        });
    }
};
