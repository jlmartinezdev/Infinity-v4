<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicios') || Schema::hasColumn('servicios', 'url_ubicacion')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->string('url_ubicacion', 500)->nullable()->after('alias');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicios') || ! Schema::hasColumn('servicios', 'url_ubicacion')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('url_ubicacion');
        });
    }
};
