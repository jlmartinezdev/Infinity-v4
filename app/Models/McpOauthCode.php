<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpOauthCode extends Model
{
    protected $table = 'mcp_oauth_codes';

    protected $fillable = [
        'code_hash',
        'client_id',
        'usuario_id',
        'redirect_uri',
        'code_challenge',
        'scope',
        'resource',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
