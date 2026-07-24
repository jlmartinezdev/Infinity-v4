<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappAsunto extends Model
{
    protected $table = 'whatsapp_asuntos';

    protected $fillable = [
        'nombre',
        'color',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(WhatsappContacto::class, 'whatsapp_asunto_id');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true)->orderBy('orden')->orderBy('nombre');
    }
}
