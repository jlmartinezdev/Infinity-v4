<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrityNonce extends Model
{
    protected $table = 'integrity_nonces';

    protected $fillable = [
        'nonce',
        'ip',
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

    public function estaVigente(): bool
    {
        return $this->used_at === null && $this->expires_at !== null && $this->expires_at->isFuture();
    }
}
