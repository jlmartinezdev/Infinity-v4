<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    public function __construct(
        protected GoogleDriveUploader $driveUploader
    ) {}

    /**
     * @return array{driver: string, label: string, database: string|null}
     */
    public function connectionInfo(): array
    {
        $name = config('database.default');
        $config = config("database.connections.{$name}");
        $driver = $config['driver'] ?? 'unknown';

        $label = match ($driver) {
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'sqlite' => 'SQLite',
            default => $driver,
        };

        $database = $config['database'] ?? null;
        if ($driver === 'sqlite' && $database) {
            $database = $this->resolveSqlitePath((string) $database);
        }

        return [
            'driver' => $driver,
            'label' => $label,
            'database' => $database,
        ];
    }

    public function isSupported(): bool
    {
        $driver = $this->connectionInfo()['driver'];

        return in_array($driver, ['mysql', 'mariadb', 'sqlite'], true);
    }

    /**
     * Genera el nombre sugerido para el archivo de backup.
     */
    public function suggestedFilename(): string
    {
        $slug = Str::slug(config('app.name', 'backup'));
        $ts = now()->format('Y-m-d_His');
        $driver = $this->connectionInfo()['driver'];
        $ext = in_array($driver, ['mysql', 'mariadb'], true) ? 'sql' : 'sqlite';

        return "{$slug}-{$ts}.{$ext}";
    }

    /**
     * Contenido SQL (MySQL/MariaDB) o ruta al archivo SQLite para descarga binaria.
     *
     * @return array{type: 'sql'|'file', content?: string, path?: string}
     */
    public function prepareBackup(): array
    {
        $name = config('database.default');
        $config = config("database.connections.{$name}");
        $driver = $config['driver'] ?? '';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return [
                'type' => 'sql',
                'content' => $this->dumpMysql($config),
            ];
        }

        if ($driver === 'sqlite') {
            $path = $this->resolveSqlitePath((string) ($config['database'] ?? ''));
            if ($path === '' || ! File::isFile($path)) {
                throw new \RuntimeException('No se encontró el archivo de base de datos SQLite.');
            }

            return [
                'type' => 'file',
                'path' => $path,
            ];
        }

        throw new \RuntimeException('El driver de base de datos actual no admite backup desde esta pantalla.');
    }

    /**
     * Genera el backup en un archivo temporal bajo storage/app/backups.
     *
     * @return array{path: string, filename: string, delete_after: bool}
     */
    public function crearArchivoTemporal(): array
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = $this->suggestedFilename();
        $prepared = $this->prepareBackup();

        if ($prepared['type'] === 'sql') {
            $path = $dir.DIRECTORY_SEPARATOR.$filename;
            File::put($path, $prepared['content'] ?? '');

            return [
                'path' => $path,
                'filename' => $filename,
                'delete_after' => true,
            ];
        }

        $source = $prepared['path'] ?? '';
        $path = $dir.DIRECTORY_SEPARATOR.$filename;
        if (! File::copy($source, $path)) {
            throw new \RuntimeException('No se pudo copiar el archivo SQLite para backup.');
        }

        return [
            'path' => $path,
            'filename' => $filename,
            'delete_after' => true,
        ];
    }

    /**
     * @return array{filename: string, drive_id: string, webViewLink: ?string, pruned: int}
     */
    public function subirADrive(): array
    {
        if (! $this->driveUploader->isConfigured()) {
            throw new \RuntimeException('Google Drive no está configurado (enabled, refresh token, folder id).');
        }

        $temp = $this->crearArchivoTemporal();

        try {
            $mime = str_ends_with(strtolower($temp['filename']), '.sql')
                ? 'application/sql'
                : 'application/octet-stream';

            $uploaded = $this->driveUploader->uploadFile($temp['path'], $temp['filename'], $mime);
            $prefix = Str::slug(config('app.name', 'backup')).'-';
            $pruned = $this->driveUploader->pruneOldBackups($prefix);

            return [
                'filename' => $temp['filename'],
                'drive_id' => $uploaded['id'],
                'webViewLink' => $uploaded['webViewLink'] ?? null,
                'pruned' => $pruned,
            ];
        } finally {
            if (($temp['delete_after'] ?? false) && File::isFile($temp['path'])) {
                File::delete($temp['path']);
            }
        }
    }

    /**
     * Ruta al ejecutable mysqldump: .env MYSQLDUMP_PATH, rutas típicas de XAMPP en Windows, o "mysqldump" del PATH.
     */
    private function resolveMysqldumpBinary(): string
    {
        $configured = config('backup.mysqldump_path');
        if (is_string($configured) && $configured !== '' && File::isFile($configured)) {
            return $configured;
        }

        $candidates = [];

        // Proyecto en ...\xampp\htdocs\algo → usar ...\xampp\mysql\bin\mysqldump.exe
        if (strtolower(basename(dirname(base_path()))) === 'htdocs') {
            $xamppRoot = dirname(dirname(base_path()));
            $candidates[] = $xamppRoot.DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe';
        }

        $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $candidates[] = 'D:\\xampp\\mysql\\bin\\mysqldump.exe';
        $candidates[] = 'E:\\xampp\\mysql\\bin\\mysqldump.exe';

        foreach ($candidates as $path) {
            if ($path !== '' && File::isFile($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config): string
    {
        $binary = $this->resolveMysqldumpBinary();

        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? '3306');
        $database = $config['database'] ?? '';
        $username = $config['username'] ?? 'root';
        $password = (string) ($config['password'] ?? '');

        $command = [
            $binary,
            '--user='.$username,
            '--host='.$host,
            '--port='.$port,
            '--single-transaction',
            '--routines',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
        ];

        if ($password !== '') {
            $command[] = '--password='.$password;
        }

        $command[] = $database;

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $msg = trim($process->getErrorOutput() ?: $process->getOutput() ?: $process->getExitCodeText());
            throw new \RuntimeException('mysqldump falló: '.($msg !== '' ? $msg : 'código '.$process->getExitCode()));
        }

        return $process->getOutput();
    }

    private function resolveSqlitePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (File::isFile($path)) {
            return $path;
        }
        if (File::isFile(base_path($path))) {
            return base_path($path);
        }
        if (File::isFile(database_path(basename($path)))) {
            return database_path(basename($path));
        }

        return $path;
    }
}
