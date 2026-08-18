<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodoApWireless extends Model
{
    use Auditable;

    protected $table = 'nodo_aps_wireless';

    protected $primaryKey = 'ap_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'nodo_id',
        'nombre',
        'ip',
        'activo',
        'notas',
        'ping_ok',
        'ping_latencia_ms',
        'ping_at',
        'ping_error',
        'ping_fallos_seguidos',
        'ping_alerta_enviada',
        'hostname',
        'ssid',
        'modo',
        'frecuencia',
        'canal',
        'chanbw',
        'firmware',
        'modelo',
        'mac',
        'uptime_segundos',
        'estaciones',
        'ssh_at',
        'ssh_error',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'ping_ok' => 'boolean',
            'ping_latencia_ms' => 'integer',
            'ping_at' => 'datetime',
            'ping_fallos_seguidos' => 'integer',
            'ping_alerta_enviada' => 'boolean',
            'uptime_segundos' => 'integer',
            'estaciones' => 'integer',
            'ssh_at' => 'datetime',
            'extra' => 'array',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function getRouteKeyName(): string
    {
        return 'ap_id';
    }

    /**
     * @param  array{ok: bool, latency_ms: int|null, error: string|null}  $result
     */
    public function aplicarResultadoPing(array $result): void
    {
        $fallos = (int) $this->ping_fallos_seguidos;
        $this->ping_ok = $result['ok'];
        $this->ping_latencia_ms = $result['latency_ms'];
        $this->ping_at = now();
        $this->ping_error = $result['ok'] ? null : ($result['error'] ?? 'Sin respuesta');
        if ($result['ok']) {
            $this->ping_fallos_seguidos = 0;
            $this->ping_alerta_enviada = false;
        } else {
            $this->ping_fallos_seguidos = min(65535, $fallos + 1);
        }
        $this->saveQuietly();
    }

    public static function formatearUptime(?int $segundos): ?string
    {
        if ($segundos === null || $segundos < 0) {
            return null;
        }

        $dias = intdiv($segundos, 86400);
        $horas = intdiv($segundos % 86400, 3600);
        $minutos = intdiv($segundos % 3600, 60);

        $partes = [];
        if ($dias > 0) {
            $partes[] = $dias.'d';
        }
        if ($horas > 0 || $dias > 0) {
            $partes[] = $horas.'h';
        }
        $partes[] = $minutos.'m';

        return implode(' ', $partes);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArrayVista(): array
    {
        return [
            'ap_id' => $this->ap_id,
            'nodo_id' => $this->nodo_id,
            'nodo' => $this->nodo?->descripcion,
            'nombre' => $this->nombre,
            'ip' => $this->ip,
            'activo' => (bool) $this->activo,
            'notas' => $this->notas,
            'ping_ok' => $this->ping_ok,
            'ping_latencia_ms' => $this->ping_latencia_ms,
            'ping_at' => $this->ping_at?->toIso8601String(),
            'ping_error' => $this->ping_error,
            'hostname' => $this->hostname,
            'ssid' => $this->ssid,
            'modo' => $this->modo,
            'frecuencia' => $this->frecuencia,
            'canal' => $this->canal,
            'chanbw' => $this->chanbw,
            'firmware' => $this->firmware,
            'modelo' => $this->modelo,
            'mac' => $this->mac,
            'uptime_segundos' => $this->uptime_segundos,
            'uptime' => self::formatearUptime($this->uptime_segundos),
            'estaciones' => $this->estaciones,
            'ssh_at' => $this->ssh_at?->toIso8601String(),
            'ssh_error' => $this->ssh_error,
            'extra' => $this->extra ?: null,
        ];
    }
}
