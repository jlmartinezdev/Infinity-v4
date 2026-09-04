<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveAuthService;
use Illuminate\Console\Command;

class BackupDriveAuthCommand extends Command
{
    protected $signature = 'backup:drive-auth
                            {--port=8765 : Puerto local para el callback OAuth}';

    protected $description = 'Autoriza Google Drive (OAuth) y guarda GOOGLE_DRIVE_REFRESH_TOKEN en .env';

    public function handle(GoogleDriveAuthService $auth): int
    {
        $port = (int) $this->option('port');

        if (! $auth->hasOAuthClient()) {
            $this->error('Faltan GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET en .env');

            return self::FAILURE;
        }

        $redirectUri = "http://127.0.0.1:{$port}/";
        $state = bin2hex(random_bytes(16));
        $authUrl = $auth->authorizationUrl($state, $redirectUri);

        $this->warn('Abrí la URL en Chrome o Edge normal (NO en el navegador de Cursor).');
        $this->info('1) URL de autorización:');
        $this->line($authUrl);
        $this->newLine();
        $this->info("2) Esperando callback en {$redirectUri} (hasta 5 min)…");

        $socket = @stream_socket_server("tcp://127.0.0.1:{$port}", $errno, $errstr);
        if ($socket === false) {
            $this->error("No se pudo abrir el puerto {$port}: {$errstr} ({$errno})");

            return self::FAILURE;
        }

        stream_set_blocking($socket, false);
        $deadline = time() + 300;
        $params = null;
        $htmlOk = '<html><body style="font-family:sans-serif;padding:2rem"><h2>Autorización OK</h2><p>Ya podés cerrar esta ventana y volver a la terminal.</p></body></html>';
        $htmlWait = '<html><body style="font-family:sans-serif;padding:2rem"><h2>Esperando autorización…</h2><p>Volvé a Google y aceptá el permiso. No abras esta URL a mano.</p></body></html>';

        while (time() < $deadline) {
            $conn = @stream_socket_accept($socket, 1);
            if ($conn === false) {
                usleep(200000);

                continue;
            }

            $request = '';
            stream_set_timeout($conn, 5);
            while (! str_contains($request, "\r\n\r\n")) {
                $chunk = fread($conn, 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $request .= $chunk;
            }

            $path = '/';
            if (preg_match('/^(GET|POST)\s+(\S+)/', $request, $m)) {
                $path = $m[2];
            }

            $query = parse_url($path, PHP_URL_QUERY) ?: '';
            $parsed = [];
            parse_str($query, $parsed);

            if (($parsed['state'] ?? '') === $state && ! empty($parsed['code'])) {
                fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: ".strlen($htmlOk)."\r\nConnection: close\r\n\r\n".$htmlOk);
                fclose($conn);
                $params = $parsed;
                break;
            }

            // Ignorar hits vacíos (pestañas abiertas a mano a 127.0.0.1:8765)
            fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/html; charset=utf-8\r\nContent-Length: ".strlen($htmlWait)."\r\nConnection: close\r\n\r\n".$htmlWait);
            fclose($conn);
            $this->comment('Conexión ignorada (sin code). Seguí en Google…');
        }

        fclose($socket);

        if ($params === null) {
            $this->error('Timeout: no llegó la autorización (5 min).');

            return self::FAILURE;
        }

        try {
            $refresh = $auth->exchangeCode($params['code'], $redirectUri);
            $auth->persistRefreshToken($refresh);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Listo. GOOGLE_DRIVE_REFRESH_TOKEN guardado en .env y BACKUP_DRIVE_ENABLED=true.');
        $this->comment('Siguiente: creá una carpeta en Drive, copiá su ID (en la URL) a GOOGLE_DRIVE_FOLDER_ID.');

        return self::SUCCESS;
    }
}
