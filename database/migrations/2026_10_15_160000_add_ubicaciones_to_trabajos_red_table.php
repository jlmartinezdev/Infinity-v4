<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trabajos_red')) {
            return;
        }

        Schema::table('trabajos_red', function (Blueprint $table) {
            if (! Schema::hasColumn('trabajos_red', 'ubicacion_desde')) {
                $table->string('ubicacion_desde', 180)->nullable()->after('descripcion');
            }
            if (! Schema::hasColumn('trabajos_red', 'url_ubicacion_desde')) {
                $table->string('url_ubicacion_desde', 500)->nullable()->after('ubicacion_desde');
            }
            if (! Schema::hasColumn('trabajos_red', 'ubicacion_hasta')) {
                $table->string('ubicacion_hasta', 180)->nullable()->after('url_ubicacion_desde');
            }
            if (! Schema::hasColumn('trabajos_red', 'url_ubicacion_hasta')) {
                $table->string('url_ubicacion_hasta', 500)->nullable()->after('ubicacion_hasta');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trabajos_red')) {
            return;
        }

        Schema::table('trabajos_red', function (Blueprint $table) {
            foreach (['ubicacion_desde', 'url_ubicacion_desde', 'ubicacion_hasta', 'url_ubicacion_hasta'] as $col) {
                if (Schema::hasColumn('trabajos_red', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
