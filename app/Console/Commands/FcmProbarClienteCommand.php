<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FcmPushService;
use Illuminate\Console\Command;

class FcmProbarClienteCommand extends Command
{
    protected $signature = 'fcm:probar-cliente
                            {identificador : usuario_id, cliente_id o documento/cédula}
                            {--title=Prueba Infinity : Título}
                            {--body=Notificación de prueba para cliente : Cuerpo}';

    protected $description = 'Envía un push de prueba al token FCM del usuario portal del cliente';

    public function handle(FcmPushService $fcm): int
    {
        $id = trim((string) $this->argument('identificador'));
        $user = null;

        if (ctype_digit($id)) {
            $user = User::query()->where('usuario_id', (int) $id)->whereNotNull('cliente_id')->first()
                ?: User::query()->where('cliente_id', (int) $id)->whereNotNull('push_token')->first();
        }

        if (! $user) {
            $user = User::query()
                ->whereNotNull('cliente_id')
                ->whereHas('cliente', fn ($q) => $q->where('cedula', $id))
                ->first();
        }

        if (! $user) {
            $this->error('No se encontró usuario portal para: '.$id);

            return self::FAILURE;
        }

        if (! $user->push_token) {
            $this->error('El usuario portal (usuario_id='.$user->usuario_id.', cliente_id='.$user->cliente_id.') no tiene push_token. La app debe llamar POST /portal/save-push-token tras el login.');

            return self::FAILURE;
        }

        $this->info('Enviando a cliente_id='.$user->cliente_id.' usuario_id='.$user->usuario_id.'…');
        $fcm->notifyUser(
            $user,
            (string) $this->option('title'),
            (string) $this->option('body'),
            ['tipo' => 'prueba_fcm_cliente']
        );
        $this->info('Listo. Revisá el celular del cliente y storage/logs/laravel.log');

        return self::SUCCESS;
    }
}
