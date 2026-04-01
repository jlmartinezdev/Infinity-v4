<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('sessions')) {
            // Si existe la columna usuario_id, renombrarla usando SQL directo
            if (Schema::hasColumn('sessions', 'usuario_id') && !Schema::hasColumn('sessions', 'user_id')) {
                // Primero eliminar la clave foránea si existe
                try {
                    DB::statement('ALTER TABLE `sessions` DROP FOREIGN KEY `sessions_usuario_id_foreign`');
                } catch (\Exception $e) {
                    // La clave foránea puede no existir o tener otro nombre
                }
                // Renombrar la columna
                DB::statement('ALTER TABLE `sessions` CHANGE `usuario_id` `user_id` INT UNSIGNED NULL');
                // Agregar la nueva clave foránea
                DB::statement('ALTER TABLE `sessions` ADD CONSTRAINT `sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`usuario_id`) ON DELETE CASCADE');
            } elseif (!Schema::hasColumn('sessions', 'user_id')) {
                // Si no existe ninguna de las dos, crear user_id
                Schema::table('sessions', function (Blueprint $table) {
                    $table->unsignedInteger('user_id')->nullable()->index()->after('id');
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
        if (Schema::hasTable('sessions') && Schema::hasColumn('sessions', 'user_id')) {
            if (!Schema::hasColumn('sessions', 'usuario_id')) {
                DB::statement('ALTER TABLE `sessions` CHANGE `user_id` `usuario_id` BIGINT UNSIGNED NULL');
            }
        }
    }
};
