<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterScheduler extends Model
{
    protected $table = 'router_schedulers';

    protected $primaryKey = 'router_scheduler_id';

    protected $fillable = [
        'nombre',
        'on_event',
        'start_date',
        'start_time',
        'interval',
        'owner',
        'policy',
        'disabled',
        'comment',
        'router_origen_id',
        'notas',
        'leido_en',
        'sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'disabled' => 'boolean',
            'leido_en' => 'datetime',
            'sincronizado_en' => 'datetime',
        ];
    }

    public function routerOrigen(): BelongsTo
    {
        return $this->belongsTo(Router::class, 'router_origen_id', 'router_id');
    }

    public function getRouteKeyName(): string
    {
        return 'router_scheduler_id';
    }
}
