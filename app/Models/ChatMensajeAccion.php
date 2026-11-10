<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMensajeAccion extends Model
{
    protected $table = 'chat_mensaje_acciones';

    protected $fillable = [
        'mensaje_id',
        'usuario_id',
        'atendido_at',
    ];

    protected function casts(): array
    {
        return [
            'atendido_at' => 'datetime',
        ];
    }

    public function mensaje(): BelongsTo
    {
        return $this->belongsTo(ChatMensaje::class, 'mensaje_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'usuario_id');
    }
}
