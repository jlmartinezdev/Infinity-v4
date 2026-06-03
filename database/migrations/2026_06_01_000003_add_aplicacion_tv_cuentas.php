<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            if (! Schema::hasColumn('tv_cuentas', 'aplicacion')) {
                $table->string('aplicacion', 20)->default('nebula')->after('nombre')
                    ->comment('nebula = 3 perfiles; lumix = 4 pantallas sin nombre de perfil');
            }
            if (! Schema::hasColumn('tv_cuentas', 'precio_pantalla_1')) {
                $table->decimal('precio_pantalla_1', 10, 2)->nullable()->after('precio_perfil_3');
                $table->decimal('precio_pantalla_2', 10, 2)->nullable()->after('precio_pantalla_1');
                $table->decimal('precio_pantalla_3', 10, 2)->nullable()->after('precio_pantalla_2');
                $table->decimal('precio_pantalla_4', 10, 2)->nullable()->after('precio_pantalla_3');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tv_cuentas', function (Blueprint $table) {
            $drops = [];
            foreach (['aplicacion', 'precio_pantalla_1', 'precio_pantalla_2', 'precio_pantalla_3', 'precio_pantalla_4'] as $col) {
                if (Schema::hasColumn('tv_cuentas', $col)) {
                    $drops[] = $col;
                }
            }
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
