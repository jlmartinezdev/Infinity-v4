<?php

namespace App\Console\Commands;

use App\Models\WaCaso;
use App\Services\WhatsApp\Soporte\EstiloInterplus;
use Illuminate\Console\Command;

class WhatsAppSanearEstiloCasosCommand extends Command
{
    protected $signature = 'whatsapp:sanear-estilo-casos
                            {--dry-run : Muestra qué cambiaría, sin guardar}';

    protected $description = 'Reemplaza el estilo guardado por el texto limpio y apaga casos con Ueno, cuenta o voseo';

    public function handle(EstiloInterplus $estilo): int
    {
        $dry = (bool) $this->option('dry-run');
        $apagados = 0;

        foreach (WaCaso::query()->where('activo', true)->orderBy('id')->get() as $caso) {
            $blob = trim($caso->titulo.' '.$caso->texto.' '.$caso->palabras);
            if (! EstiloInterplus::textoProhibido($blob)) {
                continue;
            }
            $this->line('apagado #'.$caso->id.' '.$caso->titulo);
            if (! $dry) {
                $caso->update(['activo' => false]);
            }
            $apagados++;
        }

        if ($dry) {
            $this->info('Estilo: se reemplazaría por el texto limpio. Casos a apagar: '.$apagados);

            return self::SUCCESS;
        }

        $estilo->guardar($estilo->base());
        $this->info('Estilo reemplazado. Casos apagados: '.$apagados);

        return self::SUCCESS;
    }
}
