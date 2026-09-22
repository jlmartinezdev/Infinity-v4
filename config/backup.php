<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ruta al ejecutable mysqldump
    |--------------------------------------------------------------------------
    |
    | En Windows con XAMPP suele ser: C:\xampp\mysql\bin\mysqldump.exe
    | Si está vacío, se usa "mysqldump" del PATH.
    |
    */

    'mysqldump_path' => env('MYSQLDUMP_PATH', ''),

    /*
    |--------------------------------------------------------------------------
    | Backup esencial
    |--------------------------------------------------------------------------
    |
    | Copia operativa (clientes, servicios, cobros, etc.) sin tablas de ruido:
    | notificaciones, auditoría, eventos de red y mensajes de WhatsApp.
    |
    */
    'esencial_omitir' => [
        'notifications',
        'push_avisos',
        'auditoria',
        'servicio_conexion_eventos',
        'monitoreo_ping_servicios',
        'whatsapp_mensajes',
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Drive (backup remoto)
    |--------------------------------------------------------------------------
    */

    'drive' => [
        'enabled' => (bool) env('BACKUP_DRIVE_ENABLED', false),
        'client_id' => env('GOOGLE_DRIVE_CLIENT_ID', ''),
        'client_secret' => env('GOOGLE_DRIVE_CLIENT_SECRET', ''),
        'refresh_token' => env('GOOGLE_DRIVE_REFRESH_TOKEN', ''),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', ''),
        'redirect_uri' => env('GOOGLE_DRIVE_REDIRECT_URI', ''),
        'keep' => (int) env('BACKUP_DRIVE_KEEP', 14),
        'scope' => 'https://www.googleapis.com/auth/drive.file',
    ],

];
