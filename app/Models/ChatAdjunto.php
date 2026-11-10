<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatAdjunto extends Model
{
    protected $table = 'chat_adjuntos';

    protected $fillable = [
        'mensaje_id',
        'disco',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
    ];

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(ChatMensaje::class, 'mensaje_id');
    }
}
