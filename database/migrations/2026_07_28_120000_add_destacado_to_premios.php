<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            if (! Schema::hasColumn('premios', 'destacado')) {
                $table->boolean('destacado')->default(false)->after('orden');
                $table->index(['destacado', 'orden']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('premios', function (Blueprint $table) {
            if (Schema::hasColumn('premios', 'destacado')) {
                $table->dropIndex(['destacado', 'orden']);
                $table->dropColumn('destacado');
            }
        });
    }
};
