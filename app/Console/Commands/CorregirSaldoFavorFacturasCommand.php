<?php

namespace App\Console\Commands;

use App\Models\FacturaInterna;
use App\Services\FacturacionService;
use Illuminate\Console\Command;

class CorregirSaldoFavorFacturasCommand extends Command
{
    protected $signature = 'facturas:corregir-saldo-favor-aplicado
                            {--factura_id= : Corregir solo esta factura interna}
                            {--cliente_id= : Filtrar por cliente}
                            {--fix : Aplicar correcciones (sin esto solo audita)}
                            {--fail-on-diff : Código 1 si hay facturas afectadas sin corregir}';

    protected $description = 'Detecta y corrige facturas internas donde el saldo a favor se aplicó solo al subtotal (sin IVA).';

    public function handle(FacturacionService $facturacion): int
    {
        $fix = (bool) $this->option('fix');
        $failOnDiff = (bool) $this->option('fail-on-diff');
        $facturaId = $this->option('factura_id') ? (int) $this->option('factura_id') : null;
        $clienteId = $this->option('cliente_id') ? (int) $this->option('cliente_id') : null;

        $afectadas = $facturacion->listarFacturasSaldoFavorMalAplicado($facturaId, $clienteId);

        $this->info('Corrección saldo a favor mal aplicado en facturas internas');
        $this->line('Modo: '.($fix ? 'CORREGIR' : 'AUDITORÍA (use --fix para aplicar)'));
        $this->line('Facturas afectadas: '.$afectadas->count());

        if ($afectadas->isEmpty()) {
            $this->newLine();
            $this->info('OK: no hay facturas con este problema.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Factura', 'Cliente', 'Estado', 'Bruto', 'Saldo appl.', 'Total actual', 'Δ corregir', 'Pagado', 'Δ saldo serv.', 'Saldo disp.', 'Obs.'],
            $afectadas->map(fn (array $a) => [
                $a['factura_id'],
                $a['cliente_id'].' — '.($a['cliente'] ?: '—'),
                $a['estado'],
                number_format($a['bruto_total'], 0, ',', '.'),
                number_format($a['aplicado_saldo'], 0, ',', '.'),
                number_format($a['total_actual'], 0, ',', '.'),
                number_format($a['delta_total'], 0, ',', '.'),
                number_format($a['monto_pagado'], 0, ',', '.'),
                number_format($a['deducir_saldo_servicio'], 0, ',', '.'),
                number_format($a['saldo_servicios_disponible'], 0, ',', '.'),
                $a['motivo_skip'] ?? '—',
            ])->all()
        );

        $corregibles = $afectadas->filter(fn (array $a) => empty($a['motivo_skip']));
        $bloqueadas = $afectadas->count() - $corregibles->count();

        if ($bloqueadas > 0) {
            $this->warn("{$bloqueadas} factura(s) no se pueden corregir automáticamente (ver columna Obs.).");
        }

        if (! $fix) {
            $this->newLine();
            $this->comment('Ejecute con --fix para aplicar la corrección en '.$corregibles->count().' factura(s).');

            return $failOnDiff ? self::FAILURE : self::SUCCESS;
        }

        $ok = 0;
        $errores = 0;

        foreach ($corregibles as $analisis) {
            $resultado = $facturacion->corregirSaldoFavorMalAplicado(
                FacturaInterna::findOrFail($analisis['factura_id'])
            );

            if ($resultado['ok']) {
                $ok++;
                $this->line('  ✓ '.$resultado['message']);
            } else {
                $errores++;
                $this->error('  ✗ '.$resultado['message']);
            }
        }

        $this->newLine();
        $this->info("Corregidas: {$ok}. Errores: {$errores}.");

        if ($errores > 0 || ($bloqueadas > 0 && $failOnDiff)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
