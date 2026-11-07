<?php

namespace App\Console\Commands;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Support\XlsxHoja;
use App\Support\XlsxWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ExportarFacturacionExcelCommand extends Command
{
    protected $signature = 'facturacion:exportar-excel
                            {--desde= : Desde YYYY-MM-DD. Facturas por fecha de creación, recibos por fecha de pago}
                            {--hasta= : Hasta YYYY-MM-DD}
                            {--salida= : Archivo .xlsx o carpeta destino. Por defecto storage/app/exportaciones/fecha/facturacion.xlsx}';

    protected $description = 'Exporta facturas internas y recibos a un libro Excel (.xlsx) para análisis.';

    public function handle(): int
    {
        $desde = $this->fechaOpcion('desde');
        $hasta = $this->fechaOpcion('hasta');
        if ($desde === false || $hasta === false) {
            return self::FAILURE;
        }
        if ($desde && $hasta && $desde->gt($hasta)) {
            $this->error('La fecha --desde no puede ser posterior a --hasta.');

            return self::FAILURE;
        }

        $archivo = $this->archivoSalida();
        $this->info('Exportando a '.$archivo);

        $libro = new XlsxWriter;
        $facturas = $this->exportarFacturas($libro, $desde, $hasta);
        $detalles = $this->exportarDetalles($libro, $desde, $hasta);
        $notas = $this->exportarNotas($libro, $desde, $hasta);
        $recibos = $this->exportarRecibos($libro, $desde, $hasta);
        $aplicaciones = $this->exportarAplicaciones($libro, $desde, $hasta);
        $libro->guardar($archivo);

        $this->newLine();
        $this->table(
            ['Hoja', 'Filas'],
            [
                ['Facturas', $facturas],
                ['Detalle', $detalles],
                ['Notas de credito', $notas],
                ['Recibos', $recibos],
                ['Recibos y facturas', $aplicaciones],
            ]
        );
        $this->line('Los montos quedan como números. Mes ciclo es el mes del dashboard: el mes siguiente a la creación de la factura.');

        return self::SUCCESS;
    }

    private function exportarFacturas(XlsxWriter $libro, ?Carbon $desde, ?Carbon $hasta): int
    {
        $cobrado = DB::table('cobro_factura_interna')
            ->selectRaw('factura_interna_id, SUM(monto) as aplicado')
            ->groupBy('factura_interna_id');
        $notas = DB::table('factura_interna_notas_credito')
            ->selectRaw('factura_interna_id, SUM(monto) as notas')
            ->groupBy('factura_interna_id');

        $query = DB::table('factura_internas as fi')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 'fi.cliente_id')
            ->leftJoin('users as u', 'u.usuario_id', '=', 'fi.usuario_id')
            ->leftJoinSub($cobrado, 'cob', 'cob.factura_interna_id', '=', 'fi.id')
            ->leftJoinSub($notas, 'nc', 'nc.factura_interna_id', '=', 'fi.id')
            ->select([
                'fi.id',
                'fi.cliente_id',
                'c.cedula',
                'fi.tipo_factura',
                'fi.estado',
                'fi.created_at',
                'fi.fecha_emision',
                'fi.fecha_vencimiento',
                'fi.fecha_pago',
                'fi.periodo_desde',
                'fi.periodo_hasta',
                'fi.subtotal',
                'fi.descuento',
                'fi.total_impuestos',
                'fi.total',
                'u.name as usuario',
                'fi.observaciones',
            ])
            ->selectRaw($this->sqlCliente().' as cliente')
            ->selectRaw('COALESCE(cob.aplicado, 0) as cobrado_aplicado')
            ->selectRaw('COALESCE(nc.notas, 0) as notas_credito')
            ->selectRaw('GREATEST(fi.total - LEAST(fi.total, COALESCE(cob.aplicado, 0)) - COALESCE(nc.notas, 0), 0) as saldo')
            ->selectRaw("DATE_FORMAT(DATE_ADD(fi.created_at, INTERVAL 1 MONTH), '%Y-%m') as mes_ciclo")
            ->orderBy('fi.id');

        $this->filtrarCreacion($query, 'fi.created_at', $desde, $hasta);

        return $this->volcar($libro->hoja('Facturas', [
            'Factura',
            'Cliente ID',
            'Cliente',
            'Cedula',
            'Tipo',
            'Estado',
            'Creada',
            'Emision',
            'Vencimiento',
            'Fecha pago',
            'Periodo desde',
            'Periodo hasta',
            'Subtotal',
            'Descuento',
            'Impuestos',
            'Total',
            'Cobrado aplicado',
            'Notas de credito',
            'Saldo',
            'Mes ciclo',
            'Usuario',
            'Observaciones',
        ], ['i', 'i', 's', 's', 's', 's', 's', 'd', 'd', 'd', 'd', 'd', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 's', 's', 's']), $query, function (object $row): array {
            return [
                $row->id,
                $row->cliente_id,
                $row->cliente,
                $row->cedula,
                FacturaInterna::tiposFactura()[$row->tipo_factura] ?? $row->tipo_factura,
                $row->estado,
                $this->fechaHora($row->created_at),
                $this->fecha($row->fecha_emision),
                $this->fecha($row->fecha_vencimiento),
                $this->fecha($row->fecha_pago),
                $this->fecha($row->periodo_desde),
                $this->fecha($row->periodo_hasta),
                $row->subtotal,
                $row->descuento,
                $row->total_impuestos,
                $row->total,
                $row->cobrado_aplicado,
                $row->notas_credito,
                $row->saldo,
                $row->mes_ciclo,
                $row->usuario,
                $row->observaciones,
            ];
        });
    }

    private function exportarDetalles(XlsxWriter $libro, ?Carbon $desde, ?Carbon $hasta): int
    {
        $query = DB::table('factura_interna_detalles as d')
            ->join('factura_internas as fi', 'fi.id', '=', 'd.factura_interna_id')
            ->select([
                'd.factura_interna_id',
                'fi.cliente_id',
                'd.servicio_id',
                'd.descripcion',
                'd.cantidad',
                'd.precio_unitario',
                'd.subtotal',
                'd.monto_impuesto',
                'd.total',
            ])
            ->orderBy('d.id');

        $this->filtrarCreacion($query, 'fi.created_at', $desde, $hasta);

        return $this->volcar($libro->hoja('Detalle', [
            'Factura',
            'Cliente ID',
            'Servicio ID',
            'Descripcion',
            'Cantidad',
            'Precio unitario',
            'Subtotal',
            'Impuesto',
            'Total',
        ], ['i', 'i', 'i', 's', 'n', 'n', 'n', 'n', 'n']), $query, function (object $row): array {
            return [
                $row->factura_interna_id,
                $row->cliente_id,
                $row->servicio_id,
                $row->descripcion,
                $row->cantidad,
                $row->precio_unitario,
                $row->subtotal,
                $row->monto_impuesto,
                $row->total,
            ];
        });
    }

    private function exportarNotas(XlsxWriter $libro, ?Carbon $desde, ?Carbon $hasta): int
    {
        $query = DB::table('factura_interna_notas_credito as n')
            ->join('factura_internas as fi', 'fi.id', '=', 'n.factura_interna_id')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 'fi.cliente_id')
            ->leftJoin('users as u', 'u.usuario_id', '=', 'n.usuario_id')
            ->select([
                'n.id',
                'n.factura_interna_id',
                'fi.cliente_id',
                'n.monto',
                'n.motivo',
                'n.created_at',
                'u.name as usuario',
            ])
            ->selectRaw($this->sqlCliente().' as cliente')
            ->orderBy('n.id');

        $this->filtrarCreacion($query, 'fi.created_at', $desde, $hasta);

        return $this->volcar($libro->hoja('Notas de credito', [
            'Nota',
            'Factura',
            'Cliente ID',
            'Cliente',
            'Monto',
            'Motivo',
            'Creada',
            'Usuario',
        ], ['i', 'i', 'i', 's', 'n', 's', 's', 's']), $query, function (object $row): array {
            return [
                $row->id,
                $row->factura_interna_id,
                $row->cliente_id,
                $row->cliente,
                $row->monto,
                $row->motivo,
                $this->fechaHora($row->created_at),
                $row->usuario,
            ];
        });
    }

    private function exportarRecibos(XlsxWriter $libro, ?Carbon $desde, ?Carbon $hasta): int
    {
        $aplicado = DB::table('cobro_factura_interna')
            ->selectRaw('cobro_id, SUM(monto) as aplicado')
            ->groupBy('cobro_id');

        $query = DB::table('cobros as cb')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 'cb.cliente_id')
            ->leftJoin('users as u', 'u.usuario_id', '=', 'cb.usuario_id')
            ->leftJoinSub($aplicado, 'ap', 'ap.cobro_id', '=', 'cb.id')
            ->select([
                'cb.id',
                'cb.numero_recibo',
                'cb.fecha_pago',
                'cb.cliente_id',
                'c.cedula',
                'cb.forma_pago',
                'cb.monto',
                'cb.referencia',
                'cb.concepto',
                'cb.observaciones',
                'u.name as usuario',
                'cb.created_at',
            ])
            ->selectRaw($this->sqlCliente().' as cliente')
            ->selectRaw('COALESCE(ap.aplicado, 0) as aplicado')
            ->selectRaw('GREATEST(cb.monto - COALESCE(ap.aplicado, 0), 0) as saldo_a_favor')
            ->orderBy('cb.id');

        $this->filtrarFecha($query, 'cb.fecha_pago', $desde, $hasta);

        return $this->volcar($libro->hoja('Recibos', [
            'Cobro',
            'Recibo',
            'Fecha pago',
            'Cliente ID',
            'Cliente',
            'Cedula',
            'Forma de pago',
            'Monto',
            'Aplicado a facturas',
            'Saldo a favor',
            'Referencia',
            'Concepto',
            'Observaciones',
            'Cobrado por',
            'Creado',
        ], ['i', 's', 'd', 'i', 's', 's', 's', 'n', 'n', 'n', 's', 's', 's', 's', 's']), $query, function (object $row): array {
            return [
                $row->id,
                $row->numero_recibo,
                $this->fecha($row->fecha_pago),
                $row->cliente_id,
                $row->cliente,
                $row->cedula,
                Cobro::formasPago()[$row->forma_pago] ?? $row->forma_pago,
                $row->monto,
                $row->aplicado,
                $row->saldo_a_favor,
                $row->referencia,
                $row->concepto,
                $row->observaciones,
                $row->usuario,
                $this->fechaHora($row->created_at),
            ];
        });
    }

    private function exportarAplicaciones(XlsxWriter $libro, ?Carbon $desde, ?Carbon $hasta): int
    {
        $query = DB::table('cobro_factura_interna as cfi')
            ->join('cobros as cb', 'cb.id', '=', 'cfi.cobro_id')
            ->join('factura_internas as fi', 'fi.id', '=', 'cfi.factura_interna_id')
            ->leftJoin('clientes as c', 'c.cliente_id', '=', 'cb.cliente_id')
            ->select([
                'cfi.cobro_id',
                'cb.numero_recibo',
                'cb.fecha_pago',
                'cfi.factura_interna_id',
                'fi.created_at as factura_creada',
                'cb.cliente_id',
                'cfi.monto',
                'cb.monto as monto_recibo',
                'cb.forma_pago',
            ])
            ->selectRaw($this->sqlCliente().' as cliente')
            ->selectRaw("DATE_FORMAT(DATE_ADD(fi.created_at, INTERVAL 1 MONTH), '%Y-%m') as mes_ciclo")
            ->orderBy('cfi.id');

        $this->filtrarFecha($query, 'cb.fecha_pago', $desde, $hasta);

        return $this->volcar($libro->hoja('Recibos y facturas', [
            'Cobro',
            'Recibo',
            'Fecha pago',
            'Factura',
            'Factura creada',
            'Mes ciclo',
            'Cliente ID',
            'Cliente',
            'Monto aplicado',
            'Monto del recibo',
            'Forma de pago',
        ], ['i', 's', 'd', 'i', 's', 's', 'i', 's', 'n', 'n', 's']), $query, function (object $row): array {
            return [
                $row->cobro_id,
                $row->numero_recibo,
                $this->fecha($row->fecha_pago),
                $row->factura_interna_id,
                $this->fechaHora($row->factura_creada),
                $row->mes_ciclo,
                $row->cliente_id,
                $row->cliente,
                $row->monto,
                $row->monto_recibo,
                Cobro::formasPago()[$row->forma_pago] ?? $row->forma_pago,
            ];
        });
    }

    private function volcar(XlsxHoja $hoja, Builder $query, callable $fila): int
    {
        foreach ($query->cursor() as $row) {
            $hoja->agregar($fila($row));
        }

        return $hoja->cerrar();
    }

    private function filtrarCreacion(Builder $query, string $columna, ?Carbon $desde, ?Carbon $hasta): void
    {
        if ($desde) {
            $query->where($columna, '>=', $desde->copy()->startOfDay());
        }
        if ($hasta) {
            $query->where($columna, '<=', $hasta->copy()->endOfDay());
        }
    }

    private function filtrarFecha(Builder $query, string $columna, ?Carbon $desde, ?Carbon $hasta): void
    {
        if ($desde) {
            $query->whereDate($columna, '>=', $desde->toDateString());
        }
        if ($hasta) {
            $query->whereDate($columna, '<=', $hasta->toDateString());
        }
    }

    private function sqlCliente(): string
    {
        return "TRIM(CONCAT(COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido, '')))";
    }

    private function fecha(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return Carbon::parse($valor)->format('Y-m-d');
    }

    private function fechaHora(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return Carbon::parse($valor)->format('Y-m-d H:i');
    }

    /**
     * @return Carbon|false|null
     */
    private function fechaOpcion(string $nombre): Carbon|false|null
    {
        $raw = $this->option($nombre);
        if ($raw === null || $raw === '') {
            return null;
        }

        $fecha = Carbon::createFromFormat('Y-m-d', (string) $raw);
        if ($fecha === false || $fecha->format('Y-m-d') !== (string) $raw) {
            $this->error("La opción --{$nombre} debe ser una fecha YYYY-MM-DD.");

            return false;
        }

        return $fecha->startOfDay();
    }

    private function archivoSalida(): string
    {
        $salida = $this->option('salida');
        if ($salida === null || $salida === '') {
            return storage_path('app/exportaciones/'.now()->format('Ymd-His').'/facturacion.xlsx');
        }

        $salida = (string) $salida;
        if (! str_starts_with($salida, '/') && ! str_starts_with($salida, '\\') && preg_match('/^[A-Za-z]:[\\\\\\/]/', $salida) !== 1) {
            $salida = base_path($salida);
        }
        if (str_ends_with(strtolower($salida), '.xlsx')) {
            return $salida;
        }

        return rtrim($salida, '/\\').DIRECTORY_SEPARATOR.'facturacion.xlsx';
    }
}
