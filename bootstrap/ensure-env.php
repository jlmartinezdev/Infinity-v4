<?php

/**
 * En Windows el .env a veces está bloqueado (editor, antivirus, rewrite).
 * Sin .env Laravel cae a APP_ENV=production, APP_KEY vacío y DB sqlite → HTTP 500.
 */
$envFile = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';

for ($i = 0; $i < 8; $i++) {
    $size = @filesize($envFile);
    if (is_int($size) && $size > 80) {
        $fp = @fopen($envFile, 'rb');
        if ($fp !== false) {
            fclose($fp);
            break;
        }
    }
    usleep(80_000);
}
