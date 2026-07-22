<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (! Schema::hasColumn('olts', 'mac_cli_comandos')) {
                $table->json('mac_cli_comandos')->nullable()->after('gestion_enable_password');
            }
        });
    }

    public function down(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (Schema::hasColumn('olts', 'mac_cli_comandos')) {
                $table->dropColumn('mac_cli_comandos');
            }
        });
    }
};
