<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackupDriveAuthCommand extends Command
{
    protected $signature = 'backup:drive-auth
                            {--port=8765 : Puerto local para el callback OAuth}';

    protected $description = 'Autoriza Google Drive (OAuth) y guarda GOOGLE_DRIVE_REFRESH_TOKEN en .env';

    public function handle(): int
    {
        $clientId = (string) config('backup.drive.client_id');
        $clientSecret = (string) config('backup.drive.client_secret');
        $scope = (string) config('backup.drive.scope', 'https://www.googleapis.com/auth/drive.file');
        $port = (int) $this->option('port');

        if ($clientId === '' || $clientSecret === '') {
            $this->error('Faltan GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET en .env');

            return self::FAILURE;
        }

        $redirectUri = "http://127.0.0.1:{$port}/";
        $state = bin2hex(random_bytes(16));

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $scope,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

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

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $params['code'],
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            $this->error('Error al intercambiar el code: '.$response->body());

            return self::FAILURE;
        }

        $refresh = (string) ($response->json('refresh_token') ?? '');
        if ($refresh === '') {
            $this->error('Google no devolvió refresh_token. Revocá el acceso de la app en la cuenta Google y reintentá con prompt=consent.');

            return self::FAILURE;
        }

        $this->writeEnvValue('GOOGLE_DRIVE_REFRESH_TOKEN', $refresh);
        $this->writeEnvValue('BACKUP_DRIVE_ENABLED', 'true');

        $this->info('Listo. GOOGLE_DRIVE_REFRESH_TOKEN guardado en .env y BACKUP_DRIVE_ENABLED=true.');
        $this->comment('Siguiente: creá una carpeta en Drive, copiá su ID (en la URL) a GOOGLE_DRIVE_FOLDER_ID.');

        return self::SUCCESS;
    }

    private function writeEnvValue(string $key, string $value): void
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            throw new \RuntimeException('.env no encontrado');
        }

        $content = file_get_contents($path) ?: '';
        $line = $key.'='.$value;

        if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $content)) {
            $content = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $content) ?? $content;
        } else {
            $content = rtrim($content)."\n".$line."\n";
        }

        file_put_contents($path, $content);
    }
}
