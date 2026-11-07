<?php

namespace App\Http\Controllers;

use App\Services\AnalisisCobranzasCicloService;
use App\Support\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AnalisisCobranzasController extends Controller
{
    public function index(Request $request, AnalisisCobranzasCicloService $analisis): View
    {
        $this->soloAdministrador();
        [$desde, $hasta] = $this->rango($request, $analisis);
        $informe = $analisis->analizar($desde, $hasta);

        return view('facturacion.analisis-cobranzas', [
            'informe' => $informe,
            'desde' => $desde->format('Y-m'),
            'hasta' => $hasta->format('Y-m'),
            'minimo' => Carbon::parse(AnalisisCobranzasCicloService::DESDE_MINIMO)->format('Y-m'),
            'maximo' => $analisis->ultimoCicloDisponible()->format('Y-m'),
        ]);
    }

    public function excel(Request $request, AnalisisCobranzasCicloService $analisis): BinaryFileResponse
    {
        $this->soloAdministrador();
        [$desde, $hasta] = $this->rango($request, $analisis);
        $informe = $analisis->analizar($desde, $hasta);

        $libro = new XlsxWriter();
        $this->hojaMontos($libro, 'Normales', $informe['normales'], false);
        $this->hojaMontos($libro, 'Especiales', $informe['especiales'], true);
        $this->hojaConsolidado($libro, $informe['consolidado'], $informe['totales']);
        $this->hojaConciliacion($libro, $informe['conciliacion']);

        $nombre = 'analisis-cobranzas-'.$desde->format('Y-m').'-'.$hasta->format('Y-m').'.xlsx';
        $ruta = storage_path('app/exportaciones/'.$nombre);
        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0755, true);
        }
        $libro->guardar($ruta);

        return response()->download($ruta, $nombre)->deleteFileAfterSend(true);
    }

    private function soloAdministrador(): void
    {
        $user = auth()->user();
        $esAdmin = $user && $user->rol && strtolower($user->rol->descripcion) === 'administrador';
        abort_unless($esAdmin, 403, 'Solo administradores pueden ver el análisis de cobranzas.');
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function rango(Request $request, AnalisisCobranzasCicloService $analisis): array
    {
        $hastaDefecto = now()->startOfMonth();
        $desde = $this->mes($request->query('desde'), Carbon::parse(AnalisisCobranzasCicloService::DESDE_MINIMO));
        $hasta = $this->mes($request->query('hasta'), $hastaDefecto);
        $desde = $analisis->acotar($desde);
        $hasta = $analisis->acotar($hasta);
        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        return [$desde, $hasta];
    }

    private function mes(mixed $valor, Carbon $defecto): Carbon
    {
        $texto = trim((string) $valor);
        if (preg_match('/^\d{4}-\d{2}$/', $texto) !== 1) {
            return $defecto->copy()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', $texto)->startOfMonth();
        } catch (\Throwable) {
            return $defecto->copy()->startOfMonth();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function hojaMontos(XlsxWriter $libro, string $nombre, array $filas, bool $especial): void
    {
        $encabezados = $especial
            ? ['Ciclo', 'Facturas ancladas', 'Cobradas en el mes', 'Sin cobro', 'Facturado', 'Notas de crédito', 'Cobrado aplicado', 'Pendiente', '% cobranza']
            : ['Ciclo', 'Facturas', 'Facturado', 'Notas de crédito', 'Cobrado aplicado', 'Pendiente', '% cobranza'];
        $tipos = $especial
            ? ['s', 'i', 'i', 'i', 'n', 'n', 'n', 'n', 'n']
            : ['s', 'i', 'n', 'n', 'n', 'n', 'n'];
        $hoja = $libro->hoja($nombre, $encabezados, $tipos);
        foreach ($filas as $fila) {
            if ($especial) {
                $hoja->agregar([
                    $fila['etiqueta'],
                    $fila['cantidad'],
                    $fila['cobradas'],
                    $fila['sin_cobro'],
                    $fila['facturado'],
                    $fila['notas'],
                    $fila['aplicado'],
                    $fila['pendiente'],
                    $fila['porcentaje'],
                ]);
            } else {
                $hoja->agregar([
                    $fila['etiqueta'],
                    $fila['cantidad'],
                    $fila['facturado'],
                    $fila['notas'],
                    $fila['aplicado'],
                    $fila['pendiente'],
                    $fila['porcentaje'],
                ]);
            }
        }
        $hoja->cerrar();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, float|int>  $totales
     */
    private function hojaConsolidado(XlsxWriter $libro, array $filas, array $totales): void
    {
        $hoja = $libro->hoja('Consolidado', [
            'Ciclo', 'Facturas', 'Facturado', 'Notas de crédito', 'Cobrado aplicado', 'Pendiente', 'Saldo a favor', '% cobranza',
        ], ['s', 'i', 'n', 'n', 'n', 'n', 'n', 'n']);
        foreach ($filas as $fila) {
            $hoja->agregar([
                $fila['etiqueta'],
                $fila['cantidad'],
                $fila['facturado'],
                $fila['notas'],
                $fila['aplicado'],
                $fila['pendiente'],
                $fila['saldo_favor'],
                $fila['porcentaje'],
            ]);
        }
        $hoja->agregar([
            'Total',
            $totales['cantidad'],
            $totales['facturado'],
            $totales['notas'],
            $totales['aplicado'],
            $totales['pendiente'],
            $totales['saldo_favor'],
            $totales['porcentaje'],
        ]);
        $hoja->cerrar();
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function hojaConciliacion(XlsxWriter $libro, array $filas): void
    {
        $hoja = $libro->hoja('Conciliacion', [
            'Ciclo',
            'Guardado facturado', 'Guardado cobrado', 'Guardado pendiente',
            'Vivo facturado', 'Vivo cobrado recibo', 'Vivo pendiente',
            'Análisis facturado neto', 'Análisis aplicado', 'Análisis pendiente',
            'Origen de la diferencia',
        ], ['s', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 'n', 's']);
        foreach ($filas as $fila) {
            $hoja->agregar([
                $fila['etiqueta'],
                $fila['guardado_facturado'],
                $fila['guardado_cobrado'],
                $fila['guardado_pendiente'],
                $fila['vivo_facturado'],
                $fila['vivo_cobrado'],
                $fila['vivo_pendiente'],
                $fila['analisis_facturado'],
                $fila['analisis_aplicado'],
                $fila['analisis_pendiente'],
                implode(' ', $fila['explicacion']),
            ]);
        }
        $hoja->cerrar();
    }
}
