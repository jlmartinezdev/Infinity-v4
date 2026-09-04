<?php

namespace App\Http\Controllers;

use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveAuthService;
use App\Services\GoogleDriveUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(
        protected DatabaseBackupService $backupService,
        protected GoogleDriveUploader $driveUploader,
        protected GoogleDriveAuthService $driveAuth
    ) {}

    public function index()
    {
        $info = $this->backupService->connectionInfo();
        $supported = $this->backupService->isSupported();
        $driveReady = $this->driveUploader->isConfigured();
        $driveCanAuth = $this->driveAuth->hasOAuthClient();
        $driveTokenStatus = $driveCanAuth ? $this->driveUploader->probeRefreshToken() : 'no_client';
        $driveFolderId = (string) config('backup.drive.folder_id', '');
        $driveRedirectUri = $driveCanAuth ? $this->driveAuth->redirectUri() : '';
        $driveUsesLoopback = $driveCanAuth && $this->driveAuth->isLoopbackRedirect();
        $hora = \App\Support\BackupScheduleConfig::hora();
        $schedule = [
            'worker' => \App\Support\ScheduleOnceAfter::workerActivo(),
            'latido' => \App\Support\ScheduleOnceAfter::ultimoLatido(),
            'backup_ok_hoy' => \App\Support\ScheduleOnceAfter::doneToday('backup-drive'),
            'hora' => $hora,
        ];

        return view('configuracion.backup', compact(
            'info',
            'supported',
            'driveReady',
            'driveCanAuth',
            'driveTokenStatus',
            'driveFolderId',
            'driveRedirectUri',
            'driveUsesLoopback',
            'schedule',
            'hora'
        ));
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
            $this->driveUploader->forgetTokenStatusCache();
            $redirect = redirect()
                ->route('configuracion.backup')
                ->with('error', $e->getMessage());

            if (GoogleDriveUploader::isExpiredTokenMessage($e->getMessage())) {
                $redirect->with('drive_token_expired', true);
            }

            return $redirect;
        }
    }

    public function solicitarAcceso(): RedirectResponse
    {
        if (! $this->driveAuth->hasOAuthClient()) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'Faltan GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET en .env');
        }

        return redirect()->route('configuracion.backup.drive.auth.show');
    }

    public function mostrarSolicitarAcceso()
    {
        if (! $this->driveAuth->hasOAuthClient()) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'Faltan GOOGLE_DRIVE_CLIENT_ID / GOOGLE_DRIVE_CLIENT_SECRET en .env');
        }

        $redirectUri = $this->driveAuth->redirectUri();
        $state = (string) session('backup_drive_oauth_state', '');
        if ($state === '') {
            $state = bin2hex(random_bytes(16));
        }

        session([
            'backup_drive_oauth_state' => $state,
            'backup_drive_oauth_redirect_uri' => $redirectUri,
        ]);

        $authUrl = $this->driveAuth->authorizationUrl($state, $redirectUri);

        if (! $this->driveAuth->isLoopbackRedirect($redirectUri)) {
            return redirect()->away($authUrl);
        }

        $catcherOk = $this->driveAuth->ensureLoopbackCatcher(
            route('configuracion.backup.drive.callback')
        );

        return view('configuracion.backup-drive-auth', compact('authUrl', 'redirectUri', 'catcherOk'));
    }

    public function completarDesdeUrl(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'oauth_url' => ['required', 'string', 'max:4000'],
        ]);

        $params = $this->driveAuth->parsePastedOauthUrl($validated['oauth_url']);

        return $this->finalizarOauth(
            (string) ($params['code'] ?? ''),
            (string) ($params['state'] ?? ''),
            (string) ($params['error'] ?? '')
        );
    }

    public function driveCallback(Request $request): RedirectResponse
    {
        return $this->finalizarOauth(
            (string) $request->input('code', ''),
            (string) $request->input('state', ''),
            (string) $request->input('error', '')
        );
    }

    private function finalizarOauth(string $code, string $state, string $error): RedirectResponse
    {
        if ($error !== '') {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'Google no otorgó el acceso: '.$error);
        }

        $expected = (string) session('backup_drive_oauth_state', '');
        $redirectUri = (string) session('backup_drive_oauth_redirect_uri', $this->driveAuth->redirectUri());
        session()->forget(['backup_drive_oauth_state', 'backup_drive_oauth_redirect_uri']);

        if ($expected === '' || $state === '' || ! hash_equals($expected, $state) || $code === '') {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', 'La autorización de Drive no es válida. Volvé a pulsar «Solicitar acceso».');
        }

        try {
            $refresh = $this->driveAuth->exchangeCode($code, $redirectUri);
            $this->driveAuth->persistRefreshToken($refresh);
        } catch (\Throwable $e) {
            return redirect()
                ->route('configuracion.backup')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('configuracion.backup')
            ->with('success', 'Acceso a Google Drive autorizado. Ya podés subir backups.');
    }
}
