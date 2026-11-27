<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpOauthToken extends Model
{
    protected $table = 'mcp_oauth_tokens';

    protected $fillable = [
        'token_hash',
        'refresh_hash',
        'client_id',
        'usuario_id',
        'scope',
        'expires_at',
        'refresh_expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'refresh_expires_at' => 'datetime',
    ];
}
