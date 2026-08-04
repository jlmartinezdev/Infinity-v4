<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Services\ClientePortalUserService;
use Illuminate\Console\Command;

class SyncClientePortalUsersCommand extends Command
{
    protected $signature = 'clientes:sync-portal-users
                            {--limpiar-passwords-documento : Vacía solo contraseñas que aún son el CI/documento}
                            {--solo-activos : Solo clientes en estado activo}';

    protected $description = 'Crea/actualiza usuarios de app para clientes (usuario = documento; sin contraseña hasta alta)';

    public function handle(ClientePortalUserService $portal): int
    {
        if ($this->option('limpiar-passwords-documento')) {
            $n = $portal->limpiarContrasenasDocumentoLegacy();
            $this->info("Contraseñas documento vaciadas: {$n}. Las claves PLUS u otras se conservaron.");

            return self::SUCCESS;
        }

        $query = Cliente::query()->orderBy('cliente_id');

        if ($this->option('solo-activos')) {
            $query->where('estado', 'activo');
        }

        $creados = 0;
        $actualizados = 0;
        $errores = 0;

        $this->info('Sincronizando usuarios portal de clientes...');

        $query->chunkById(100, function ($clientes) use ($portal, &$creados, &$actualizados, &$errores) {
            foreach ($clientes as $cliente) {
                try {
                    $result = $portal->syncParaCliente($cliente, false);
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
