<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'cedula')) {
                $table->string('cedula', 20)->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('users', 'cargo')) {
                $table->string('cargo', 120)->nullable()->after('cedula');
            }
            if (! Schema::hasColumn('users', 'salario_basico')) {
                $table->unsignedBigInteger('salario_basico')->nullable()->after('cargo');
            }
            if (! Schema::hasColumn('users', 'banco')) {
                $table->string('banco', 80)->nullable()->after('salario_basico');
            }
            if (! Schema::hasColumn('users', 'cuenta_bancaria')) {
                $table->string('cuenta_bancaria', 60)->nullable()->after('banco');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['cedula', 'cargo', 'salario_basico', 'banco', 'cuenta_bancaria'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
