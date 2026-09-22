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
