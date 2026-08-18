<?php

namespace App\Console\Commands;

use App\Models\FacturaInterna;
use App\Models\FacturacionParametro;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CorregirVencimientoFacturasInstalacionCommand extends Command
{
    protected $signature = 'facturas:corregir-vencimiento-instalacion
                            {--factura_id= : Corregir solo esta factura interna}
                            {--fix : Aplicar correcciones (sin esto solo audita)}';

    protected $description = 'Corrige vencimientos de facturas internas pendientes que usaron emisión + N días, al día configurado del mes siguiente al período (igual que las automáticas).';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $facturaId = $this->option('factura_id') ? (int) $this->option('factura_id') : null;
        $diaVencimiento = FacturacionParametro::diaVencimiento();
        $diasOffset = FacturacionParametro::diasVencimientoFactura();

        $query = FacturaInterna::query()
            ->with('cliente')
            ->whereNotNull('fecha_emision')
            ->whereNotNull('fecha_vencimiento')
            ->whereIn('estado', ['pendiente', 'emitida'])
            ->where(function ($q) {
                $q->whereNull('tipo_factura')
                    ->orWhere('tipo_factura', '!=', FacturaInterna::TIPO_SERVICIO_ESPECIAL);
            })
            ->where(function ($q) {
                $q->whereNull('observaciones')
                    ->orWhere('observaciones', 'not like', 'Saldo pendiente%');
            })
            ->whereRaw(FacturaInterna::sqlSaldoPendienteExpr().' > 0.00001')
            ->where(function ($q) use ($diasOffset) {
                $q->whereRaw('DATEDIFF(fecha_vencimiento, fecha_emision) = ?', [$diasOffset])
                    ->orWhere('observaciones', 'like', '%factura prorrateada por instalación%');
            })
            ->orderBy('id');

        if ($facturaId) {
            $query->where('id', $facturaId);
        }

        $facturas = $query->get();
        $filas = [];

        foreach ($facturas as $factura) {
            $ref = $factura->periodo_hasta ?? $factura->fecha_emision;
            $correcta = FacturacionParametro::fechaVencimientoMesSiguiente($ref);
            $actual = $factura->fecha_vencimiento?->toDateString();

            if ($actual === $correcta) {
                continue;
            }

            $filas[] = [
                'factura' => $factura,
                'actual' => $actual ?? '—',
                'correcta' => $correcta,
            ];
        }

        $this->info('Corrección de vencimiento — emisión + '.$diasOffset.' días → día '.$diaVencimiento.' del mes siguiente al período');
        $this->line('Modo: '.($fix ? 'CORREGIR' : 'AUDITORÍA (use --fix para aplicar)'));
        $this->line('Facturas a corregir: '.count($filas));

        if ($filas === []) {
            $this->newLine();
            $this->info('OK: no hay facturas pendientes con ese patrón de vencimiento.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Factura', 'Cliente', 'Período', 'Emisión', 'Venc. actual', 'Venc. correcto'],
            array_map(function (array $fila) {
                $f = $fila['factura'];
                $cliente = $f->cliente
                    ? trim(($f->cliente->nombre ?? '').' '.($f->cliente->apellido ?? ''))
                    : '—';

                return [
                    $f->id,
                    $f->cliente_id.' — '.$cliente,
                    ($f->periodo_desde?->format('d/m/Y') ?? '—').' – '.($f->periodo_hasta?->format('d/m/Y') ?? '—'),
                    $f->fecha_emision?->format('d/m/Y') ?? '—',
                    $fila['actual'] === '—' ? '—' : Carbon::parse($fila['actual'])->format('d/m/Y'),
                    Carbon::parse($fila['correcta'])->format('d/m/Y'),
                ];
            }, $filas)
        );

        if (! $fix) {
            $this->newLine();
            $this->comment('Ejecute con --fix para aplicar la corrección en '.count($filas).' factura(s).');

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($filas as $fila) {
            $fila['factura']->update([
                'fecha_vencimiento' => $fila['correcta'],
            ]);
            $ok++;
            $this->line(sprintf(
                '  ✓ Factura #%d: %s → %s',
                $fila['factura']->id,
                $fila['actual'],
                $fila['correcta']
            ));
        }

        $this->newLine();
        $this->info("Finalizado: {$ok} factura(s) actualizada(s).");

        return self::SUCCESS;
    }
}
