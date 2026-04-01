<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Router extends Model
{
    use Auditable;
    protected $table = 'routers';

    protected $primaryKey = 'router_id';

    protected $keyType = 'int';

    public $incrementing = true;

    protected $fillable = [
        'nodo_id',
        'nombre',
        'ip',
        'ip_loopback',
        'hotspot_servidor',
        'api_port',
        'usuario',
        'password',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'api_port' => 'integer',
        ];
    }

    public function nodo(): BelongsTo
    {
        return $this->belongsTo(Nodo::class, 'nodo_id', 'nodo_id');
    }

    public function routerIpPools(): HasMany
    {
        return $this->hasMany(RouterIpPool::class, 'router_id', 'router_id');
    }

    public function getRouteKeyName(): string
    {
        return 'router_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }
}
