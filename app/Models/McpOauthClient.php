<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpOauthClient extends Model
{
    protected $table = 'mcp_oauth_clients';

    protected $fillable = [
        'client_id',
        'client_secret_hash',
        'client_name',
        'redirect_uris',
        'token_endpoint_auth_method',
    ];

    protected $casts = [
        'redirect_uris' => 'array',
    ];
}
