<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cliente_id')) {
                $table->unsignedInteger('cliente_id')->nullable()->after('rol_id');
                $table->foreign('cliente_id')->references('cliente_id')->on('clientes')->nullOnDelete();
                $table->unique('cliente_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'cliente_id')) {
                $table->dropForeign(['cliente_id']);
                $table->dropUnique(['cliente_id']);
                $table->dropColumn('cliente_id');
            }
        });
    }
};
