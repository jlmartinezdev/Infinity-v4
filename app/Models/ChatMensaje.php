<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatMensaje extends Model
{
    protected $table = 'chat_mensajes';

    protected $fillable = [
        'conversacion_id',
        'usuario_id',
        'cuerpo',
    ];

    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ChatConversacion::class, 'conversacion_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }

    public function adjunto(): HasOne
    {
        return $this->hasOne(ChatAdjunto::class, 'mensaje_id');
    }

    public function acciones(): HasMany
    {
        return $this->hasMany(ChatMensajeAccion::class, 'mensaje_id');
    }
}
