<?php

namespace App\Console\Commands;

use App\Models\Factura;
use App\Services\Sifen\SifenService;
use Illuminate\Console\Command;

class SifenEmitirDeCommand extends Command
{
    protected $signature = 'sifen:emitir-de {factura : ID de factura_electronicas} {--sin-envio : Firmar y generar KuDE sin enviar a SIFEN}';

    protected $description = 'Emite factura electrónica completa (DE + firma + QR + SIFEN + KuDE)';

    public function handle(SifenService $sifenService): int
    {
        $factura = Factura::with(['cliente', 'detalles.impuesto'])->find($this->argument('factura'));

        if (! $factura) {
            $this->error('Factura no encontrada.');

            return self::FAILURE;
        }

        try {
            $resultado = $sifenService->emitirDocumento($factura, ! $this->option('sin-envio'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('CDC: '.$resultado['cdc']);
        $this->info('XML: '.$resultado['xml_path']);
        $this->info('PDF: '.$resultado['pdf_path']);

        if ($resultado['sifen']) {
            $this->info('SIFEN ['.($resultado['sifen']['codigo'] ?? '?').']: '.($resultado['sifen']['mensaje'] ?? ''));
        } elseif ($this->option('sin-envio')) {
            $this->warn('Emitido localmente sin envío a SIFEN (--sin-envio).');
        }

        return self::SUCCESS;
    }
}
