<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (! Schema::hasColumn('routers', 'webhook_token')) {
                $table->string('webhook_token', 64)->nullable()->after('password');
                $table->unique('webhook_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            if (Schema::hasColumn('routers', 'webhook_token')) {
                $table->dropUnique(['webhook_token']);
                $table->dropColumn('webhook_token');
            }
        });
    }
};
