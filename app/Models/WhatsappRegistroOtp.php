<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsappRegistroOtp extends Model
{
    protected $table = 'whatsapp_registro_otps';

    protected $fillable = [
        'telefono',
        'telefono_sufijo',
        'codigo',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function scopeVigentes($query)
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
