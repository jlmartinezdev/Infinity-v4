<?php

namespace App\Services;

use App\Support\BackupScheduleConfig;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
    public function suggestedFilename(string $tipo = BackupScheduleConfig::TIPO_COMPLETO): string
    {
        $tipo = BackupScheduleConfig::normalizarTipo($tipo);
        $slug = Str::slug(config('app.name', 'backup'));
        $ts = now()->format('Y-m-d_His');
        $driver = $this->connectionInfo()['driver'];
        $ext = in_array($driver, ['mysql', 'mariadb'], true) ? 'sql' : 'sqlite';

        return "{$slug}-{$tipo}-{$ts}.{$ext}";
    }

    /**
     * Tablas que el backup esencial no incluye (estructura ni datos).
     *
     * @return list<string>
     */
    public function tablasEsencialOmitir(): array
    {
        $tablas = config('backup.esencial_omitir', []);
        if (! is_array($tablas)) {
            return [];
        }

        $out = [];
        foreach ($tablas as $tabla) {
            $nombre = strtolower(trim((string) $tabla));
            if ($nombre !== '' && preg_match('/^[a-z0-9_]+$/', $nombre)) {
                $out[] = $nombre;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Contenido SQL (MySQL/MariaDB) o ruta al archivo SQLite para descarga binaria.
     *
     * @return array{type: 'sql'|'file', content?: string, path?: string, delete_after?: bool}
     */
    public function prepareBackup(string $tipo = BackupScheduleConfig::TIPO_COMPLETO): array
    {
        $tipo = BackupScheduleConfig::normalizarTipo($tipo);
        $name = config('database.default');
        $config = config("database.connections.{$name}");
        $driver = $config['driver'] ?? '';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return [
                'type' => 'sql',
                'content' => $this->dumpMysql($config, $tipo),
            ];
        }

        if ($driver === 'sqlite') {
            $path = $this->resolveSqlitePath((string) ($config['database'] ?? ''));
            if ($path === '' || ! File::isFile($path)) {
                throw new \RuntimeException('No se encontró el archivo de base de datos SQLite.');
            }

            if ($tipo === BackupScheduleConfig::TIPO_ESENCIAL) {
                $copia = $this->sqliteEsencialTemporal($path);

                return [
                    'type' => 'file',
                    'path' => $copia,
                    'delete_after' => true,
                ];
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
    public function crearArchivoTemporal(string $tipo = BackupScheduleConfig::TIPO_COMPLETO): array
    {
        $tipo = BackupScheduleConfig::normalizarTipo($tipo);
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $filename = $this->suggestedFilename($tipo);
        $name = config('database.default');
        $config = config("database.connections.{$name}");
        $driver = $config['driver'] ?? '';

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $path = $dir.DIRECTORY_SEPARATOR.$filename;
            $this->dumpMysqlToFile($config, $path, $tipo);

            return [
                'path' => $path,
                'filename' => $filename,
                'delete_after' => true,
            ];
        }

        $prepared = $this->prepareBackup($tipo);

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
        if (! empty($prepared['delete_after']) && File::isFile($source) && realpath($source) !== realpath($path)) {
            File::delete($source);
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
    public function subirADrive(string $tipo = BackupScheduleConfig::TIPO_COMPLETO): array
    {
        if (! $this->driveUploader->isConfigured()) {
            throw new \RuntimeException('Google Drive no está configurado (enabled, refresh token, folder id).');
        }

        $temp = $this->crearArchivoTemporal($tipo);

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
     * @return list<string>
     */
    public function mysqlDumpCommand(array $config, string $tipo = BackupScheduleConfig::TIPO_COMPLETO, ?string $resultFile = null): array
    {
        $tipo = BackupScheduleConfig::normalizarTipo($tipo);
        $binary = $this->resolveMysqldumpBinary();
        $host = $config['host'] ?? '127.0.0.1';
        $port = (string) ($config['port'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
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

        if ($resultFile !== null && $resultFile !== '') {
            $command[] = '--result-file='.$resultFile;
        }

        if ($password !== '') {
            $command[] = '--password='.$password;
        }

        if ($tipo === BackupScheduleConfig::TIPO_ESENCIAL) {
            foreach ($this->tablasEsencialOmitir() as $tabla) {
                $command[] = '--ignore-table='.$database.'.'.$tabla;
            }
        }

        $command[] = $database;

        return $command;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function dumpMysql(array $config, string $tipo = BackupScheduleConfig::TIPO_COMPLETO): string
    {
        $process = new Process($this->mysqlDumpCommand($config, $tipo));
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful()) {
            $msg = trim($process->getErrorOutput() ?: $process->getOutput() ?: $process->getExitCodeText());
            throw new \RuntimeException('mysqldump falló: '.($msg !== '' ? $msg : 'código '.$process->getExitCode()));
        }

        return $process->getOutput();
    }

    /**
     * Dump MySQL a un archivo (más fiable que capturar stdout, sobre todo como servicio Windows).
     *
     * @param  array<string, mixed>  $config
     */
    public function dumpMysqlToFile(array $config, string $path, string $tipo = BackupScheduleConfig::TIPO_COMPLETO): void
    {
        $binary = $this->resolveMysqldumpBinary();
        if ($binary === 'mysqldump' || ! File::isFile($binary)) {
            throw new \RuntimeException(
                'No se encontró mysqldump. Definí MYSQLDUMP_PATH en .env (ej. C:\\xampp\\mysql\\bin\\mysqldump.exe).'
            );
        }

        $tipo = BackupScheduleConfig::normalizarTipo($tipo);
        Log::info('[backup] mysqldump', [
            'binary' => $binary,
            'database' => $config['database'] ?? '',
            'path' => $path,
            'tipo' => $tipo,
        ]);

        $process = new Process($this->mysqlDumpCommand($config, $tipo, $path));
        $process->setTimeout(3600);
        $process->run();

        if (! $process->isSuccessful() || ! File::isFile($path) || File::size($path) < 10) {
            $msg = trim($process->getErrorOutput() ?: $process->getOutput() ?: $process->getExitCodeText());
            throw new \RuntimeException('mysqldump falló: '.($msg !== '' ? $msg : 'código '.$process->getExitCode()));
        }
    }

    private function sqliteEsencialTemporal(string $origen): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $destino = $dir.DIRECTORY_SEPARATOR.'esencial-'.uniqid('', true).'.sqlite';
        if (! File::copy($origen, $destino)) {
            throw new \RuntimeException('No se pudo copiar SQLite para backup esencial.');
        }

        $pdo = new \PDO('sqlite:'.$destino);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        foreach ($this->tablasEsencialOmitir() as $tabla) {
            $existe = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name=".$pdo->quote($tabla))->fetchColumn();
            if ($existe) {
                $pdo->exec('DELETE FROM "'.$tabla.'"');
            }
        }
        $pdo->exec('VACUUM');

        return $destino;
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
