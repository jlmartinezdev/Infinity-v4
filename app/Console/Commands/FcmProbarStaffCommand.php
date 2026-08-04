<?php

namespace App\Console\Commands;

use App\Services\FcmPushService;
use Illuminate\Console\Command;

class FcmProbarStaffCommand extends Command
{
    protected $signature = 'fcm:probar-staff
                            {--title=Prueba Infinity : Título}
                            {--body=Notificación de prueba desde el backend : Cuerpo}
                            {--tipo=flota : Deep-link tipo (solicitud|visita|flota|pedido_instalacion)}
                            {--id= : ID de entidad para deep-link}';

    protected $description = 'Envía un push de prueba al topic staff (FCM HTTP v1)';

    public function handle(FcmPushService $fcm): int
    {
        $path = config('services.fcm.service_account_path') ?: env('FCM_SERVICE_ACCOUNT_PATH');
        if (! $path) {
            $this->error('Falta FCM_SERVICE_ACCOUNT_PATH en .env');
            $this->line('Firebase Console → Project settings → Service accounts → Generate new private key');
            $this->line('Guardá el JSON (ej. storage/app/firebase-service-account.json) y en .env:');
            $this->line('  FCM_SERVICE_ACCOUNT_PATH=storage/app/firebase-service-account.json');
            $this->line('  FCM_PROJECT_ID=tu-project-id   (opcional si viene en el JSON)');
            $this->line('  FCM_STAFF_TOPIC=staff');

            return self::FAILURE;
        }

        $full = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $path)
            ? $path
            : base_path($path);

        if (! is_readable($full)) {
            $this->error('No se puede leer: '.$full);

            return self::FAILURE;
        }

        $title = (string) $this->option('title');
        $body = (string) $this->option('body');
        $tipo = (string) $this->option('tipo');
        $id = $this->option('id');

        $data = [
            'tipo' => $tipo !== '' ? $tipo : 'flota',
            'title' => $title,
            'body' => $body,
        ];
        if ($id !== null && $id !== '') {
            $data['id'] = (string) $id;
            if (in_array($data['tipo'], ['visita', 'ticket', 'reporte', 'urgencia'], true)) {
                $data['visita_id'] = (string) $id;
                $data['ticket_id'] = (string) $id;
            }
            if (in_array($data['tipo'], ['solicitud', 'alta', 'pedido', 'registro'], true)) {
                $data['solicitud_id'] = (string) $id;
            }
            if (in_array($data['tipo'], ['pedido_instalacion', 'instalacion'], true)) {
                $data['pedido_instalacion_id'] = (string) $id;
                $data['pedido_id'] = (string) $id;
            }
        }

        $this->info('Enviando (HTTP v1) a topic "'.config('services.fcm.staff_topic', 'staff').'"…');
        $this->line('data: '.json_encode($data, JSON_UNESCAPED_UNICODE));
        $fcm->notifyStaff($title, $body, $data);
        $this->info('Listo. Revisá el celular y storage/logs/laravel.log (FCM OK / FCM falló).');

        return self::SUCCESS;
    }
}
