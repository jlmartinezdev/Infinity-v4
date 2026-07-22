<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('router_ip_pools', function (Blueprint $table) {
            $table->unsignedInteger('olt_id')->nullable()->after('router_id');
            $table->foreign('olt_id')
                ->references('olt_id')
                ->on('olts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('router_ip_pools', function (Blueprint $table) {
            $table->dropForeign(['olt_id']);
            $table->dropColumn('olt_id');
        });
    }
};
