<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (! Schema::hasColumn('servicios', 'acuerdo_tipo')) {
                $table->string('acuerdo_tipo', 20)->default('ninguno')->after('precio_app');
            }
            if (! Schema::hasColumn('servicios', 'acuerdo_meses')) {
                $table->unsignedTinyInteger('acuerdo_meses')->nullable()->after('acuerdo_tipo');
            }
            if (! Schema::hasColumn('servicios', 'acuerdo_desde')) {
                $table->date('acuerdo_desde')->nullable()->after('acuerdo_meses');
            }
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            if (Schema::hasColumn('servicios', 'acuerdo_desde')) {
                $table->dropColumn('acuerdo_desde');
            }
            if (Schema::hasColumn('servicios', 'acuerdo_meses')) {
                $table->dropColumn('acuerdo_meses');
            }
            if (Schema::hasColumn('servicios', 'acuerdo_tipo')) {
                $table->dropColumn('acuerdo_tipo');
            }
        });
    }
};
