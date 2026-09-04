<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('servicios', 'alias')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->string('alias', 80)->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('servicios', 'alias')) {
            return;
        }

        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn('alias');
        });
    }
};
