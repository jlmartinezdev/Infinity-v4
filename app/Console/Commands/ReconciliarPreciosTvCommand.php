<?php

namespace App\Console\Commands;

use App\Services\Tv\TvPrecioSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReconciliarPreciosTvCommand extends Command
{
    protected $signature = 'tv:reconciliar-precios
                            {--fix : Aplicar correcciones (sin esto solo audita)}
                            {--incluir-cero : También corregir asignaciones no promo con precio_aplicado = 0}
                            {--corregir-facturas : Corregir líneas TV en facturas internas pendientes desfasadas}
                            {--solo-facturadas-20 : En facturas, solo corregir líneas que estaban en Gs. 20}
                            {--agregar-faltantes : Agregar línea TV faltante en facturas del mes}
                            {--mes= : Mes del período (YYYY-MM). Por defecto: mes anterior}
                            {--min-antiguedad-meses=1 : Meses mínimos de fecha_instalacion antes del período}
                            {--solo-pendientes : En faltantes, no tocar facturas ya pagadas}
                            {--tv-cuenta= : Limitar reconciliación de asignaciones a una cuenta TV}';

    protected $description = 'Sincroniza precios TV (catálogo → precio_aplicado → precio_app) y opcionalmente corrige/agrega líneas en facturas.';

    public function handle(TvPrecioSyncService $sync): int
    {
        $aplicar = (bool) $this->option('fix');
        $incluirCero = (bool) $this->option('incluir-cero');
        $corregirFacturas = (bool) $this->option('corregir-facturas');
        $soloFacturadas20 = (bool) $this->option('solo-facturadas-20');
        $agregarFaltantes = (bool) $this->option('agregar-faltantes');
        $soloPendientes = (bool) $this->option('solo-pendientes');
        $minAntiguedad = max(0, (int) $this->option('min-antiguedad-meses'));
        $tvCuentaId = $this->option('tv-cuenta') !== null && $this->option('tv-cuenta') !== ''
            ? (int) $this->option('tv-cuenta')
            : null;

        $mesOpcion = trim((string) ($this->option('mes') ?? ''));
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

        $this->info('Reconciliación precios TV Streaming');
        $this->line('Modo: '.($aplicar ? 'APLICAR' : 'AUDITORÍA (use --fix para aplicar)'));
        $this->line('Incluir precio_aplicado = 0: '.($incluirCero ? 'sí' : 'no'));
        $this->line('Corregir facturas pendientes: '.($corregirFacturas ? 'sí' : 'no'));
        $this->line('Agregar líneas TV faltantes: '.($agregarFaltantes ? 'sí' : 'no'));
        if ($agregarFaltantes) {
            $this->line(sprintf(
                'Período faltantes: %s a %s (antigüedad instalación ≥ %d mes/es)',
                $periodoDesde->format('d/m/Y'),
                $periodoHasta->format('d/m/Y'),
                $minAntiguedad
            ));
        }
        if ($tvCuentaId) {
            $this->line('Solo cuenta TV #'.$tvCuentaId);
        }
        $this->newLine();

        $asig = $sync->reconciliarAsignaciones($tvCuentaId, $incluirCero, $aplicar);

        $this->info('1) Asignaciones vs catálogo (tv_cuentas)');
        $this->line('Revisadas: '.$asig['asignaciones_revisadas']);
        $this->line('A actualizar: '.$asig['asignaciones_actualizadas']);
        if ($aplicar) {
            $this->line('Servicios sincronizados tras asignación: '.$asig['servicios_sincronizados']);
        }

        if ($asig['cambios'] !== []) {
            $this->table(
                ['Asig.', 'Cuenta', 'Servicio', 'Perfil', 'Antes', 'Nuevo'],
                collect($asig['cambios'])->map(fn (array $c) => [
                    $c['asignacion_id'],
                    $c['tv_cuenta_id'],
                    $c['servicio_id'],
                    $c['perfil_numero'],
                    number_format((float) ($c['precio_anterior'] ?? 0), 0, ',', '.'),
                    number_format((float) $c['precio_nuevo'], 0, ',', '.'),
                ])->all()
            );
        } else {
            $this->line('Sin desfases de asignación'.($incluirCero ? '' : ' (con precio > 0)').'.');
        }

        $this->newLine();
        $this->info('2) servicios.precio_app vs suma de asignaciones');
        $serv = $sync->reconciliarPrecioAppServicios($aplicar);
        $this->line('Servicios con TV: '.$serv['revisados']);
        $this->line('A actualizar: '.$serv['actualizados']);

        if ($serv['cambios'] !== []) {
            $this->table(
                ['Servicio', 'precio_app antes', 'precio_app nuevo', 'cant. antes', 'cant. nueva'],
                collect($serv['cambios'])->map(fn (array $c) => [
                    $c['servicio_id'],
                    $c['precio_app_anterior'] !== null ? number_format((float) $c['precio_app_anterior'], 0, ',', '.') : '—',
                    $c['precio_app_nuevo'] !== null ? number_format((float) $c['precio_app_nuevo'], 0, ',', '.') : '—',
                    $c['cantidad_anterior'] ?? '—',
                    $c['cantidad_nueva'],
                ])->all()
            );
        } else {
            $this->line('precio_app ya coherente.');
        }

        if ($corregirFacturas) {
            $this->newLine();
            $this->info('3) Facturas internas pendientes con línea TV desfasada');
            $fact = $sync->corregirFacturasInternasTvPendientes(
                $aplicar,
                $soloFacturadas20 ? 20.0 : null
            );
            $this->line('Líneas TV revisadas: '.$fact['revisadas']);
            $this->line('A corregir: '.$fact['corregidas']);

            if ($fact['cambios'] !== []) {
                $this->table(
                    ['Detalle', 'Factura', 'Servicio', 'Antes', 'Nuevo', 'Total factura (antes)'],
                    collect($fact['cambios'])->map(fn (array $c) => [
                        $c['detalle_id'],
                        $c['factura_id'],
                        $c['servicio_id'],
                        number_format((float) $c['precio_anterior'], 0, ',', '.'),
                        number_format((float) $c['precio_nuevo'], 0, ',', '.'),
                        number_format((float) $c['total_factura_anterior'], 0, ',', '.'),
                    ])->all()
                );
            } else {
                $this->line('Sin líneas TV pendientes desfasadas'.($soloFacturadas20 ? ' (filtro Gs. 20)' : '').'.');
            }
        }

        if ($agregarFaltantes) {
            $this->newLine();
            $this->info('4) Agregar línea TV faltante en facturas del período');
            $falt = $sync->agregarLineasTvFaltantesEnPeriodo(
                $periodoDesde,
                $periodoHasta,
                $minAntiguedad,
                $aplicar,
                ! $soloPendientes,
            );
            $this->line('Servicios TV revisados: '.$falt['revisados']);
            $this->line('Facturas a completar: '.$falt['a_agregar']);
            if ($aplicar) {
                $this->line('Líneas agregadas: '.$falt['agregadas']);
            }

            if ($falt['cambios'] !== []) {
                $this->table(
                    ['Cliente', 'CI', 'Serv.', 'Instalación', 'Factura', 'Estado', 'TV a sumar', 'Total antes', 'Total nuevo'],
                    collect($falt['cambios'])->map(fn (array $c) => [
                        $c['nombre'],
                        $c['cedula'],
                        $c['servicio_id'],
                        $c['fecha_instalacion'] ?? '—',
                        $c['factura_id'],
                        $c['estado_factura'],
                        number_format((float) $c['precio_tv'], 0, ',', '.'),
                        number_format((float) $c['total_anterior'], 0, ',', '.'),
                        number_format((float) $c['total_nuevo'], 0, ',', '.'),
                    ])->all()
                );
            } else {
                $this->line('Ninguna factura del período quedó sin línea TV bajo estos criterios.');
            }
        }

        $this->newLine();
        if (! $aplicar) {
            $this->comment('Nada se modificó. Ejecute con --fix para aplicar.');
            if (! $incluirCero && $asig['asignaciones_actualizadas'] === 0) {
                $this->comment('Tip: use --incluir-cero si también quiere alinear asignaciones en 0 con el catálogo.');
            }
        } else {
            $this->info('Correcciones aplicadas.');
        }

        return self::SUCCESS;
    }
}
