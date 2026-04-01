<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoolIpAsignada extends Model
{
    use Auditable;
    protected $table = 'pool_ip_asignadas';

    protected $primaryKey = ['ip', 'pool_id'];

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'ip',
        'pool_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function routerIpPool(): BelongsTo
    {
        return $this->belongsTo(RouterIpPool::class, 'pool_id', 'pool_id');
    }

    protected function setKeysForSaveQuery($query)
    {
        return $query->where('ip', $this->ip)->where('pool_id', $this->pool_id);
    }

    public static function find($ip, $poolId = null)
    {
        if ($poolId === null && is_array($ip)) {
            $poolId = $ip['pool_id'] ?? null;
            $ip = $ip['ip'] ?? null;
        }
        return static::where('ip', $ip)->where('pool_id', $poolId)->first();
    }
}
