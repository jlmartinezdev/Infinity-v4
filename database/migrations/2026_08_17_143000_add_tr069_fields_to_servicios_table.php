<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->string('tr069_serial', 64)->nullable()->after('mac_address');
            $table->string('tr069_product_class', 64)->nullable()->after('tr069_serial');
        });
    }

    public function down(): void
    {
        Schema::table('servicios', function (Blueprint $table) {
            $table->dropColumn(['tr069_serial', 'tr069_product_class']);
        });
    }
};
