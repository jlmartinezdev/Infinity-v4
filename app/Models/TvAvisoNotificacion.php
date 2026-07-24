<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvAvisoNotificacion extends Model
{
    protected $table = 'tv_aviso_notificaciones';

    protected $fillable = [
        'tv_cuenta_id',
        'fecha_vencimiento',
        'enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
            'enviado_at' => 'datetime',
        ];
    }

    public function tvCuenta(): BelongsTo
    {
        return $this->belongsTo(TvCuenta::class, 'tv_cuenta_id');
    }
}
