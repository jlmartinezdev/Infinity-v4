<?php

return [
    /*
    | Conector MCP para que Claude lea WhatsApp y proponga una respuesta.
    | El envío real exige confirmar=true en la herramienta whatsapp_enviar.
    */
    'whatsapp' => [
        'token' => env('MCP_WHATSAPP_TOKEN'),
        // Cliente confidencial para Gemini (y cualquier conector que pida id + secreto).
        // ChatGPT puede registrarse solo en /api/oauth/register.
        'oauth_client_id' => env('MCP_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('MCP_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'MCP_OAUTH_REDIRECT_HOSTS',
                'chatgpt.com,chat.openai.com,gemini.google.com,accounts.google.com'
            ))
        ))),
    ],
];
