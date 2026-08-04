<?php

namespace App\Services\Staff;

use App\Models\StaffUbicacion;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class StaffUbicacionService
{
    public const RATE_LIMIT_SECONDS = 20;

    public const FLOTA_LOOKBACK_HOURS = 24;

    /**
     * Upsert última posición del técnico. Respeta rate-limit por usuario.
     *
     * @param  array{lat: float, lng: float, accuracy?: float|null, heading?: float|null, en_turno?: bool, visita_id?: int|null}  $data
     * @return array{ubicacion: StaffUbicacion|null, throttled: bool}
     */
    public function reportar(int $usuarioId, array $data): array
    {
        $lat = (float) $data['lat'];
        $lng = (float) $data['lng'];

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw ValidationException::withMessages([
                'lat' => ['Coordenadas inválidas.'],
            ]);
        }

        $cacheKey = 'staff_ubicacion_rl:'.$usuarioId;
        if (! Cache::add($cacheKey, 1, self::RATE_LIMIT_SECONDS)) {
            return ['ubicacion' => null, 'throttled' => true];
        }

        $visitaId = $data['visita_id'] ?? null;
        if ($visitaId !== null) {
            $existe = Ticket::query()->whereKey((int) $visitaId)->exists();
            if (! $existe) {
                throw ValidationException::withMessages([
                    'visita_id' => ['La visita indicada no existe.'],
                ]);
            }
        }

        $ubicacion = StaffUbicacion::query()->updateOrCreate(
            ['usuario_id' => $usuarioId],
            [
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $data['accuracy'] ?? null,
                'heading' => $data['heading'] ?? null,
                'en_turno' => array_key_exists('en_turno', $data) ? (bool) $data['en_turno'] : true,
                'visita_id' => $visitaId !== null ? (int) $visitaId : null,
                'reported_at' => now(),
            ]
        );

        return ['ubicacion' => $ubicacion, 'throttled' => false];
    }

    /**
     * Flota visible: reportes de las últimas 24 h (en_turno se expone al cliente).
     *
     * @return Collection<int, StaffUbicacion>
     */
    public function listarFlota(): Collection
    {
        $desde = now()->subHours(self::FLOTA_LOOKBACK_HOURS);

        return StaffUbicacion::query()
            ->with(['usuario:usuario_id,name'])
            ->where('reported_at', '>=', $desde)
            ->orderByDesc('reported_at')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarFlotaPayload(): array
    {
        return $this->listarFlota()
            ->map(fn (StaffUbicacion $u) => $u->toFlotaItem())
            ->values()
            ->all();
    }
}
