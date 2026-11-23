<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('canjes', function (Blueprint $table) {
            $table->unsignedBigInteger('factura_interna_id')->nullable()->after('premio_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('canjes', function (Blueprint $table) {
            $table->dropIndex(['factura_interna_id']);
            $table->dropColumn('factura_interna_id');
        });
    }
};
