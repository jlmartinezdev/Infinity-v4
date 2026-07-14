<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olts', function (Blueprint $table) {
            if (! Schema::hasColumn('olts', 'gestion_usuario')) {
                $table->string('gestion_usuario', 64)->nullable()->after('ip');
            }
            if (! Schema::hasColumn('olts', 'gestion_password')) {
                $table->string('gestion_password', 255)->nullable()->after('gestion_usuario');
            }
            if (! Schema::hasColumn('olts', 'gestion_protocolo')) {
                $table->string('gestion_protocolo', 10)->default('telnet')->after('gestion_password');
            }
            if (! Schema::hasColumn('olts', 'gestion_puerto')) {
                $table->unsignedSmallInteger('gestion_puerto')->nullable()->after('gestion_protocolo');
            }
            if (! Schema::hasColumn('olts', 'gestion_enable_password')) {
                $table->string('gestion_enable_password', 255)->nullable()->after('gestion_puerto');
            }
            if (! Schema::hasColumn('olts', 'onus_synced_at')) {
                $table->timestamp('onus_synced_at')->nullable()->after('notas');
            }
            if (! Schema::hasColumn('olts', 'onus_sync_error')) {
                $table->text('onus_sync_error')->nullable()->after('onus_synced_at');
            }
        });

        if (! Schema::hasTable('olt_onus')) {
            Schema::create('olt_onus', function (Blueprint $table) {
                $table->increments('olt_onu_id');
                $table->unsignedInteger('olt_id');
                $table->unsignedTinyInteger('pon_slot')->default(0);
                $table->unsignedTinyInteger('pon_port');
                $table->string('pon_key', 16);
                $table->unsignedSmallInteger('onu_index');
                $table->string('serial', 64)->nullable();
                $table->string('vendor_id', 32)->nullable();
                $table->string('modelo', 64)->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->string('estado', 32)->default('unknown');
                $table->decimal('rx_power_dbm', 8, 2)->nullable();
                $table->decimal('tx_power_dbm', 8, 2)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->foreign('olt_id')->references('olt_id')->on('olts')->cascadeOnDelete();
                $table->unique(['olt_id', 'pon_key', 'onu_index'], 'olt_onus_olt_pon_onu_unique');
                $table->index(['olt_id', 'estado']);
                $table->index(['olt_id', 'serial']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_onus');

        Schema::table('olts', function (Blueprint $table) {
            $cols = [
                'gestion_usuario',
                'gestion_password',
                'gestion_protocolo',
                'gestion_puerto',
                'gestion_enable_password',
                'onus_synced_at',
                'onus_sync_error',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('olts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
