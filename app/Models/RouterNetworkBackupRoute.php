<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterNetworkBackupRoute extends Model
{
    protected $table = 'router_network_backup_routes';

    protected $fillable = [
        'router_network_backup_id',
        'familia',
        'dst_address',
        'gateway',
        'distance',
        'routing_table',
        'scope',
        'target_scope',
        'pref_src',
        'check_gateway',
        'disabled',
        'comment',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'distance' => 'integer',
            'extra' => 'array',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(RouterNetworkBackup::class, 'router_network_backup_id', 'router_network_backup_id');
    }
}
