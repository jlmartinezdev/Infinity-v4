<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushAviso extends Model
{
    protected $table = 'push_avisos';

    protected $fillable = [
        'titulo',
        'cuerpo',
        'tipo',
        'destino',
        'cliente_ids',
        'total_destinatarios',
        'enviados',
        'fallidos',
        'omitidos',
        'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'cliente_ids' => 'array',
        ];
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por', 'usuario_id');
    }

    public static function tipos(): array
    {
        return [
            'aviso' => 'Aviso',
            'promocion' => 'Promoción',
        ];
    }
}
