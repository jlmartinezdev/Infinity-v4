<?php

namespace App\Services;

use App\Models\ServicioHotspot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class HotspotCuotaResetService
{
    /**
     * Deja en cero el consumo de la cuota de los hotspot con límite de GB.
     * Un mismo período (AAAA-MM) no se vuelve a cortar.
     *
     * @return array{ok: bool, skipped: bool, motivo: ?string, reseteados: int, ya_reseteados: int, sesiones: int, errores: int}
     */
    public function resetear(string $periodo): array
    {
        $periodo = trim($periodo);
        if (! preg_match('/^\d{4}-\d{2}$/', $periodo)) {
            throw new InvalidArgumentException('El período de la cuota debe ser AAAA-MM.');
        }

        $vacio = [
            'ok' => true,
            'skipped' => false,
            'motivo' => null,
            'reseteados' => 0,
            'ya_reseteados' => 0,
            'sesiones' => 0,
            'errores' => 0,
        ];

        $radius = app(RadiusHotspotSyncService::class);
        if (! $radius->operativo()) {
            return array_merge($vacio, [
                'skipped' => true,
                'motivo' => 'RADIUS no está activo o no responde.',
            ]);
        }

        $this->asegurarTabla($radius->connection());

        $cuentas = ServicioHotspot::query()
            ->with(['servicio', 'hotspotPerfil', 'router'])
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->whereHas('hotspotPerfil', fn ($q) => $q->where('cuota_gb', '>', 0))
            ->get();

        if ($cuentas->isEmpty()) {
            return array_merge($vacio, [
                'skipped' => true,
                'motivo' => 'No hay usuarios hotspot con cuota.',
            ]);
        }

        $porUsuario = [];
        foreach ($cuentas as $cuenta) {
            $username = trim((string) $cuenta->username);
            if ($username !== '') {
                $porUsuario[$username] = $cuenta;
            }
        }

        $periodos = $radius->periodosCorte(array_keys($porUsuario));
        $pendientes = [];
        $ya = 0;
        foreach ($porUsuario as $username => $cuenta) {
            if (($periodos[$username] ?? '') === $periodo) {
                $ya++;
                continue;
            }
            $pendientes[$username] = $cuenta;
        }

        if ($pendientes === []) {
            return array_merge($vacio, [
                'skipped' => true,
                'motivo' => 'La cuota de '.$periodo.' ya estaba en cero.',
                'ya_reseteados' => $ya,
            ]);
        }

        $usernames = array_keys($pendientes);
        $radius->cerrarContabilidadAbierta($usernames);
        $acumulado = $radius->consumoAcumulado($usernames);
        $bases = [];
        foreach ($usernames as $username) {
            $bases[$username] = $acumulado[$username] ?? 0;
        }
        $radius->registrarCortesCuota($bases, $periodo);

        $sesiones = 0;
        try {
            $sesiones = app(MikroTikService::class)->cerrarSesionesHotspot($usernames);
        } catch (Throwable $e) {
            Log::warning('[Hotspot] no se pudieron cerrar las sesiones al resetear la cuota', [
                'error' => $e->getMessage(),
            ]);
        }

        $errores = 0;
        foreach ($pendientes as $username => $cuenta) {
            $resultado = $radius->sync($cuenta, false);
            if (($resultado['success'] ?? false) !== true) {
                $errores++;
                Log::warning('[Hotspot] no se pudo reenviar la cuota', [
                    'username' => $username,
                    'error' => $resultado['error'] ?? 'error',
                ]);
            }
        }

        Log::info('[Hotspot] cuota reiniciada', [
            'periodo' => $periodo,
            'reseteados' => count($pendientes),
            'ya_reseteados' => $ya,
            'sesiones' => $sesiones,
            'errores' => $errores,
        ]);

        return [
            'ok' => $errores === 0,
            'skipped' => false,
            'motivo' => null,
            'reseteados' => count($pendientes),
            'ya_reseteados' => $ya,
            'sesiones' => $sesiones,
            'errores' => $errores,
        ];
    }

    public function asegurarTabla(string $connection): void
    {
        $schema = Schema::connection($connection);
        if ($schema->hasTable('hotspot_cuota_corte')) {
            return;
        }

        $schema->create('hotspot_cuota_corte', function (Blueprint $table) {
            $table->string('username', 64)->primary();
            $table->unsignedBigInteger('bytes_base')->default(0);
            $table->char('periodo', 7);
            $table->dateTime('cortado_en');
        });
    }
}
