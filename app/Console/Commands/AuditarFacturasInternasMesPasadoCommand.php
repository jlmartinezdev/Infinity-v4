<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AuditarFacturasInternasMesPasadoCommand extends Command
{
    /** Mismos estados que `facturas:crear-internas-automaticas`. */
    private const ESTADOS_SERVICIO_FACTURA_AUTOMATICA = [
        Servicio::ESTADO_ACTIVO,
        Servicio::ESTADO_SUSPENDIDO,
        Servicio::ESTADO_CORTADO,
    ];

    protected $signature = 'facturas:auditar-internas-mes-pasado
                            {--mes= : Mes del período facturado (YYYY-MM). Por defecto: mes anterior}
                            {--solo-faltantes : Mostrar solo clientes que debían facturarse y no tienen factura}
                            {--export= : Ruta CSV opcional para exportar el resultado}';

    protected $description = 'Audita clientes activos que debían tener factura interna mensual (servicios A/S/C) y no la tienen para el mes indicado.';

    public function handle(): int
    {
        $mesOpcion = trim((string) ($this->option('mes') ?? ''));
        $soloFaltantes = (bool) $this->option('solo-faltantes');
        $exportPath = trim((string) ($this->option('export') ?? ''));

        if ($mesOpcion !== '') {
            try {
                $mesReferencia = Carbon::createFromFormat('Y-m', $mesOpcion)->startOfMonth();
            } catch (\Throwable) {
                $this->error('Formato inválido en --mes. Usa YYYY-MM, por ejemplo 2026-05.');

                return self::FAILURE;
            }
        } else {
            $mesReferencia = now()->subMonthNoOverflow()->startOfMonth();
        }

        $periodoDesde = $mesReferencia->copy()->startOfMonth();
        $periodoHasta = $mesReferencia->copy()->endOfMonth();
        $periodoHastaStr = $periodoHasta->toDateString();

        $clientes = Cliente::query()
            ->where('estado', 'activo')
            ->whereHas('servicios', function ($q) {
                $q->whereIn('estado', self::ESTADOS_SERVICIO_FACTURA_AUTOMATICA);
            })
            ->with(['servicios' => fn ($q) => $q->whereIn('estado', self::ESTADOS_SERVICIO_FACTURA_AUTOMATICA)->with('plan')])
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get();

        $clienteIds = $clientes->pluck('cliente_id')->all();

        $facturasPorCliente = FacturaInterna::query()
            ->whereIn('cliente_id', $clienteIds)
            ->whereDate('periodo_hasta', $periodoHastaStr)
            ->where(function ($q) {
                $q->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
                    ->orWhereNull('tipo_factura');
            })
            ->get(['id', 'cliente_id', 'total', 'estado', 'fecha_emision'])
            ->groupBy('cliente_id');

        $filas = [];

        foreach ($clientes as $cliente) {
            $serviciosFacturables = $cliente->servicios;
            $serviciosSinAcuerdo = $serviciosFacturables->reject(
                fn (Servicio $s) => $s->acuerdoAplicaEnPeriodo($periodoDesde, $periodoHasta)
            );

            if ($serviciosFacturables->isEmpty()) {
                continue;
            }

            if ($serviciosSinAcuerdo->isEmpty()) {
                $filas[] = [
                    'cliente_id' => $cliente->cliente_id,
                    'nombre' => trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')),
                    'cedula' => $cliente->cedula ?? '',
                    'servicios' => $serviciosFacturables->count(),
                    'estado' => 'excluido_acuerdo',
                    'factura_id' => null,
                    'detalle' => 'Todos los servicios A/S/C con acuerdo sin facturación en el período',
                ];

                continue;
            }

            $facturas = $facturasPorCliente->get($cliente->cliente_id, collect());

            if ($facturas->isNotEmpty()) {
                $factura = $facturas->sortByDesc('id')->first();
                $filas[] = [
                    'cliente_id' => $cliente->cliente_id,
                    'nombre' => trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')),
                    'cedula' => $cliente->cedula ?? '',
                    'servicios' => $serviciosSinAcuerdo->count(),
                    'estado' => 'ok',
                    'factura_id' => $factura->id,
                    'detalle' => sprintf(
                        'Factura #%d (%s, %s Gs.)',
                        $factura->id,
                        $factura->estado,
                        number_format((float) $factura->total, 0, ',', '.')
                    ),
                ];

                continue;
            }

            $planes = $serviciosSinAcuerdo->map(fn (Servicio $s) => $s->plan?->nombre ?? '—')->unique()->implode(', ');

            $filas[] = [
                'cliente_id' => $cliente->cliente_id,
                'nombre' => trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? '')),
                'cedula' => $cliente->cedula ?? '',
                'servicios' => $serviciosSinAcuerdo->count(),
                'estado' => 'faltante',
                'factura_id' => null,
                'detalle' => 'Sin factura mensual. Servicios: '.$planes,
            ];
        }

        $faltantes = collect($filas)->where('estado', 'faltante')->values();
        $ok = collect($filas)->where('estado', 'ok')->count();
        $excluidos = collect($filas)->where('estado', 'excluido_acuerdo')->count();

        $this->info(sprintf(
            'Auditoría facturas internas — período %s a %s',
            $periodoDesde->format('d/m/Y'),
            $periodoHasta->format('d/m/Y')
        ));
        $this->line('Criterio: cliente activo, servicios A/S/C facturables (sin acuerdo en el período).');
        $this->newLine();
        $this->line('Con factura del período: '.$ok);
        $this->line('Sin factura (faltantes): '.$faltantes->count());
        $this->line('Excluidos por acuerdo: '.$excluidos);

        $mostrar = collect($filas);
        if ($soloFaltantes) {
            $mostrar = $faltantes;
        }

        if ($mostrar->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['ID', 'Cliente', 'Cédula', 'Serv.', 'Estado', 'Factura', 'Detalle'],
                $mostrar->map(fn (array $r) => [
                    $r['cliente_id'],
                    $r['nombre'],
                    $r['cedula'],
                    $r['servicios'],
                    $r['estado'],
                    $r['factura_id'] ?? '—',
                    $r['detalle'],
                ])->all()
            );
        }

        if ($exportPath !== '') {
            $this->exportarCsv($exportPath, $filas);
            $this->info('Exportado: '.$exportPath);
        }

        if ($faltantes->isEmpty()) {
            $this->newLine();
            $this->info('OK: no hay clientes facturables sin factura del período.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Hay '.$faltantes->count().' cliente(s) sin factura interna del período.');

        return self::FAILURE;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function exportarCsv(string $path, array $filas): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error('No se pudo escribir el archivo CSV.');

            return;
        }

        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, ['cliente_id', 'nombre', 'cedula', 'servicios', 'estado', 'factura_id', 'detalle'], ';');
        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['cliente_id'],
                $fila['nombre'],
                $fila['cedula'],
                $fila['servicios'],
                $fila['estado'],
                $fila['factura_id'] ?? '',
                $fila['detalle'],
            ], ';');
        }
        fclose($handle);
    }
}
