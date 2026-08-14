<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouterScript extends Model
{
    protected $table = 'router_scripts';

    protected $primaryKey = 'router_script_id';

    protected $fillable = [
        'nombre',
        'source',
        'owner',
        'policy',
        'dont_require_permissions',
        'router_origen_id',
        'notas',
        'leido_en',
        'sincronizado_en',
    ];

    protected function casts(): array
    {
        return [
            'dont_require_permissions' => 'boolean',
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
        return 'router_script_id';
    }
}
