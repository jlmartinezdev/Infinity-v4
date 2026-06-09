<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * @deprecated Usar facturas:auditar-internas-mes-pasado
 */
class VerificarFacturasMesPasadoClientesActivosCommand extends Command
{
    protected $signature = 'facturas:verificar-mes-pasado-clientes-activos
                            {--solo-faltantes : Mostrar solo clientes sin factura del mes pasado}
                            {--mes= : Mes del período (YYYY-MM)}';

    protected $description = 'Alias legado: audita facturas internas faltantes del mes pasado.';

    public function handle(): int
    {
        $params = [];
        if ($this->option('solo-faltantes')) {
            $params['--solo-faltantes'] = true;
        }
        if ($this->option('mes')) {
            $params['--mes'] = $this->option('mes');
        }

        return $this->call('facturas:auditar-internas-mes-pasado', $params);
    }
}
