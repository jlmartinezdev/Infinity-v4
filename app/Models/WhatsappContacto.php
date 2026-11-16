<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappContacto extends Model
{
    protected $table = 'whatsapp_contactos';

    protected $fillable = [
        'telefono',
        'nombre',
        'cliente_id',
        'whatsapp_asunto_id',
        'encargado_usuario_id',
        'encargado_at',
        'ultimo_visto_at',
        'mensajes_count',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_visto_at' => 'datetime',
            'encargado_at' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id', 'cliente_id');
    }

    public function asunto(): BelongsTo
    {
        return $this->belongsTo(WhatsappAsunto::class, 'whatsapp_asunto_id');
    }

    public function encargado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_usuario_id', 'usuario_id');
    }
}
