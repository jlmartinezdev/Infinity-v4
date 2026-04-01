<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega user_id a sessions si no existe (Laravel 11+ lo requiere).
     */
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        if (! Schema::hasColumn('sessions', 'user_id')) {
            Schema::table('sessions', function (Blueprint $table) {
                $table->unsignedInteger('user_id')->nullable()->index()->after('id');
            });

            // Agregar FK solo si existe la tabla users con usuario_id
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'usuario_id')) {
                Schema::table('sessions', function (Blueprint $table) {
                    $table->foreign('user_id')->references('usuario_id')->on('users')->onDelete('cascade');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'user_id')) {
            return;
        }

        try {
            Schema::table('sessions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {
            // La FK puede no existir
        }
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn('user_id');
        });
    }
};
