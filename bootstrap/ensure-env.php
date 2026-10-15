<?php

/**
 * En Windows el .env a veces está bloqueado (editor, antivirus o un rewrite).
 * Si Laravel arranca sin esas variables usa la base "laravel", sin clave,
 * y MariaDB responde auth_gssapi_client → HTTP 500 en cualquier pantalla.
 *
 * Leemos un snapshot completo y lo dejamos en el entorno del proceso.
 * Dotenv es inmutable: si después no puede abrir el archivo, no pisa estos valores.
 */

if (! function_exists('infinity_cargar_env')) {
    function infinity_cargar_env(string $path): bool
    {
        $raw = @file_get_contents($path);
        if (! is_string($raw) || strlen($raw) < 80) {
            return false;
        }
        if (! preg_match('/^APP_KEY=\s*\S+/m', $raw) || ! preg_match('/^DB_DATABASE=\s*\S+/m', $raw)) {
            return false;
        }

        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                continue;
            }

            $value = trim($value);
            if (str_starts_with($value, '"') && str_ends_with($value, '"') && strlen($value) >= 2) {
                $value = str_replace(
                    ['\n', '\r', '\"', '\\\\'],
                    ["\n", "\r", '"', '\\'],
                    substr($value, 1, -1)
                );
            } elseif (str_starts_with($value, "'") && str_ends_with($value, "'") && strlen($value) >= 2) {
                $value = substr($value, 1, -1);
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
            }

            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        $db = getenv('DB_DATABASE');

        return getenv('APP_KEY') !== false
            && is_string($db)
            && $db !== ''
            && $db !== 'laravel';
    }
}

$envFile = dirname(__DIR__).DIRECTORY_SEPARATOR.'.env';
$cargado = false;

for ($i = 0; $i < 25; $i++) {
    if (infinity_cargar_env($envFile)) {
        $cargado = true;
        break;
    }
    usleep(100_000);
}

if (! $cargado) {
    $db = getenv('DB_DATABASE');
    $yaListo = getenv('APP_KEY') !== false
        && is_string($db)
        && $db !== ''
        && $db !== 'laravel';

    if (! $yaListo) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "No se pudo leer .env. El proceso no arranca con la base por defecto.\n");
            exit(1);
        }

        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Retry-After: 2');
        echo "Configuración ocupada. Reintentá en unos segundos.\n";
        exit(1);
    }
}
