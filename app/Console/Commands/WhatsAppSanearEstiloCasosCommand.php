<?php

namespace App\Console\Commands;

use App\Models\WaCaso;
use App\Services\WhatsApp\Soporte\EstiloInterplus;
use Illuminate\Console\Command;

class WhatsAppSanearEstiloCasosCommand extends Command
{
    protected $signature = 'whatsapp:sanear-estilo-casos
                            {--dry-run : Muestra qué cambiaría, sin guardar}';

    protected $description = 'Deja el estilo limpio, borra cuentas y pasa los casos a usted, con título corto';

    public function handle(EstiloInterplus $estilo): int
    {
        $dry = (bool) $this->option('dry-run');
        $borrados = 0;
        $apagados = 0;
        $corregidos = 0;

        foreach (WaCaso::query()->orderBy('id')->get() as $caso) {
            $texto = (string) $caso->texto;
            if (EstiloInterplus::esDatoDeCuenta($texto)) {
                $this->line('borrado #'.$caso->id.' '.$caso->titulo);
                if (! $dry) {
                    $caso->delete();
                }
                $borrados++;
                continue;
            }

            $eraMalo = EstiloInterplus::textoProhibido($texto);
            $nuevo = EstiloInterplus::reescribirCaso($texto);
            if ($nuevo === null) {
                if ($caso->activo) {
                    $this->line('apagado #'.$caso->id.' '.$caso->titulo);
                    if (! $dry) {
                        $caso->update(['activo' => false]);
                    }
                    $apagados++;
                }
                continue;
            }

            $activo = $caso->activo || $eraMalo;
            $titulo = EstiloInterplus::tituloCorto($nuevo);
            $palabras = EstiloInterplus::palabrasDeEtiqueta($caso->etiqueta);
            $cambia = $nuevo !== $texto
                || $titulo !== (string) $caso->titulo
                || $palabras !== (string) $caso->palabras
                || (bool) $caso->activo !== $activo;
            if (! $cambia) {
                continue;
            }
            $this->line(($activo ? 'corregido' : 'ajustado').' #'.$caso->id.' '.$titulo);
            if (! $dry) {
                $caso->update([
                    'texto' => $nuevo,
                    'titulo' => $titulo,
                    'palabras' => $palabras,
                    'activo' => $activo,
                ]);
            }
            $corregidos++;
        }

        $duplicados = $this->apagarDuplicados($dry);

        if ($dry) {
            $this->info("Sin guardar. Borrados: {$borrados}. Apagados: {$apagados}. Corregidos: {$corregidos}. Duplicados: {$duplicados}.");

            return self::SUCCESS;
        }

        $estilo->guardar($estilo->base());
        $this->info("Estilo reemplazado. Borrados: {$borrados}. Apagados: {$apagados}. Corregidos: {$corregidos}. Duplicados: {$duplicados}.");

        return self::SUCCESS;
    }

    private function apagarDuplicados(bool $dry): int
    {
        $vistos = [];
        $n = 0;
        foreach (WaCaso::query()->where('activo', true)->orderBy('id')->get() as $caso) {
            $clave = mb_strtolower(trim((string) $caso->texto));
            if ($clave === '') {
                continue;
            }
            if (isset($vistos[$clave])) {
                $this->line('duplicado #'.$caso->id);
                if (! $dry) {
                    $caso->update(['activo' => false]);
                }
                $n++;
                continue;
            }
            $vistos[$clave] = true;
        }

        return $n;
    }
}
