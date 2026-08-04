<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Staff\StaffUbicacionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffUbicacionController extends ApiController
{
    public function __construct(
        private readonly StaffUbicacionService $ubicaciones
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'heading' => ['nullable', 'numeric', 'between:0,360'],
            'en_turno' => ['nullable', 'boolean'],
            'visita_id' => ['nullable', 'integer'],
        ]);

        $result = $this->ubicaciones->reportar(
            (int) $request->user()->usuario_id,
            [
                'lat' => (float) $validated['lat'],
                'lng' => (float) $validated['lng'],
                'accuracy' => $validated['accuracy'] ?? null,
                'heading' => $validated['heading'] ?? null,
                'en_turno' => array_key_exists('en_turno', $validated)
                    ? (bool) $validated['en_turno']
                    : true,
                'visita_id' => $validated['visita_id'] ?? null,
            ]
        );

        // Rate-limit: responder 200 para no romper UX de la app (soft-fail amigable).
        return $this->ok(null, $result['throttled'] ? 'ok' : 'ok');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('rol');

        if (! $user->puedeVerFlotaStaff()) {
            return $this->fail('Acceso solo para administradores.', 403);
        }

        return $this->ok($this->ubicaciones->listarFlotaPayload());
    }

    /**
     * SSE opcional: poll DB cada ~5 s + heartbeat 30 s.
     */
    public function stream(Request $request): StreamedResponse
    {
        $user = $request->user();
        $user->loadMissing('rol');

        if (! $user->puedeVerFlotaStaff()) {
            return response()->stream(function () {
                echo "event: error\n";
                echo 'data: '.json_encode(['success' => false, 'message' => 'Acceso solo para administradores.'])."\n\n";
            }, 403, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () {
            @set_time_limit(0);
            ignore_user_abort(true);

            $lastFingerprint = null;
            $lastHeartbeat = time();
            $started = time();
            $maxSeconds = 300;

            while (! connection_aborted() && (time() - $started) < $maxSeconds) {
                $payload = app(StaffUbicacionService::class)->listarFlotaPayload();
                $fingerprint = md5(json_encode($payload));

                if ($fingerprint !== $lastFingerprint) {
                    $lastFingerprint = $fingerprint;
                    echo "event: ubicacion_update\n";
                    echo 'data: '.json_encode(['success' => true, 'data' => $payload], JSON_UNESCAPED_UNICODE)."\n\n";
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }

                if ((time() - $lastHeartbeat) >= 30) {
                    $lastHeartbeat = time();
                    echo ": heartbeat\n\n";
                    if (ob_get_level() > 0) {
                        @ob_flush();
                    }
                    @flush();
                }

                sleep(5);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
