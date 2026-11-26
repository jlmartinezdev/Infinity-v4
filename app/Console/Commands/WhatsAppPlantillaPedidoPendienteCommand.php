<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\PlantillaPedidoPendiente;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

class WhatsAppPlantillaPedidoPendienteCommand extends Command
{
    protected $signature = 'whatsapp:plantilla-pedido-pendiente';

    protected $description = 'Crea en Meta la plantilla para retomar un pedido de instalación pendiente';

    public function handle(WhatsAppService $whatsapp): int
    {
        $nombre = PlantillaPedidoPendiente::nombre();
        $idioma = PlantillaPedidoPendiente::idioma();

        if (! $whatsapp->isConfigured() && ! filled(config('whatsapp.token'))) {
            $this->error('WhatsApp no está configurado.');

            return self::FAILURE;
        }

        $existente = $whatsapp->buscarPlantilla($nombre);
        if (is_array($existente)) {
            $this->info(sprintf(
                'Ya existe «%s» (%s · %s).',
                $nombre,
                $existente['status'] ?? '-',
                $existente['language'] ?? $idioma
            ));

            return self::SUCCESS;
        }

        $creada = $whatsapp->crearPlantilla(PlantillaPedidoPendiente::definicion());
        if (! ($creada['ok'] ?? false)) {
            $this->error('No se pudo crear la plantilla: '.($creada['error'] ?? 'error Meta'));

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Plantilla «%s» enviada a Meta. Estado: %s.',
            $nombre,
            $creada['status'] ?? 'PENDING'
        ));
        $this->line('Hasta que quede APPROVED no se puede usar fuera de la ventana de 24 h.');

        return self::SUCCESS;
    }
}
