<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveUploader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDriveCommand extends Command
{
    protected $signature = 'backup:drive';

    protected $description = 'Genera un backup de la BD y lo sube a Google Drive';

    public function handle(DatabaseBackupService $backupService, GoogleDriveUploader $driveUploader): int
    {
        if (! $backupService->isSupported()) {
            $this->error('Driver de BD no soportado para backup.');

            return self::FAILURE;
        }

        if (! $driveUploader->isConfigured()) {
            $this->error('Google Drive no configurado (BACKUP_DRIVE_ENABLED, refresh token, folder id).');

            return self::FAILURE;
        }

        $this->info('Generando backup y subiendo a Drive…');

        try {
            $result = $backupService->subirADrive();
            $this->info("OK: {$result['filename']} (id {$result['drive_id']})");
            Log::info('[backup:drive] OK', [
                'filename' => $result['filename'],
                'drive_id' => $result['drive_id'],
            ]);
            \App\Support\ScheduleOnceAfter::markDone('backup-drive');
            if ($result['pruned'] > 0) {
                $this->comment("Eliminados {$result['pruned']} backup(s) antiguos.");
            }
            if (! empty($result['webViewLink'])) {
                $this->line($result['webViewLink']);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[backup:drive] '.$e->getMessage(), ['exception' => $e]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
