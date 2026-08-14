<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveUploader;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        protected DatabaseBackupService $backupService,
        protected GoogleDriveUploader $driveUploader
    ) {}

    public function index()
    {
        $info = $this->backupService->connectionInfo();
        $supported = $this->backupService->isSupported();
        $driveReady = $this->driveUploader->isConfigured();
        $driveFolderId = (string) config('backup.drive.folder_id', '');
        $hora = \App\Support\BackupScheduleConfig::hora();
        $schedule = [
            'worker' => \App\Support\ScheduleOnceAfter::workerActivo(),
            'latido' => \App\Support\ScheduleOnceAfter::ultimoLatido(),
            'backup_ok_hoy' => \App\Support\ScheduleOnceAfter::doneToday('backup-drive'),
            'hora' => $hora,
        ];

        return view('configuracion.backup', compact('info', 'supported', 'driveReady', 'driveFolderId', 'schedule', 'hora'));
    }

    public function updateHora(\Illuminate\Http\Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hora' => ['required', 'date_format:H:i'],
        ]);
        \App\Support\BackupScheduleConfig::guardarHora($validated['hora']);

        return redirect()
            ->route('configuracion.backup')
            ->with('success', 'Hora de backup automático guardada ('.$validated['hora'].').');
    }

    public function download(): StreamedResponse|BinaryFileResponse|RedirectResponse
    {
        if (! $this->backupService->isSupported()) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'El tipo de base de datos actual no admite backup desde esta pantalla.');
        }

        set_time_limit(3600);

        try {
            $prepared = $this->backupService->prepareBackup();
            $filename = $this->backupService->suggestedFilename();

            if ($prepared['type'] === 'sql') {
                $content = $prepared['content'] ?? '';

                return response()->streamDownload(function () use ($content) {
                    echo $content;
                }, $filename, [
                    'Content-Type' => 'application/sql; charset=UTF-8',
                ]);
            }

            $path = $prepared['path'] ?? '';

            return response()->download($path, $filename, [
                'Content-Type' => 'application/octet-stream',
            ]);
        } catch (\Throwable $e) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', $e->getMessage());
        }
    }

    public function uploadDrive(): RedirectResponse
    {
        if (! $this->backupService->isSupported()) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'El tipo de base de datos actual no admite backup desde esta pantalla.');
        }

        if (! $this->driveUploader->isConfigured()) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'Google Drive no está configurado. Revisá .env (BACKUP_DRIVE_ENABLED, token y folder id).');
        }

        set_time_limit(3600);

        try {
            $result = $this->backupService->subirADrive();
            $msg = "Backup «{$result['filename']}» subido a Google Drive.";
            if ($result['pruned'] > 0) {
                $msg .= " Se eliminaron {$result['pruned']} copia(s) antigua(s).";
            }

            return redirect()
                ->route('configuracion.backup')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', $e->getMessage());
        }
    }
}
