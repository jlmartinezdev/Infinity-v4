<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropForeign(['perfil_pppoe_id']);
            $table->dropColumn('perfil_pppoe_id');
        });
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->unsignedInteger('perfil_pppoe_id')->nullable()->after('plan_id');
            $table->foreign('perfil_pppoe_id')->references('perfil_pppoe_id')->on('perfiles_pppoe');
        });
    }
};
