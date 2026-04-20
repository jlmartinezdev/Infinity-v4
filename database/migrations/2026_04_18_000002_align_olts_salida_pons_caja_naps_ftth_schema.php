<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        // --- caja_naps: columnas diagrama + enlace a salida PON ---
        Schema::table('caja_naps', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_naps', 'salida_pon_id')) {
                $table->unsignedInteger('salida_pon_id')->nullable()->after('nodo_id');
            }
            if (! Schema::hasColumn('caja_naps', 'splitter_primer_nivel')) {
                $table->string('splitter_primer_nivel', 10)->nullable()->after('lon');
            }
            if (! Schema::hasColumn('caja_naps', 'potencia_salida')) {
                $table->decimal('potencia_salida', 5, 3)->nullable()->after('splitter_primer_nivel');
            }
        });

        if (Schema::hasColumn('caja_naps', 'notas') && ! Schema::hasColumn('caja_naps', 'nota')) {
            DB::statement('ALTER TABLE caja_naps CHANGE COLUMN notas nota TEXT NULL');
        }

        // Enlazar caja → salida (una salida por caja según modelo): primera salida que apuntaba a la caja
        if (Schema::hasColumn('salida_pons', 'caja_nap_id')) {
            DB::statement('
                UPDATE caja_naps c
                INNER JOIN (
                    SELECT caja_nap_id, MIN(salida_pon_id) AS sid
                    FROM salida_pons
                    WHERE caja_nap_id IS NOT NULL
                    GROUP BY caja_nap_id
                ) x ON c.caja_nap_id = x.caja_nap_id
                SET c.salida_pon_id = x.sid
                WHERE c.salida_pon_id IS NULL
            ');
        }

        // --- salida_pons: olt_id, tipo_modulo, potencia_salida, puerto_olt, nota; quitar caja_nap / olt_puerto ---
        Schema::table('salida_pons', function (Blueprint $table) {
            if (! Schema::hasColumn('salida_pons', 'olt_id')) {
                $table->unsignedInteger('olt_id')->nullable()->after('salida_pon_id');
            }
            if (! Schema::hasColumn('salida_pons', 'tipo_modulo')) {
                $table->string('tipo_modulo', 20)->nullable()->after('olt_id');
            }
            if (! Schema::hasColumn('salida_pons', 'potencia_salida')) {
                $table->decimal('potencia_salida', 7, 3)->nullable()->after('tipo_modulo');
            }
        });

        if (Schema::hasColumn('salida_pons', 'olt_puerto_id')) {
            DB::statement('
                UPDATE salida_pons sp
                INNER JOIN olt_puertos op ON sp.olt_puerto_id = op.olt_puerto_id
                SET sp.olt_id = op.olt_id
                WHERE sp.olt_puerto_id IS NOT NULL AND sp.olt_id IS NULL
            ');
        }

        if (Schema::hasColumn('salida_pons', 'puerto') && ! Schema::hasColumn('salida_pons', 'puerto_olt')) {
            DB::statement('ALTER TABLE salida_pons CHANGE COLUMN puerto puerto_olt TINYINT UNSIGNED NOT NULL DEFAULT 1');
        }

        if (Schema::hasColumn('salida_pons', 'notas') && ! Schema::hasColumn('salida_pons', 'nota')) {
            DB::statement('ALTER TABLE salida_pons CHANGE COLUMN notas nota TEXT NULL');
        }

        if (Schema::hasColumn('salida_pons', 'olt_id')) {
            DB::statement('
                UPDATE salida_pons sp
                INNER JOIN olts o ON sp.olt_id = o.olt_id
                SET sp.nodo_id = o.nodo_id
                WHERE sp.olt_id IS NOT NULL
            ');
        }

        Schema::table('salida_pons', function (Blueprint $table) {
            if (Schema::hasColumn('salida_pons', 'caja_nap_id')) {
                try {
                    $table->dropForeign(['caja_nap_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('caja_nap_id');
            }
            if (Schema::hasColumn('salida_pons', 'olt_puerto_id')) {
                try {
                    $table->dropForeign(['olt_puerto_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('olt_puerto_id');
            }
        });

        Schema::table('salida_pons', function (Blueprint $table) {
            if (Schema::hasColumn('salida_pons', 'olt_id')) {
                try {
                    $table->foreign('olt_id')->references('olt_id')->on('olts')->nullOnDelete();
                } catch (\Throwable) {
                }
            }
        });

        Schema::table('caja_naps', function (Blueprint $table) {
            if (Schema::hasColumn('caja_naps', 'salida_pon_id')) {
                try {
                    $table->foreign('salida_pon_id')->references('salida_pon_id')->on('salida_pons')->nullOnDelete();
                } catch (\Throwable) {
                }
            }
        });

        // --- olts: marca, codigo, cantidad_puerto; quitar olt_marca_id ---
        Schema::table('olts', function (Blueprint $table) {
            if (! Schema::hasColumn('olts', 'marca')) {
                $table->string('marca', 100)->nullable()->after('nodo_id');
            }
            if (! Schema::hasColumn('olts', 'codigo')) {
                $table->string('codigo', 50)->nullable()->after('marca');
            }
        });

        if (Schema::hasColumn('olts', 'olt_marca_id') && Schema::hasColumn('olts', 'marca')) {
            DB::statement('
                UPDATE olts o
                INNER JOIN olt_marcas m ON o.olt_marca_id = m.olt_marca_id
                SET o.marca = m.nombre
                WHERE o.marca IS NULL OR o.marca = ""
            ');
            DB::statement("UPDATE olts SET codigo = CONCAT('OLT-', olt_id) WHERE codigo IS NULL OR codigo = ''");
        }

        if (Schema::hasColumn('olts', 'cantidad_puertos') && ! Schema::hasColumn('olts', 'cantidad_puerto')) {
            DB::statement('ALTER TABLE olts CHANGE COLUMN cantidad_puertos cantidad_puerto SMALLINT UNSIGNED NOT NULL DEFAULT 8');
        }

        if (Schema::hasColumn('olts', 'modelo')) {
            DB::statement('ALTER TABLE olts MODIFY modelo VARCHAR(50) NULL');
        }

        Schema::table('olts', function (Blueprint $table) {
            if (Schema::hasColumn('olts', 'olt_marca_id')) {
                try {
                    $table->dropForeign(['olt_marca_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('olt_marca_id');
            }
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('olts', function (Blueprint $table) {
            if (! Schema::hasColumn('olts', 'olt_marca_id')) {
                $table->unsignedInteger('olt_marca_id')->nullable()->after('nodo_id');
            }
        });

        if (Schema::hasColumn('olts', 'cantidad_puerto') && ! Schema::hasColumn('olts', 'cantidad_puertos')) {
            DB::statement('ALTER TABLE olts CHANGE COLUMN cantidad_puerto cantidad_puertos SMALLINT UNSIGNED NOT NULL DEFAULT 8');
        }

        if (Schema::hasColumn('olts', 'marca')) {
            Schema::table('olts', function (Blueprint $table) {
                $table->dropColumn(['marca', 'codigo']);
            });
        }

        Schema::table('caja_naps', function (Blueprint $table) {
            if (Schema::hasColumn('caja_naps', 'salida_pon_id')) {
                try {
                    $table->dropForeign(['salida_pon_id']);
                } catch (\Throwable) {
                }
                $table->dropColumn('salida_pon_id');
            }
            if (Schema::hasColumn('caja_naps', 'splitter_primer_nivel')) {
                $table->dropColumn('splitter_primer_nivel');
            }
            if (Schema::hasColumn('caja_naps', 'potencia_salida')) {
                $table->dropColumn('potencia_salida');
            }
        });

        if (Schema::hasColumn('caja_naps', 'nota') && ! Schema::hasColumn('caja_naps', 'notas')) {
            DB::statement('ALTER TABLE caja_naps CHANGE COLUMN nota notas TEXT NULL');
        }

        Schema::table('salida_pons', function (Blueprint $table) {
            if (Schema::hasColumn('salida_pons', 'olt_id')) {
                try {
                    $table->dropForeign(['olt_id']);
                } catch (\Throwable) {
                }
            }
            if (! Schema::hasColumn('salida_pons', 'caja_nap_id')) {
                $table->unsignedInteger('caja_nap_id')->nullable()->after('nodo_id');
                $table->foreign('caja_nap_id')->references('caja_nap_id')->on('caja_naps')->nullOnDelete();
            }
            if (! Schema::hasColumn('salida_pons', 'olt_puerto_id')) {
                $table->unsignedInteger('olt_puerto_id')->nullable()->after('caja_nap_id');
                $table->foreign('olt_puerto_id')->references('olt_puerto_id')->on('olt_puertos')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('salida_pons', 'puerto_olt') && ! Schema::hasColumn('salida_pons', 'puerto')) {
            DB::statement('ALTER TABLE salida_pons CHANGE COLUMN puerto_olt puerto TINYINT UNSIGNED NOT NULL DEFAULT 1');
        }

        if (Schema::hasColumn('salida_pons', 'nota') && ! Schema::hasColumn('salida_pons', 'notas')) {
            DB::statement('ALTER TABLE salida_pons CHANGE COLUMN nota notas TEXT NULL');
        }

        Schema::table('salida_pons', function (Blueprint $table) {
            if (Schema::hasColumn('salida_pons', 'tipo_modulo')) {
                $table->dropColumn('tipo_modulo');
            }
            if (Schema::hasColumn('salida_pons', 'potencia_salida')) {
                $table->dropColumn('potencia_salida');
            }
            if (Schema::hasColumn('salida_pons', 'olt_id')) {
                $table->dropColumn('olt_id');
            }
        });
    }
};
