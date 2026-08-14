<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouterNetworkBackup extends Model
{
    protected $table = 'router_network_backups';

    protected $primaryKey = 'router_network_backup_id';

    protected $fillable = [
        'router_origen_id',
        'nombre',
        'notas',
        'cant_ipv4',
        'cant_ipv6',
        'cant_rutas_v4',
        'cant_rutas_v6',
        'leido_en',
        'sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'cant_ipv4' => 'integer',
            'cant_ipv6' => 'integer',
            'cant_rutas_v4' => 'integer',
            'cant_rutas_v6' => 'integer',
            'leido_en' => 'datetime',
            'sincronizado_en' => 'datetime',
        ];
    }

    public function routerOrigen(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_origen_id', 'router_id');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(RouterNetworkBackupAddress::class, 'router_network_backup_id', 'router_network_backup_id');
    }

    public function routes(): HasMany
    {
        return $this->hasMany(RouterNetworkBackupRoute::class, 'router_network_backup_id', 'router_network_backup_id');
    }

    public function getRouteKeyName(): string
    {
        return 'router_network_backup_id';
    }
}
