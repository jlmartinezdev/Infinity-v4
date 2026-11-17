<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotspotPerfil extends Model
{
    use Auditable;

    protected $table = 'hotspot_perfiles';

    protected $primaryKey = 'hotspot_perfil_id';

    protected $fillable = [
        'nombre',
        'rate_limit',
        'cuota_gb',
        'shared_users',
        'idle_timeout',
        'session_timeout',
    ];

    protected function casts(): array
    {
        return [
            'cuota_gb' => 'integer',
        ];
    }

    public function servicioHotspots(): HasMany
    {
        return $this->hasMany(ServicioHotspot::class, 'hotspot_perfil_id', 'hotspot_perfil_id');
    }

    public function tieneCuota(): bool
    {
        return (int) $this->cuota_gb > 0;
    }

    public function etiqueta(): string
    {
        $nombre = (string) $this->nombre;
        if (! $this->tieneCuota()) {
            return $nombre;
        }

        return $nombre.' · '.(int) $this->cuota_gb.' GB';
    }

    /**
     * Velocidad del perfil para mostrar. El rate-limit de MikroTik es "subida/bajada"
     * (rx/tx vistos desde el router); lo que venga después de un espacio (ráfagas) se ignora.
     *
     * @return array{subida: string, bajada: string}|null
     */
    public function velocidad(): ?array
    {
        $rate = trim((string) $this->rate_limit);
        if ($rate === '') {
            return null;
        }
        $base = preg_split('/\s+/', $rate)[0];
        $partes = explode('/', $base);
        $subida = self::velocidadLegible($partes[0] ?? '');
        $bajada = self::velocidadLegible($partes[1] ?? ($partes[0] ?? ''));
        if ($subida === null || $bajada === null) {
            return null;
        }

        return ['subida' => $subida, 'bajada' => $bajada];
    }

    /**
     * "10M" → "10 Mbps", "512k" → "512 kbps", "1G" → "1 Gbps", "1000000" → "1 Mbps".
     */
    public static function velocidadLegible(string $valor): ?string
    {
        if (! preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*([kKmMgG]?)\s*$/', $valor, $m)) {
            return null;
        }
        $numero = (float) str_replace(',', '.', $m[1]);
        $bps = $numero * match (strtolower($m[2])) {
            'k' => 1_000,
            'm' => 1_000_000,
            'g' => 1_000_000_000,
            default => 1,
        };
        if ($bps <= 0) {
            return null;
        }
        $formato = fn (float $n) => rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');
        if ($bps >= 1_000_000_000) {
            return $formato($bps / 1_000_000_000).' Gbps';
        }
        if ($bps >= 1_000_000) {
            return $formato($bps / 1_000_000).' Mbps';
        }

        return $formato($bps / 1_000).' kbps';
    }

    /**
     * Nombre para el watch: si el perfil se llama como el tope («10 GB»),
     * mostrar rate-limit o nada — la cuota va en el anillo.
     */
    public function nombreDistintoDeCuota(): string
    {
        $nombre = trim((string) $this->nombre);
        if ($nombre === '') {
            return '';
        }
        $cuota = (int) $this->cuota_gb;
        if ($cuota > 0 && preg_match('/^\s*'.preg_quote((string) $cuota, '/').'\s*GB\s*$/iu', $nombre)) {
            return trim((string) $this->rate_limit);
        }

        return $nombre;
    }
}
