<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\ClientePortalUserService;
use Illuminate\Console\Command;

class SyncClientePortalUsersCommand extends Command
{
    protected $signature = 'clientes:sync-portal-users
                            {--reset-passwords : Restablece la contraseña al número de documento}
                            {--solo-activos : Solo clientes en estado activo}';

    protected $description = 'Crea/actualiza usuarios de app para clientes (usuario y contraseña = documento)';

    public function handle(ClientePortalUserService $portal): int
    {
        $query = Cliente::query()->orderBy('cliente_id');

        if ($this->option('solo-activos')) {
            $query->where('estado', 'activo');
        }

        $creados = 0;
        $actualizados = 0;
        $errores = 0;
        $reset = (bool) $this->option('reset-passwords');

        $this->info('Sincronizando usuarios portal de clientes...');

        $query->chunkById(100, function ($clientes) use ($portal, $reset, &$creados, &$actualizados, &$errores) {
            foreach ($clientes as $cliente) {
                try {
                    $result = $portal->syncParaCliente($cliente, $reset);
                    if ($result['created']) {
                        $creados++;
                    } else {
                        $actualizados++;
                    }
                } catch (\Throwable $e) {
                    $errores++;
                    $this->error("Cliente #{$cliente->cliente_id}: {$e->getMessage()}");
                }
            }
        }, 'cliente_id');

        $this->info("Listo. Creados: {$creados}. Actualizados: {$actualizados}. Errores: {$errores}.");

        return $errores > 0 ? self::FAILURE : self::SUCCESS;
    }
}
