<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->unsignedInteger('usuario_id')->nullable()->change();
            $table->foreign('usuario_id')->references('usuario_id')->on('users')->onDelete('set null');
        });
        if (! Schema::hasColumn('auditoria', 'registro_key')) {
            Schema::table('auditoria', function (Blueprint $table) {
                $table->string('registro_key', 120)->nullable()->after('registro_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('auditoria', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->unsignedInteger('usuario_id')->nullable(false)->change();
            $table->foreign('usuario_id')->references('usuario_id')->on('users');
        });
        if (Schema::hasColumn('auditoria', 'registro_key')) {
            Schema::table('auditoria', function (Blueprint $table) {
                $table->dropColumn('registro_key');
            });
        }
    }
};
