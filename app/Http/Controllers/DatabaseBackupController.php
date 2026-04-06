<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        protected DatabaseBackupService $backupService
    ) {}

    public function index()
    {
        $info = $this->backupService->connectionInfo();
        $supported = $this->backupService->isSupported();

        return view('configuracion.backup', compact('info', 'supported'));
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
}
