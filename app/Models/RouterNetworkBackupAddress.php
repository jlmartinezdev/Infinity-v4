<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterNetworkBackupAddress extends Model
{
    protected $table = 'router_network_backup_addresses';

    protected $fillable = [
        'router_network_backup_id',
        'familia',
        'address',
        'network',
        'interface',
        'disabled',
        'comment',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'extra' => 'array',
        ];
    }

    public function backup(): BelongsTo
    {
        return $this->belongsTo(RouterNetworkBackup::class, 'router_network_backup_id', 'router_network_backup_id');
    }
}
