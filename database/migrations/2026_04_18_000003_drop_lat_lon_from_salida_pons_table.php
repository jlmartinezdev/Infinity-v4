<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salida_pons', function (Blueprint $table) {
            $drop = array_values(array_filter([
                Schema::hasColumn('salida_pons', 'lat') ? 'lat' : null,
                Schema::hasColumn('salida_pons', 'lon') ? 'lon' : null,
            ]));

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }

    public function down(): void
    {
        Schema::table('salida_pons', function (Blueprint $table) {
            if (! Schema::hasColumn('salida_pons', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('salida_pons', 'lon')) {
                $table->decimal('lon', 10, 7)->nullable();
            }
        });
    }
};
