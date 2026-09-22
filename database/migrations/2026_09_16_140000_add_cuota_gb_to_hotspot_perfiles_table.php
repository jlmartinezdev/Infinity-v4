<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotspot_perfiles', function (Blueprint $table) {
            if (! Schema::hasColumn('hotspot_perfiles', 'cuota_gb')) {
                $table->unsignedInteger('cuota_gb')->nullable()->after('rate_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotspot_perfiles', function (Blueprint $table) {
            if (Schema::hasColumn('hotspot_perfiles', 'cuota_gb')) {
                $table->dropColumn('cuota_gb');
            }
        });
    }
};
