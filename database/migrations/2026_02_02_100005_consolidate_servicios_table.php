<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migración consolidada: tabla servicios.
     * Reemplaza: rename_password_ppoe_to_password_pppoe, add_pedido_id, make_servicio_id_autoincrement.
     * 1) Renombrar password_ppoe -> password_pppoe si aplica.
     * 2) Añadir pedido_id si no existe.
     * 3) Convertir servicio_id a AUTO_INCREMENT si la PK sigue siendo compuesta (MySQL/MariaDB).
     */
    public function up(): void
    {
        // 1) Renombrar columna contraseña PPPoE
        if (Schema::hasColumn('servicios', 'password_ppoe')) {
            $driver = DB::getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE servicios CHANGE COLUMN password_ppoe password_pppoe VARCHAR(20) NULL');
            } else {
                DB::statement('ALTER TABLE servicios RENAME COLUMN password_ppoe TO password_pppoe');
            }
        }

        // 2) Columna pedido_id
        if (!Schema::hasColumn('servicios', 'pedido_id')) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->unsignedInteger('pedido_id')->nullable()->after('plan_id');
                $table->foreign('pedido_id')->references('pedido_id')->on('pedidos')->nullOnDelete();
            });
        }

        // 3) servicio_id auto-increment solo si la PK es compuesta (MySQL/MariaDB)
        $driver = Schema::getConnection()->getDriverName();
        $isMysql = in_array($driver, ['mysql', 'mariadb'], true);
        if (!$isMysql) {
            return;
        }

        $pkColumns = DB::select("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios' AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION");
        $pkNames = array_column($pkColumns, 'COLUMN_NAME');
        if ($pkNames !== ['cliente_id', 'servicio_id']) {
            return; // Ya es PK simple o no existe
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $fks = DB::select("SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'servicios' AND REFERENCED_TABLE_NAME IS NOT NULL");
        foreach ($fks as $fk) {
            DB::statement("ALTER TABLE servicios DROP FOREIGN KEY `" . $fk->CONSTRAINT_NAME . "`");
        }

        $tieneServicioIdNew = Schema::hasColumn('servicios', 'servicio_id_new');
        if (!$tieneServicioIdNew) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->unsignedBigInteger('servicio_id_new')->nullable()->after('cliente_id');
            });
            $rows = DB::table('servicios')->orderBy('cliente_id')->orderBy('servicio_id')->get(['cliente_id', 'servicio_id']);
            $n = 1;
            foreach ($rows as $row) {
                DB::table('servicios')
                    ->where('cliente_id', $row->cliente_id)
                    ->where('servicio_id', $row->servicio_id)
                    ->update(['servicio_id_new' => $n++]);
            }
            DB::statement('ALTER TABLE servicios MODIFY servicio_id_new BIGINT UNSIGNED NOT NULL');
        }

        $tienePedidoId = Schema::hasColumn('servicios', 'pedido_id');
        $cols = 'cliente_id, servicio_id_new AS servicio_id, pool_id, plan_id, ' . ($tienePedidoId ? 'pedido_id, ' : '')
            . 'ip, usuario_pppoe, password_pppoe, fecha_instalacion, fecha_cancelacion, estado, mac_address, pppoe_status, pppoe_synced';
        $colsList = $tienePedidoId
            ? 'cliente_id, servicio_id, pool_id, plan_id, pedido_id, ip, usuario_pppoe, password_pppoe, fecha_instalacion, fecha_cancelacion, estado, mac_address, pppoe_status, pppoe_synced'
            : 'cliente_id, servicio_id, pool_id, plan_id, ip, usuario_pppoe, password_pppoe, fecha_instalacion, fecha_cancelacion, estado, mac_address, pppoe_status, pppoe_synced';

        DB::statement("CREATE TABLE servicios_new (
            servicio_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cliente_id INT UNSIGNED NOT NULL,
            pool_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NOT NULL,
            " . ($tienePedidoId ? "pedido_id INT UNSIGNED NULL,
            " : "") . "
            ip VARCHAR(15) NULL,
            usuario_pppoe VARCHAR(100) NULL,
            password_pppoe VARCHAR(20) NULL,
            fecha_instalacion DATE NULL,
            fecha_cancelacion DATE NULL,
            estado CHAR(1) NULL,
            mac_address VARCHAR(20) NULL,
            pppoe_status VARCHAR(32) NULL,
            pppoe_synced TIMESTAMP NULL,
            INDEX servicios_new_cliente_id_index (cliente_id),
            INDEX servicios_new_plan_id_index (plan_id)
        )");

        DB::statement("INSERT INTO servicios_new (" . $colsList . ") SELECT " . $cols . " FROM servicios");
        Schema::drop('servicios');
        DB::statement('ALTER TABLE servicios_new RENAME TO servicios');

        Schema::table('servicios', function (Blueprint $table) {
            $table->foreign('cliente_id')->references('cliente_id')->on('clientes');
            $table->foreign('plan_id')->references('plan_id')->on('planes');
        });
        if ($tienePedidoId) {
            Schema::table('servicios', function (Blueprint $table) {
                $table->foreign('pedido_id')->references('pedido_id')->on('pedidos')->nullOnDelete();
            });
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    public function down(): void
    {
        // No revertir consolidado de servicios (down de cambios individuales es complejo)
    }
};
