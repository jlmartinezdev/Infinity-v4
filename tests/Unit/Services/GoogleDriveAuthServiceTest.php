<?php

namespace Tests\Unit\Services;

use App\Services\GoogleDriveAuthService;
use App\Services\GoogleDriveUploader;
use App\Support\DotEnvWriter;
use Tests\TestCase;

class GoogleDriveAuthServiceTest extends TestCase
{
    public function test_detecta_token_expirado(): void
    {
        $this->assertTrue(GoogleDriveUploader::isExpiredTokenMessage(
            'invalid_grant: Token has been expired or revoked.'
        ));
        $this->assertTrue(GoogleDriveUploader::isExpiredTokenMessage(
            'El refresh token expiró o fue revocado. En Backup, pulsá «Solicitar acceso».'
        ));
        $this->assertFalse(GoogleDriveUploader::isExpiredTokenMessage(
            'Error al subir a Google Drive: quota exceeded'
        ));
    }

    public function test_por_defecto_usa_loopback_de_escritorio(): void
    {
        config(['backup.drive.redirect_uri' => '']);

        $auth = app(GoogleDriveAuthService::class);

        $this->assertSame('http://127.0.0.1:8765/', $auth->redirectUri());
        $this->assertTrue($auth->isLoopbackRedirect());
    }

    public function test_parsea_url_pegada_del_loopback(): void
    {
        $params = app(GoogleDriveAuthService::class)->parsePastedOauthUrl(
            'http://127.0.0.1:8765/?state=abc123&code=4/0Axxx&scope=https://www.googleapis.com/auth/drive.file'
        );

        $this->assertSame('abc123', $params['state'] ?? null);
        $this->assertSame('4/0Axxx', $params['code'] ?? null);
    }

    public function test_arma_url_de_autorizacion(): void
    {
        config([
            'backup.drive.client_id' => 'client-test',
            'backup.drive.scope' => 'https://www.googleapis.com/auth/drive.file',
        ]);

        $url = app(GoogleDriveAuthService::class)->authorizationUrl('abc123', 'https://example.test/callback');

        $this->assertStringContainsString('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('client_id=client-test', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
        $this->assertStringContainsString('state=abc123', $url);
        $this->assertStringContainsString(rawurlencode('https://example.test/callback'), $url);
    }

    public function test_dotenv_writer_actualiza_y_cita_valores(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'infinity-dotenv-'.uniqid('', true);
        file_put_contents($path, "FOO=bar\nBACKUP_DRIVE_ENABLED=false\n");

        $writer = new DotEnvWriter($path);
        $writer->set('BACKUP_DRIVE_ENABLED', 'true');
        $writer->set('GOOGLE_DRIVE_REFRESH_TOKEN', '1//abc/def');

        $content = file_get_contents($path);
        $this->assertStringContainsString('BACKUP_DRIVE_ENABLED=true', $content);
        $this->assertStringContainsString('GOOGLE_DRIVE_REFRESH_TOKEN="1//abc/def"', $content);

        unlink($path);
    }
}
