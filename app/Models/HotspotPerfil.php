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
        'shared_users',
        'idle_timeout',
        'session_timeout',
    ];

    public function servicioHotspots(): HasMany
    {
        return $this->hasMany(ServicioHotspot::class, 'hotspot_perfil_id', 'hotspot_perfil_id');
    }
}
