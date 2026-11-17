<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_planes', function (Blueprint $table) {
            $table->unsignedInteger('gigas')->nullable()->change();
        });

        Schema::table('hotspot_ventas', function (Blueprint $table) {
            $table->unsignedInteger('gigas')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_planes', function (Blueprint $table) {
            $table->unsignedInteger('gigas')->nullable(false)->change();
        });

        Schema::table('hotspot_ventas', function (Blueprint $table) {
            $table->unsignedInteger('gigas')->nullable(false)->change();
        });
    }
};
