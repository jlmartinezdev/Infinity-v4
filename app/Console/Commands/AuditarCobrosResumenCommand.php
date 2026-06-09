<?php

namespace App\Console\Commands;

use App\Models\CobroResumen;
use App\Services\CobrosResumenService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AuditarCobrosResumenCommand extends Command
{
    protected $signature = 'cobros:auditar-resumen
                            {--mes= : Mes de referencia YYYY-MM}
                            {--desde= : Primer mes YYYY-MM (inclusive)}
                            {--hasta= : Último mes YYYY-MM (inclusive)}
                            {--meses=6 : Cantidad de meses hacia atrás si no se indica --mes/--desde}
                            {--solo-diferencias : Mostrar solo meses con diferencias}
                            {--fix : Recalcular meses con diferencias en cobros_resumen}
                            {--fail-on-diff : Código de salida 1 si hay diferencias}';

    protected $description = 'Audita cobros_resumen comparando la tabla con totales recalculados desde cobros y facturas.';

    /** @var list<string> */
    private const CAMPOS = [
        'total_facturado' => 'Facturado',
        'total_pendiente' => 'Pendiente',
        'total_cobrado' => 'Total cobrado',
        'pago_adelantado' => 'Pago adelantado',
        'pago_atrasado' => 'Pago atrasado',
    ];

    public function handle(CobrosResumenService $service): int
    {
        $soloDiferencias = (bool) $this->option('solo-diferencias');
        $fix = (bool) $this->option('fix');
        $failOnDiff = (bool) $this->option('fail-on-diff');

        $meses = $this->resolverMeses();
        if ($meses === null) {
            return self::FAILURE;
        }

        if ($meses === []) {
            $this->info('No hay meses para auditar.');

            return self::SUCCESS;
        }

        $this->info('Auditoría cobros_resumen vs cálculo en vivo');
        $this->line('Meses auditados: '.count($meses));
        $this->newLine();

        $filasResumen = [];
        $filasDetalle = [];
        $mesesConDiff = [];
        $totalDiffs = 0;

        foreach ($meses as $mesCarbon) {
            $esperado = $service->totalesEsperadosMes($mesCarbon);
            $mesClave = $esperado['mes'];
            $guardado = CobroResumen::query()->where('mes', $mesClave)->first();

            $diffMes = 0;
            $valsGuardado = [];
            $valsEsperado = [];
            $valsDiff = [];

            foreach (self::CAMPOS as $campo => $etiqueta) {
                $enTabla = round((float) ($guardado?->{$campo} ?? 0), 2);
                $calc = round((float) $esperado[$campo], 2);
                $delta = round($calc - $enTabla, 2);

                $valsGuardado[$campo] = $enTabla;
                $valsEsperado[$campo] = $calc;
                $valsDiff[$campo] = $delta;

                if (abs($delta) > 0.009) {
                    $diffMes++;
                    $totalDiffs++;
                    $filasDetalle[] = [
                        'mes' => Carbon::parse($mesClave)->translatedFormat('M Y'),
                        'campo' => $etiqueta,
                        'en_tabla' => $this->fmt($enTabla),
                        'esperado' => $this->fmt($calc),
                        'diferencia' => $this->fmtSigned($delta),
                    ];
                }
            }

            if ($diffMes > 0) {
                $mesesConDiff[] = $mesCarbon;
            }

            if ($soloDiferencias && $diffMes === 0) {
                continue;
            }

            $filasResumen[] = [
                'Mes' => Carbon::parse($mesClave)->translatedFormat('M Y'),
                'Facturado Δ' => $this->fmtSigned($valsDiff['total_facturado']),
                'Pendiente Δ' => $this->fmtSigned($valsDiff['total_pendiente']),
                'Cobrado Δ' => $this->fmtSigned($valsDiff['total_cobrado']),
                'Adelantado Δ' => $this->fmtSigned($valsDiff['pago_adelantado']),
                'Atrasado Δ' => $this->fmtSigned($valsDiff['pago_atrasado']),
                'Estado' => $diffMes === 0 ? 'OK' : 'DIFF',
            ];
        }

        if ($filasResumen !== []) {
            $this->table(
                ['Mes', 'Facturado Δ', 'Pendiente Δ', 'Cobrado Δ', 'Adelantado Δ', 'Atrasado Δ', 'Estado'],
                $filasResumen
            );
        } else {
            $this->info($soloDiferencias ? 'Sin diferencias en el rango indicado.' : 'Sin datos.');
        }

        if ($filasDetalle !== []) {
            $this->newLine();
            $this->warn('Detalle de diferencias:');
            $this->table(['Mes', 'Campo', 'En tabla', 'Esperado', 'Diferencia'], array_map(fn ($r) => [
                $r['mes'],
                $r['campo'],
                $r['en_tabla'],
                $r['esperado'],
                $r['diferencia'],
            ], $filasDetalle));
        }

        $this->newLine();
        $this->line('Meses con diferencias: '.count($mesesConDiff));
        $this->line('Campos desviados: '.$totalDiffs);

        if ($fix && $mesesConDiff !== []) {
            $this->newLine();
            $this->info('Recalculando meses con diferencias...');
            foreach ($mesesConDiff as $mesCarbon) {
                $service->recalcularMes($mesCarbon);
                $this->line('  ✓ '. $mesCarbon->translatedFormat('M Y'));
            }
            $this->info('Corrección aplicada. Ejecute de nuevo sin --fix para verificar.');
        } elseif ($fix) {
            $this->info('No hubo meses que corregir.');
        }

        if ($failOnDiff && $totalDiffs > 0) {
            return self::FAILURE;
        }

        if ($totalDiffs === 0) {
            $this->info('OK: cobros_resumen coincide con el cálculo en vivo.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Carbon>|null
     */
    private function resolverMeses(): ?array
    {
        $mesOpcion = trim((string) ($this->option('mes') ?? ''));
        $desdeOpcion = trim((string) ($this->option('desde') ?? ''));
        $hastaOpcion = trim((string) ($this->option('hasta') ?? ''));

        if ($mesOpcion !== '') {
            try {
                return [Carbon::createFromFormat('Y-m', $mesOpcion)->startOfMonth()];
            } catch (\Throwable) {
                $this->error('Formato inválido en --mes. Usa YYYY-MM.');

                return null;
            }
        }

        if ($desdeOpcion !== '' || $hastaOpcion !== '') {
            try {
                $desde = $desdeOpcion !== ''
                    ? Carbon::createFromFormat('Y-m', $desdeOpcion)->startOfMonth()
                    : Carbon::createFromFormat('Y-m', $hastaOpcion)->startOfMonth();
                $hasta = $hastaOpcion !== ''
                    ? Carbon::createFromFormat('Y-m', $hastaOpcion)->startOfMonth()
                    : now()->startOfMonth();
            } catch (\Throwable) {
                $this->error('Formato inválido en --desde/--hasta. Usa YYYY-MM.');

                return null;
            }

            if ($desde->gt($hasta)) {
                [$desde, $hasta] = [$hasta, $desde];
            }

            $meses = [];
            $cursor = $desde->copy();
            while ($cursor->lte($hasta)) {
                $meses[] = $cursor->copy();
                $cursor->addMonthNoOverflow();
            }

            return $meses;
        }

        $cantidad = max(1, (int) $this->option('meses'));
        $meses = [];
        for ($i = $cantidad - 1; $i >= 0; $i--) {
            $meses[] = now()->copy()->startOfMonth()->subMonths($i);
        }

        return $meses;
    }

    private function fmt(float $n): string
    {
        return number_format($n, 0, ',', '.').' PYG';
    }

    private function fmtSigned(float $n): string
    {
        if (abs($n) <= 0.009) {
            return '0';
        }

        $sign = $n > 0 ? '+' : '';

        return $sign.number_format($n, 0, ',', '.');
    }
}
