<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioHotspot extends Model
{
    use Auditable;

    protected $table = 'servicio_hotspot';

    protected $fillable = [
        'servicio_id',
        'router_id',
        'hotspot_perfil_id',
        'username',
        'password',
        'comment',
        'ros_id',
        'last_synced',
    ];

    protected function casts(): array
    {
        return [
            'last_synced' => 'datetime',
        ];
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class, 'servicio_id', 'servicio_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id', 'router_id');
    }

    public function hotspotPerfil(): BelongsTo
    {
        return $this->belongsTo(HotspotPerfil::class, 'hotspot_perfil_id', 'hotspot_perfil_id');
    }
}
