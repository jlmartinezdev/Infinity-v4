<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'ultimo_acceso_at')) {
                $table->timestamp('ultimo_acceso_at')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('users', 'ultimo_acceso_ip')) {
                $table->string('ultimo_acceso_ip', 45)->nullable()->after('ultimo_acceso_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'ultimo_acceso_ip')) {
                $table->dropColumn('ultimo_acceso_ip');
            }
            if (Schema::hasColumn('users', 'ultimo_acceso_at')) {
                $table->dropColumn('ultimo_acceso_at');
            }
        });
    }
};
