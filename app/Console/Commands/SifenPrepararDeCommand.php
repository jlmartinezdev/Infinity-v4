<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Services\Sifen\SifenService;
use Illuminate\Console\Command;

class SifenPrepararDeCommand extends Command
{
    protected $signature = 'sifen:preparar-de {factura : ID de factura_electronicas} {--sin-validar : Omitir validación XSD}';

    protected $description = 'Genera CDC y XML DE SIFEN v150 para una factura electrónica';

    public function handle(SifenService $sifenService): int
    {
        $factura = Factura::with(['cliente', 'detalles.impuesto'])->find($this->argument('factura'));

        if (! $factura) {
            $this->error('Factura no encontrada.');

            return self::FAILURE;
        }

        try {
            $resultado = $sifenService->prepararDocumento(
                $factura,
                ! $this->option('sin-validar')
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('CDC: '.$resultado['cdc']);
        $this->info('XML: '.$resultado['xml_path']);

        if (! $resultado['validacion']['valido']) {
            $this->warn('Validación XSD con observaciones:');
            foreach ($resultado['validacion']['errores'] as $error) {
                $this->line(' - '.$error);
            }
        } else {
            $this->info('Validación XSD: OK');
        }

        return self::SUCCESS;
    }
}
