<?php

namespace App\Services\Staff;

use App\Models\StaffRecorridoAjuste;
use App\Models\StaffRecorridoPunto;
use App\Models\StaffRecorridoResumen;
use App\Models\User;
use App\Support\StaffRecorridoGeometria;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class StaffRecorridoService
{
    /**
     * Guarda un punto del día solo si el técnico se movió lo bastante
     * y el GPS no viene demasiado impreciso. El primer punto del día siempre entra.
     */
    public function registrarPunto(
        int $usuarioId,
        float $lat,
        float $lng,
        ?float $accuracy,
        ?int $visitaId,
        DateTimeInterface $reportedAt,
    ): bool {
        $cuando = Carbon::parse($reportedAt);
        $ultimo = StaffRecorridoPunto::query()
            ->where('usuario_id', $usuarioId)
            ->where('reported_at', '>=', $cuando->copy()->startOfDay())
            ->orderByDesc('reported_at')
            ->orderByDesc('id')
            ->first();

        $prev = $ultimo ? ['lat' => (float) $ultimo->lat, 'lng' => (float) $ultimo->lng] : null;
        if (! StaffRecorridoGeometria::debeGuardar($prev, $lat, $lng, $accuracy)) {
            return false;
        }

        StaffRecorridoPunto::query()->create([
            'usuario_id' => $usuarioId,
            'lat' => $lat,
            'lng' => $lng,
            'accuracy' => $accuracy,
            'visita_id' => $visitaId,
            'reported_at' => $cuando,
        ]);

        return true;
    }

    /**
     * Trazo del día. Hoy: puntos en vivo. Días anteriores: solo el resumen guardado.
     *
     * @return array{modo: string, fecha: string, tecnicos: list<array<string, mixed>>}
     */
    public function payloadFecha(Carbon $fecha): array
    {
        $inicio = $fecha->copy()->startOfDay();
        $fin = $fecha->copy()->endOfDay();
        $esHoy = $inicio->isSameDay(now());

        if (! $esHoy) {
            $guardados = StaffRecorridoResumen::query()
                ->with(['usuario:usuario_id,name'])
                ->whereDate('fecha', $inicio->toDateString())
                ->get();
            if ($guardados->isNotEmpty()) {
                $tecnicos = [];
                foreach ($guardados as $resumen) {
                    $tecnicos[] = $this->fila((int) $resumen->usuario_id, $resumen->usuario?->name, [
                        'distancia_m' => (float) $resumen->distancia_metros,
                        'puntos_n' => (int) $resumen->puntos_n,
                        'visitas' => (int) $resumen->visitas,
                        'inicio' => $resumen->inicio_at?->utc()->toIso8601String(),
                        'fin' => $resumen->fin_at?->utc()->toIso8601String(),
                        'traza' => is_array($resumen->traza) ? $resumen->traza : [],
                    ]);
                }

                return [
                    'modo' => 'resumen',
                    'fecha' => $inicio->toDateString(),
                    'tecnicos' => $this->aplicarAjustes($inicio->toDateString(), $this->ordenar($tecnicos)),
                ];
            }
        }

        return [
            'modo' => 'puntos',
            'fecha' => $inicio->toDateString(),
            'tecnicos' => $this->aplicarAjustes($inicio->toDateString(), $this->ordenar($this->tecnicosDesdePuntos($inicio, $fin))),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tecnicosDesdePuntos(Carbon $inicio, Carbon $fin): array
    {
        $puntos = StaffRecorridoPunto::query()
            ->with(['usuario:usuario_id,name'])
            ->whereBetween('reported_at', [$inicio, $fin])
            ->orderBy('reported_at')
            ->get()
            ->groupBy('usuario_id');

        $tecnicos = [];
        foreach ($puntos as $usuarioId => $items) {
            $crudos = $items->map(fn (StaffRecorridoPunto $p) => [
                'lat' => (float) $p->lat,
                'lng' => (float) $p->lng,
                'at' => $p->reported_at,
                'visita_id' => $p->visita_id,
            ])->all();
            $resumen = StaffRecorridoGeometria::armarResumen($crudos, false);
            if ($resumen === null) {
                continue;
            }
            if (count($resumen['traza']) > 1500) {
                $resumen['traza'] = StaffRecorridoGeometria::simplificar($resumen['traza'], 15, 400);
            }
            $tecnicos[] = $this->fila((int) $usuarioId, $items->first()?->usuario?->name, $resumen);
        }

        return $tecnicos;
    }

    /**
     * Cierra cada día anterior a hoy: arma el resumen y borra los puntos.
     *
     * @return array{cerrados: int, puntos_eliminados: int}
     */
    public function cerrarDiasAnteriores(bool $dryRun = false): array
    {
        return $this->cerrarAntesDe(now()->startOfDay(), $dryRun);
    }

    /**
     * Cierra un día puntual, incluido hoy si se pide a mano.
     *
     * @return array{cerrados: int, puntos_eliminados: int}
     */
    public function cerrarFecha(string $fecha, bool $dryRun = false): array
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida. Usá AAAA-MM-DD.');
        }
        $inicio = Carbon::createFromFormat('Y-m-d', $fecha);
        if ($inicio === false) {
            throw new InvalidArgumentException('Fecha inválida. Usá AAAA-MM-DD.');
        }
        $inicio = $inicio->startOfDay();
        if ($inicio->toDateString() !== $fecha) {
            throw new InvalidArgumentException('Fecha inválida. Usá AAAA-MM-DD.');
        }
        if ($inicio->toDateString() > now()->toDateString()) {
            throw new InvalidArgumentException('La fecha no puede ser futura.');
        }

        return $this->cerrarRango($inicio, $inicio->copy()->endOfDay(), $dryRun);
    }

    /**
     * @return array{cerrados: int, puntos_eliminados: int}
     */
    private function cerrarAntesDe(Carbon $corte, bool $dryRun): array
    {
        $usuarios = StaffRecorridoPunto::query()
            ->where('reported_at', '<', $corte)
            ->distinct()
            ->pluck('usuario_id');

        $cerrados = 0;
        $eliminados = 0;
        foreach ($usuarios as $usuarioId) {
            $puntos = StaffRecorridoPunto::query()
                ->where('usuario_id', (int) $usuarioId)
                ->where('reported_at', '<', $corte)
                ->orderBy('reported_at')
                ->get();
            $grupos = $puntos->groupBy(fn (StaffRecorridoPunto $p) => $p->reported_at?->toDateString() ?? '');
            foreach ($grupos as $dia => $items) {
                if ($dia === '') {
                    continue;
                }
                $n = $this->cerrarGrupo((int) $usuarioId, (string) $dia, $items->all(), $dryRun);
                if ($n > 0) {
                    $cerrados++;
                    $eliminados += $n;
                }
            }
        }

        Log::info('staff.recorrido.cerrar', [
            'corte' => $corte->toDateTimeString(),
            'cerrados' => $cerrados,
            'puntos_eliminados' => $eliminados,
            'dry_run' => $dryRun,
        ]);

        return ['cerrados' => $cerrados, 'puntos_eliminados' => $eliminados];
    }

    /**
     * @return array{cerrados: int, puntos_eliminados: int}
     */
    private function cerrarRango(Carbon $inicio, Carbon $fin, bool $dryRun): array
    {
        $usuarios = StaffRecorridoPunto::query()
            ->whereBetween('reported_at', [$inicio, $fin])
            ->distinct()
            ->pluck('usuario_id');

        $cerrados = 0;
        $eliminados = 0;
        foreach ($usuarios as $usuarioId) {
            $items = StaffRecorridoPunto::query()
                ->where('usuario_id', (int) $usuarioId)
                ->whereBetween('reported_at', [$inicio, $fin])
                ->orderBy('reported_at')
                ->get()
                ->all();
            $n = $this->cerrarGrupo((int) $usuarioId, $inicio->toDateString(), $items, $dryRun);
            if ($n > 0) {
                $cerrados++;
                $eliminados += $n;
            }
        }

        return ['cerrados' => $cerrados, 'puntos_eliminados' => $eliminados];
    }

    /**
     * @param  list<StaffRecorridoPunto>  $items
     */
    private function cerrarGrupo(int $usuarioId, string $dia, array $items, bool $dryRun): int
    {
        if ($items === []) {
            return 0;
        }

        $crudos = array_map(fn (StaffRecorridoPunto $p) => [
            'lat' => (float) $p->lat,
            'lng' => (float) $p->lng,
            'at' => $p->reported_at,
            'visita_id' => $p->visita_id,
        ], $items);
        $resumen = StaffRecorridoGeometria::armarResumen($crudos, true);
        if ($resumen === null) {
            return 0;
        }

        if ($dryRun) {
            return count($items);
        }

        $inicioDia = Carbon::parse($dia)->startOfDay();
        $finDia = Carbon::parse($dia)->endOfDay();

        DB::transaction(function () use ($usuarioId, $dia, $resumen, $inicioDia, $finDia): void {
            StaffRecorridoResumen::query()->updateOrCreate(
                ['usuario_id' => $usuarioId, 'fecha' => $dia],
                [
                    'puntos_n' => $resumen['puntos_n'],
                    'distancia_metros' => $resumen['distancia_m'],
                    'visitas' => $resumen['visitas'],
                    'inicio_at' => $resumen['inicio'] ? Carbon::parse($resumen['inicio']) : null,
                    'fin_at' => $resumen['fin'] ? Carbon::parse($resumen['fin']) : null,
                    'lat_inicio' => $resumen['lat_inicio'],
                    'lng_inicio' => $resumen['lng_inicio'],
                    'lat_fin' => $resumen['lat_fin'],
                    'lng_fin' => $resumen['lng_fin'],
                    'traza' => $resumen['traza'],
                ]
            );
            StaffRecorridoPunto::query()
                ->where('usuario_id', $usuarioId)
                ->whereBetween('reported_at', [$inicioDia, $finDia])
                ->delete();
        });

        return count($items);
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function fila(int $usuarioId, ?string $nombre, array $resumen): array
    {
        $nombre = trim((string) $nombre);

        return [
            'tecnico_id' => $usuarioId,
            'nombre' => $nombre !== '' ? $nombre : 'Técnico #'.$usuarioId,
            'distancia_m' => (float) ($resumen['distancia_m'] ?? 0),
            'puntos_n' => (int) ($resumen['puntos_n'] ?? 0),
            'visitas' => (int) ($resumen['visitas'] ?? 0),
            'inicio' => $resumen['inicio'] ?? null,
            'fin' => $resumen['fin'] ?? null,
            'traza' => array_values(array_filter(
                is_array($resumen['traza'] ?? null) ? $resumen['traza'] : [],
                fn ($p) => is_array($p) && isset($p['lat'], $p['lng'])
            )),
            'manual' => false,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tecnicos
     * @return list<array<string, mixed>>
     */
    private function aplicarAjustes(string $fecha, array $tecnicos): array
    {
        $ajustes = StaffRecorridoAjuste::query()
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('usuario_id');

        foreach ($tecnicos as $i => $tecnico) {
            $ajuste = $ajustes->get($tecnico['tecnico_id']);
            $traza = is_array($ajuste?->traza) ? $ajuste->traza : [];
            if (count($traza) < 2) {
                continue;
            }
            $tecnicos[$i]['traza'] = array_values($traza);
            $tecnicos[$i]['manual'] = true;
        }

        return $tecnicos;
    }

    /**
     * @param  list<array{lat: float|int|string, lng: float|int|string}>  $traza
     */
    public function guardarAjuste(int $usuarioId, string $fecha, array $traza): void
    {
        $this->validarFechaAjuste($fecha);
        if (! User::query()->where('usuario_id', $usuarioId)->exists()) {
            throw new InvalidArgumentException('El técnico no existe.');
        }

        $limpia = [];
        foreach ($traza as $punto) {
            if (! is_array($punto) || ! isset($punto['lat'], $punto['lng'])) {
                continue;
            }
            $lat = round((float) $punto['lat'], 6);
            $lng = round((float) $punto['lng'], 6);
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                continue;
            }
            if (abs($lat) < 0.01 && abs($lng) < 0.01) {
                continue;
            }
            $limpia[] = ['lat' => $lat, 'lng' => $lng];
        }
        if (count($limpia) < 2) {
            throw new InvalidArgumentException('La ruta necesita al menos dos puntos.');
        }
        if (count($limpia) > 400) {
            $limpia = StaffRecorridoGeometria::simplificar($limpia, 30, 400);
        }

        StaffRecorridoAjuste::query()->updateOrCreate(
            ['usuario_id' => $usuarioId, 'fecha' => $fecha],
            ['traza' => $limpia]
        );
    }

    public function borrarAjuste(int $usuarioId, string $fecha): void
    {
        $this->validarFechaAjuste($fecha);
        StaffRecorridoAjuste::query()
            ->where('usuario_id', $usuarioId)
            ->whereDate('fecha', $fecha)
            ->delete();
    }

    private function validarFechaAjuste(string $fecha): void
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new InvalidArgumentException('Fecha inválida. Usá AAAA-MM-DD.');
        }
        $dia = Carbon::createFromFormat('Y-m-d', $fecha);
        if ($dia === false || $dia->format('Y-m-d') !== $fecha || $dia->toDateString() > now()->toDateString()) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tecnicos
     * @return list<array<string, mixed>>
     */
    private function ordenar(array $tecnicos): array
    {
        usort($tecnicos, fn (array $a, array $b) => strcasecmp((string) $a['nombre'], (string) $b['nombre']));

        return array_values($tecnicos);
    }
}
