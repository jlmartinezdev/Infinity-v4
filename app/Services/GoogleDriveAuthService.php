<?php

namespace App\Services;

use App\Support\DotEnvWriter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveAuthService
{
    public const LOOPBACK_REDIRECT_URI = 'http://127.0.0.1:8765/';

    public function hasOAuthClient(): bool
    {
        return filled(config('backup.drive.client_id'))
            && filled(config('backup.drive.client_secret'));
    }

    public function loopbackRedirectUri(): string
    {
        return self::LOOPBACK_REDIRECT_URI;
    }

    public function redirectUri(): string
    {
        $configured = trim((string) config('backup.drive.redirect_uri', ''));
        if ($configured !== '') {
            return $configured;
        }

        // El cliente OAuth de backup:drive-auth es de escritorio: solo acepta loopback.
        return $this->loopbackRedirectUri();
    }

    public function isLoopbackRedirect(?string $uri = null): bool
    {
        $uri ??= $this->redirectUri();

        return str_starts_with($uri, 'http://127.0.0.1:')
            || str_starts_with($uri, 'http://[::1]:');
    }

    /**
     * @return array<string, string>
     */
    public function parsePastedOauthUrl(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        if (! str_contains($raw, '://')) {
            $raw = str_starts_with($raw, '?')
                ? 'http://127.0.0.1:8765/'.$raw
                : 'http://127.0.0.1:8765/?'.$raw;
        }

        $query = parse_url($raw, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return [];
        }

        $params = [];
        parse_str($query, $params);

        return array_map(static fn ($v) => is_string($v) ? $v : '', $params);
    }

    public function ensureLoopbackCatcher(string $forwardTo): bool
    {
        $dir = storage_path('app/drive-oauth');
        File::ensureDirectoryExists($dir);
        $routerPath = $dir.DIRECTORY_SEPARATOR.'router.php';
        file_put_contents($dir.DIRECTORY_SEPARATOR.'target.txt', $forwardTo);
        file_put_contents($routerPath, $this->loopbackRouterScript());

        if ($this->loopbackPortOpen()) {
            return true;
        }

        $php = $this->phpCliExecutable();
        if ($php === null) {
            return false;
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $cmd = 'cmd /c start /B "" '.escapeshellarg($php).' -S 127.0.0.1:8765 '.escapeshellarg($routerPath);
                pclose(popen($cmd, 'r'));
            } else {
                exec(escapeshellarg($php).' -S 127.0.0.1:8765 '.escapeshellarg($routerPath).' > /dev/null 2>&1 &');
            }
            usleep(400000);
        } catch (\Throwable) {
            return false;
        }

        return $this->loopbackPortOpen();
    }

    private function loopbackPortOpen(): bool
    {
        $fp = @fsockopen('127.0.0.1', 8765, $errno, $errstr, 0.25);
        if (! is_resource($fp)) {
            return false;
        }
        fclose($fp);

        return true;
    }

    private function phpCliExecutable(): ?string
    {
        $candidates = [
            'C:\\xampp\\php\\php.exe',
            dirname((string) PHP_BINARY).DIRECTORY_SEPARATOR.'php.exe',
        ];
        if (PHP_BINARY && ! str_contains(strtolower(PHP_BINARY), 'cgi') && is_file(PHP_BINARY)) {
            $candidates[] = PHP_BINARY;
        }

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function loopbackRouterScript(): string
    {
        return <<<'PHP'
<?php
$targetFile = __DIR__.DIRECTORY_SEPARATOR.'target.txt';
$target = is_file($targetFile) ? trim((string) file_get_contents($targetFile)) : '';
if ($target === '') {
    http_response_code(500);
    echo 'OAuth catcher sin destino.';
    return;
}
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: '.$target.($query !== '' ? '?'.$query : ''), true, 302);
echo 'Redirigiendo a Infinity…';
PHP;
    }

    public function authorizationUrl(string $state, ?string $redirectUri = null): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('backup.drive.client_id'),
            'redirect_uri' => $redirectUri ?: $this->redirectUri(),
            'response_type' => 'code',
            'scope' => (string) config('backup.drive.scope', 'https://www.googleapis.com/auth/drive.file'),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code, string $redirectUri): string
    {
        $response = Http::asForm()->timeout(20)->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('backup.drive.client_id'),
            'client_secret' => config('backup.drive.client_secret'),
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Error al intercambiar el código de Google Drive: '.$response->body());
        }

        $refresh = (string) ($response->json('refresh_token') ?? '');
        if ($refresh === '') {
            throw new RuntimeException(
                'Google no devolvió refresh_token. Revocá el acceso de la app en la cuenta Google y volvé a solicitar acceso.'
            );
        }

        return $refresh;
    }

    public function persistRefreshToken(string $refreshToken): void
    {
        config([
            'backup.drive.refresh_token' => $refreshToken,
            'backup.drive.enabled' => true,
        ]);
        app(GoogleDriveUploader::class)->forgetTokenStatusCache();

        if (app()->environment('testing')) {
            return;
        }

        $writer = new DotEnvWriter(base_path('.env'));
        $writer->set('GOOGLE_DRIVE_REFRESH_TOKEN', $refreshToken);
        $writer->set('BACKUP_DRIVE_ENABLED', 'true');

        if (is_file(base_path('bootstrap/cache/config.php'))) {
            Artisan::call('config:clear');
        }
    }
}
