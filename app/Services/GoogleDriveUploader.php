<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleDriveUploader
{
    public function isConfigured(): bool
    {
        $d = config('backup.drive', []);

        return (bool) ($d['enabled'] ?? false)
            && filled($d['client_id'] ?? null)
            && filled($d['client_secret'] ?? null)
            && filled($d['refresh_token'] ?? null)
            && filled($d['folder_id'] ?? null);
    }

    /**
     * @return array{id: string, name: string, webViewLink?: string|null}
     */
    public function uploadFile(string $localPath, string $filename, ?string $mimeType = null): array
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Archivo local no encontrado para subir a Drive.');
        }

        $folderId = (string) config('backup.drive.folder_id');
        $accessToken = $this->accessToken();
        $mimeType = $mimeType ?: (mime_content_type($localPath) ?: 'application/octet-stream');
        $boundary = 'infinity_'.bin2hex(random_bytes(8));

        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
        ], JSON_UNESCAPED_SLASHES);

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .$metadata."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: {$mimeType}\r\n\r\n"
            .file_get_contents($localPath)."\r\n"
            ."--{$boundary}--";

        $response = Http::withToken($accessToken)
            ->withBody($body, "multipart/related; boundary={$boundary}")
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id,name,webViewLink');

        if (! $response->successful()) {
            throw new RuntimeException('Error al subir a Google Drive: '.$response->body());
        }

        return [
            'id' => (string) $response->json('id'),
            'name' => (string) $response->json('name'),
            'webViewLink' => $response->json('webViewLink'),
        ];
    }

    /**
     * Elimina backups antiguos dejando los N más recientes por prefijo de nombre.
     */
    public function pruneOldBackups(string $namePrefix, ?int $keep = null): int
    {
        $keep = $keep ?? max(1, (int) config('backup.drive.keep', 14));
        $folderId = (string) config('backup.drive.folder_id');
        $accessToken = $this->accessToken();

        $q = sprintf(
            "'%s' in parents and trashed = false and name contains '%s'",
            str_replace("'", "\\'", $folderId),
            str_replace("'", "\\'", $namePrefix)
        );

        $response = Http::withToken($accessToken)->get('https://www.googleapis.com/drive/v3/files', [
            'q' => $q,
            'fields' => 'files(id,name,createdTime)',
            'orderBy' => 'createdTime desc',
            'pageSize' => 100,
            'spaces' => 'drive',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('No se pudo listar backups en Drive: '.$response->body());
        }

        $files = $response->json('files') ?? [];
        if (! is_array($files) || count($files) <= $keep) {
            return 0;
        }

        $deleted = 0;
        foreach (array_slice($files, $keep) as $file) {
            $id = $file['id'] ?? null;
            if (! $id) {
                continue;
            }
            $del = Http::withToken($accessToken)->delete('https://www.googleapis.com/drive/v3/files/'.$id);
            if ($del->successful()) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function accessToken(): string
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('backup.drive.client_id'),
            'client_secret' => config('backup.drive.client_secret'),
            'refresh_token' => config('backup.drive.refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            $error = (string) ($response->json('error') ?? '');
            $desc = (string) ($response->json('error_description') ?? $response->body());
            $hint = ($error === 'invalid_grant')
                ? ' El refresh token expiró o fue revocado. Ejecutá: php artisan backup:drive-auth'
                : '';

            throw new RuntimeException(
                'No se pudo obtener access token de Google Drive ('.$error.': '.$desc.').'.$hint
            );
        }

        return (string) $response->json('access_token');
    }
}
