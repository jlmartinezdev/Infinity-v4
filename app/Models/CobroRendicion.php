<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CobroRendicion extends Model
{
    protected $table = 'cobro_rendiciones';

    protected $fillable = [
        'usuario_cobrador_id',
        'usuario_tesorero_id',
        'monto',
        'cantidad_cobros',
        'fecha_rendicion',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_rendicion' => 'datetime',
            'cantidad_cobros' => 'integer',
        ];
    }

    public function cobrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_cobrador_id', 'usuario_id');
    }

    public function tesorero(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_tesorero_id', 'usuario_id');
    }

    public function cobros(): HasMany
    {
        return $this->hasMany(Cobro::class, 'cobro_rendicion_id');
    }
}
