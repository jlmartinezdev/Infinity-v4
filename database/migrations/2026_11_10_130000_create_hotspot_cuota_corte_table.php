<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            DB::connection((string) config('radius.connection', 'radius'))->getPdo();
        } catch (Throwable) {
            if (config('radius.enabled')) {
                throw new RuntimeException('RADIUS_ENABLED=true pero no se pudo conectar a la base radius.');
            }

            return;
        }

        app(\App\Services\HotspotCuotaResetService::class)
            ->asegurarTabla((string) config('radius.connection', 'radius'));
    }

    public function down(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        try {
            $schema = \Illuminate\Support\Facades\Schema::connection((string) config('radius.connection', 'radius'));
        } catch (Throwable) {
            return;
        }

        $schema->dropIfExists('hotspot_cuota_corte');
    }
};
