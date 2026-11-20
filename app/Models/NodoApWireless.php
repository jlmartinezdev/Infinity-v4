<?php

namespace App\Models;

use App\Support\ApWirelessCaidaVentana;
use App\Support\MonitoreoDuracion;
use App\Traits\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'ping_caido_desde',
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
            'ping_caido_desde' => 'datetime',
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
        $inicioPrevio = $this->ping_caido_desde?->copy();
        $yaCaido = $inicioPrevio !== null;
        $fallos = (int) $this->ping_fallos_seguidos;
        $this->ping_ok = $result['ok'];
        $this->ping_latencia_ms = $result['latency_ms'];
        $this->ping_at = now();
        $this->ping_error = $result['ok'] ? null : ($result['error'] ?? 'Sin respuesta');
        if ($result['ok']) {
            // Un ping OK suelto NO libera el flag de aviso: si el AP parpadea
            // (1 ok / varios fail) re-spamaba WhatsApp cada pocos minutos.
            // Solo tras 2 OK seguidos (fallos ya en 0) se puede alertar otra caída.
            if ($fallos === 0) {
                $this->ping_alerta_enviada = false;
            }
            $this->ping_fallos_seguidos = 0;
            $this->ping_caido_desde = null;
        } else {
            if ($this->ping_caido_desde === null) {
                $this->ping_caido_desde = now();
            }
            $this->ping_fallos_seguidos = min(65535, $fallos + 1);
        }
        $this->saveQuietly();

        if ($result['ok']) {
            if ($yaCaido) {
                $this->cerrarCaida($inicioPrevio);
            }
        } else {
            $this->abrirCaida();
        }
    }

    public function caidasRecientes(): HasMany
    {
        $desde = now()->subDay();

        return $this->hasMany(NodoApWirelessCaida::class, 'ap_id', 'ap_id')
            ->where(function ($query) use ($desde) {
                $query->whereNull('fin')->orWhere('fin', '>', $desde);
            })
            ->orderBy('inicio');
    }

    /**
     * @return list<array{inicio: string, fin: string}>
     */
    public function caidas24hVista(): array
    {
        $filas = ($this->relationLoaded('caidasRecientes')
            ? $this->caidasRecientes
            : $this->caidasRecientes()->get())
            ->map(fn (NodoApWirelessCaida $caida) => [
                'inicio' => $caida->inicio,
                'fin' => $caida->fin,
            ])
            ->all();

        return ApWirelessCaidaVentana::recortar(
            $filas,
            now(),
            $this->ping_caido_desde,
            $this->ping_ok === false,
        );
    }

    private function abrirCaida(): void
    {
        $abierta = NodoApWirelessCaida::query()
            ->where('ap_id', $this->ap_id)
            ->whereNull('fin')
            ->exists();
        if ($abierta) {
            return;
        }

        NodoApWirelessCaida::query()->create([
            'ap_id' => $this->ap_id,
            'inicio' => $this->ping_caido_desde ?? now(),
            'fin' => null,
        ]);
    }

    private function cerrarCaida(?CarbonInterface $inicioPrevio = null): void
    {
        $cerradas = NodoApWirelessCaida::query()
            ->where('ap_id', $this->ap_id)
            ->whereNull('fin')
            ->update(['fin' => now()]);

        if ($cerradas > 0 || $inicioPrevio === null) {
            return;
        }

        NodoApWirelessCaida::query()->create([
            'ap_id' => $this->ap_id,
            'inicio' => $inicioPrevio,
            'fin' => now(),
        ]);
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
            'ping_caido_desde' => $this->ping_caido_desde?->toIso8601String(),
            'caido_desde' => $this->ping_caido_desde
                ? $this->ping_caido_desde->timezone(config('app.timezone'))->format('d/m/Y H:i')
                : null,
            'caido_hace' => MonitoreoDuracion::formatear($this->ping_caido_desde),
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
            'caidas_24h' => $this->caidas24hVista(),
        ];
    }
}
