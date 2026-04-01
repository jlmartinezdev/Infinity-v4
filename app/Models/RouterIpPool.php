<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouterIpPool extends Model
{
    use Auditable;
    protected $table = 'router_ip_pools';

    protected $primaryKey = 'pool_id';

    protected $keyType = 'int';

    public $incrementing = true;

    public $timestamps = false;

    protected $fillable = [
        'router_id',
        'ip_range',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    protected $dates = ['created_at'];

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_id', 'router_id');
    }

    public function poolIpAsignadas(): HasMany
    {
        return $this->hasMany(PoolIpAsignada::class, 'pool_id', 'pool_id');
    }

    public function getRouteKeyName(): string
    {
        return 'pool_id';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?: $this->getRouteKeyName();
        return $this->where($field, $value)->first();
    }
}
