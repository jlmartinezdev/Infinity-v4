<?php

namespace App\Console\Commands;

use App\Models\FacturaInterna;
use App\Models\FacturaInternaDetalle;
use App\Models\Servicio;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AuditarFacturasInternasTvCommand extends Command
{
    /** Mismos estados que la factura mensual automática. */
    private const ESTADOS_SERVICIO = [
        Servicio::ESTADO_ACTIVO,
        Servicio::ESTADO_SUSPENDIDO,
        Servicio::ESTADO_CORTADO,
    ];

    protected $signature = 'facturas:auditar-internas-tv
                            {--mes= : Mes del período facturado (YYYY-MM). Por defecto: mes anterior}
                            {--solo-faltantes : Mostrar solo servicios TV sin línea en factura}
                            {--incluir-promo : Listar también servicios TV en promo (precio_app = 0)}
                            {--export= : Ruta CSV opcional para exportar el resultado}';

    protected $description = 'Audita servicios con App TV (no promo) que debían tener línea «Servicio especial» en la factura interna del mes.';

    public function handle(): int
    {
        $mesOpcion = trim((string) ($this->option('mes') ?? ''));
        $soloFaltantes = (bool) $this->option('solo-faltantes');
        $incluirPromo = (bool) $this->option('incluir-promo');
        $exportPath = trim((string) ($this->option('export') ?? ''));

        if ($mesOpcion !== '') {
            try {
                $mesReferencia = Carbon::createFromFormat('Y-m', $mesOpcion)->startOfMonth();
            } catch (\Throwable) {
                $this->error('Formato inválido en --mes. Usa YYYY-MM, por ejemplo 2026-07.');

                return self::FAILURE;
            }
        } else {
            $mesReferencia = now()->subMonthNoOverflow()->startOfMonth();
        }

        $periodoDesde = $mesReferencia->copy()->startOfMonth();
        $periodoHasta = $mesReferencia->copy()->endOfMonth();
        $periodoHastaStr = $periodoHasta->toDateString();

        $servicios = Servicio::query()
            ->where('app_tv', true)
            ->whereIn('estado', self::ESTADOS_SERVICIO)
            ->whereHas('cliente', fn ($q) => $q->where('estado', 'activo'))
            ->with(['cliente:cliente_id,nombre,apellido,cedula,estado', 'plan:plan_id,nombre'])
            ->orderBy('cliente_id')
            ->orderBy('servicio_id')
            ->get();

        $servicioIds = $servicios->pluck('servicio_id')->all();

        // Detalles TV del período: descripción «Servicio especial…» ligada a factura mensual del mes.
        $lineasTvPorServicio = collect();
        if ($servicioIds !== []) {
            $lineasTvPorServicio = FacturaInternaDetalle::query()
                ->whereIn('servicio_id', $servicioIds)
                ->where('descripcion', 'like', 'Servicio especial%')
                ->whereHas('facturaInterna', function ($q) use ($periodoHastaStr) {
                    $q->whereDate('periodo_hasta', $periodoHastaStr)
                        ->where(function ($q2) {
                            $q2->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
                                ->orWhereNull('tipo_factura');
                        });
                })
                ->with(['facturaInterna:id,cliente_id,estado,total,periodo_hasta'])
                ->get()
                ->groupBy('servicio_id');
        }

        $filas = [];

        foreach ($servicios as $servicio) {
            $cliente = $servicio->cliente;
            $nombreCliente = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
            $precioApp = (float) ($servicio->precio_app ?? 0);

            if ($servicio->acuerdoAplicaEnPeriodo($periodoDesde, $periodoHasta)) {
                $filas[] = $this->fila($servicio, $nombreCliente, 'excluido_acuerdo', null, $precioApp, 'Acuerdo sin facturación en el período (no se factura plan ni TV)');

                continue;
            }

            if ($precioApp <= 0) {
                if ($incluirPromo) {
                    $filas[] = $this->fila($servicio, $nombreCliente, 'excluido_promo', null, $precioApp, 'App TV en promo / precio_app = 0 (no genera línea TV)');
                }

                continue;
            }

            $lineas = $lineasTvPorServicio->get($servicio->servicio_id, collect());

            if ($lineas->isNotEmpty()) {
                $detalle = $lineas->sortByDesc('id')->first();
                $factura = $detalle->facturaInterna;
                $filas[] = $this->fila(
                    $servicio,
                    $nombreCliente,
                    'ok',
                    $factura?->id,
                    $precioApp,
                    sprintf(
                        'Línea TV en factura #%d (%s Gs. detalle)',
                        $factura?->id ?? 0,
                        number_format((float) $detalle->total, 0, ',', '.')
                    )
                );

                continue;
            }

            // ¿Hay factura mensual del cliente pero sin línea TV?
            $facturaCliente = FacturaInterna::query()
                ->where('cliente_id', $servicio->cliente_id)
                ->whereDate('periodo_hasta', $periodoHastaStr)
                ->where(function ($q) {
                    $q->where('tipo_factura', FacturaInterna::TIPO_SERVICIO)
                        ->orWhereNull('tipo_factura');
                })
                ->orderByDesc('id')
                ->first(['id', 'estado', 'total']);

            if ($facturaCliente) {
                $filas[] = $this->fila(
                    $servicio,
                    $nombreCliente,
                    'faltante',
                    $facturaCliente->id,
                    $precioApp,
                    sprintf(
                        'Factura #%d existe pero sin línea «Servicio especial» (precio_app %s Gs.)',
                        $facturaCliente->id,
                        number_format($precioApp, 0, ',', '.')
                    )
                );

                continue;
            }

            $filas[] = $this->fila(
                $servicio,
                $nombreCliente,
                'faltante',
                null,
                $precioApp,
                sprintf(
                    'Sin factura mensual del período. Debía incluir TV %s Gs. (plan: %s)',
                    number_format($precioApp, 0, ',', '.'),
                    $servicio->plan?->nombre ?? '—'
                )
            );
        }

        $coleccion = collect($filas);
        $faltantes = $coleccion->where('estado', 'faltante')->values();
        $ok = $coleccion->where('estado', 'ok')->count();
        $excluidosAcuerdo = $coleccion->where('estado', 'excluido_acuerdo')->count();
        $excluidosPromo = $coleccion->where('estado', 'excluido_promo')->count();
        $enPromoOmitidos = $servicios->filter(fn (Servicio $s) => (float) ($s->precio_app ?? 0) <= 0
            && ! $s->acuerdoAplicaEnPeriodo($periodoDesde, $periodoHasta))->count();

        $this->info(sprintf(
            'Auditoría facturas internas TV — período %s a %s',
            $periodoDesde->format('d/m/Y'),
            $periodoHasta->format('d/m/Y')
        ));
        $this->line('Criterio: cliente activo, servicio A/S/C con app_tv, precio_app > 0 (no promo), sin acuerdo.');
        $this->newLine();
        $this->line('Servicios TV con app_tv: '.$servicios->count());
        $this->line('Con línea TV en factura: '.$ok);
        $this->line('Sin línea TV (faltantes): '.$faltantes->count());
        $this->line('Excluidos por acuerdo: '.$excluidosAcuerdo);
        $this->line('En promo / precio 0: '.$enPromoOmitidos.($incluirPromo ? " (listados: {$excluidosPromo})" : ' (omitidos; use --incluir-promo para listar)'));

        $mostrar = $coleccion;
        if ($soloFaltantes) {
            $mostrar = $faltantes;
        }

        if ($mostrar->isNotEmpty()) {
            $this->newLine();
            $this->table(
                ['Cliente', 'Nombre', 'Serv.', 'Precio TV', 'Estado', 'Factura', 'Detalle'],
                $mostrar->map(fn (array $r) => [
                    $r['cliente_id'],
                    $r['nombre'],
                    $r['servicio_id'],
                    number_format((float) $r['precio_app'], 0, ',', '.'),
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
            $this->info('OK: todos los servicios TV facturables tienen línea en factura del período.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Hay '.$faltantes->count().' servicio(s) TV sin línea en factura del período.');

        return self::FAILURE;
    }

    /**
     * @return array{cliente_id: int, nombre: string, servicio_id: int, cedula: string, precio_app: float, estado: string, factura_id: int|null, detalle: string}
     */
    private function fila(Servicio $servicio, string $nombreCliente, string $estado, ?int $facturaId, float $precioApp, string $detalle): array
    {
        return [
            'cliente_id' => (int) $servicio->cliente_id,
            'nombre' => $nombreCliente,
            'servicio_id' => (int) $servicio->servicio_id,
            'cedula' => (string) ($servicio->cliente?->cedula ?? ''),
            'precio_app' => $precioApp,
            'estado' => $estado,
            'factura_id' => $facturaId,
            'detalle' => $detalle,
        ];
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
        fputcsv($handle, ['cliente_id', 'nombre', 'cedula', 'servicio_id', 'precio_app', 'estado', 'factura_id', 'detalle'], ';');
        foreach ($filas as $fila) {
            fputcsv($handle, [
                $fila['cliente_id'],
                $fila['nombre'],
                $fila['cedula'],
                $fila['servicio_id'],
                $fila['precio_app'],
                $fila['estado'],
                $fila['factura_id'] ?? '',
                $fila['detalle'],
            ], ';');
        }
        fclose($handle);
    }
}
