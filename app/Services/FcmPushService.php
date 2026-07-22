<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Push FCM opcional (Legacy HTTP API).
 * Configurar FCM_SERVER_KEY y FCM_STAFF_TOPIC en .env para activar.
 */
class FcmPushService
{
    public function notifyStaff(string $title, string $body, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key') ?: env('FCM_SERVER_KEY');
        if (! $serverKey) {
            Log::info('FCM omitido (sin FCM_SERVER_KEY)', compact('title', 'body', 'data'));

            return;
        }

        $topic = config('services.fcm.staff_topic') ?: env('FCM_STAFF_TOPIC', 'staff');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => '/topics/'.$topic,
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => array_merge($data, [
                    'title' => $title,
                    'body' => $body,
                ]),
            ]);

            if (! $response->successful()) {
                Log::warning('FCM falló', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM excepción: '.$e->getMessage());
        }
    }
}
