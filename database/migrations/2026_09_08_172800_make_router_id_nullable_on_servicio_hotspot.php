<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('servicio_hotspot') || ! Schema::hasColumn('servicio_hotspot', 'router_id')) {
            return;
        }

        $this->dropForeignRouter();

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $table->unsignedInteger('router_id')->nullable()->change();
        });

        $this->addForeignRouter();
    }

    public function down(): void
    {
        if (! Schema::hasTable('servicio_hotspot') || ! Schema::hasColumn('servicio_hotspot', 'router_id')) {
            return;
        }

        $this->dropForeignRouter();

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $table->unsignedInteger('router_id')->nullable(false)->change();
        });

        $this->addForeignRouter();
    }

    private function dropForeignRouter(): void
    {
        foreach (Schema::getForeignKeys('servicio_hotspot') as $fk) {
            if (in_array('router_id', $fk['columns'], true)) {
                Schema::table('servicio_hotspot', function (Blueprint $table) use ($fk) {
                    $table->dropForeign($fk['name']);
                });
            }
        }
    }

    private function addForeignRouter(): void
    {
        $yaExiste = collect(Schema::getForeignKeys('servicio_hotspot'))
            ->contains(fn (array $fk) => in_array('router_id', $fk['columns'], true));
        if ($yaExiste) {
            return;
        }

        Schema::table('servicio_hotspot', function (Blueprint $table) {
            $table->foreign('router_id')->references('router_id')->on('routers')->cascadeOnDelete();
        });
    }
};
