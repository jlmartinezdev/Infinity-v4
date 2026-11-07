<?php

namespace App\Http\Controllers;

use App\Models\FinCategoria;
use App\Models\FinCuenta;
use App\Models\FinMovimiento;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinanzasLibroController extends Controller
{
    private const PER_PAGE_PERMITIDOS = [20, 40, 100, 200];

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $base = $this->consultaBase($filtros);

        $totales = (clone $base)
            ->selectRaw('COUNT(*) as cantidad')
            ->selectRaw('COALESCE(SUM(CASE WHEN signo > 0 THEN importe ELSE 0 END), 0) as entradas')
            ->selectRaw('COALESCE(SUM(CASE WHEN signo < 0 THEN importe ELSE 0 END), 0) as salidas')
            ->selectRaw('COALESCE(SUM(importe * signo), 0) as neto')
            ->first();

        $resumen = [
            'cantidad' => (int) ($totales->cantidad ?? 0),
            'entradas' => round((float) ($totales->entradas ?? 0), 2),
            'salidas' => round((float) ($totales->salidas ?? 0), 2),
            'neto' => round((float) ($totales->neto ?? 0), 2),
        ];

        $movimientos = (clone $base)
            ->with(['cuenta', 'categoria', 'usuario'])
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'])
            ->withQueryString();

        return view('finanzas.libro', [
            'movimientos' => $movimientos,
            'cuentas' => FinCuenta::query()->orderBy('orden')->get(),
            'categorias' => FinCategoria::query()->where('activa', true)->orderBy('clase')->orderBy('nombre')->get(),
            'filtros' => $filtros,
            'resumen' => $resumen,
        ]);
    }

    public function excel(Request $request): BinaryFileResponse
    {
        $filtros = $this->filtros($request);
        $libro = new XlsxWriter();
        $hoja = $libro->hoja('Libro', [
            'Fecha', 'Cuenta', 'Tipo', 'Concepto', 'Categoría', 'Importe', 'Signo', 'Referencia', 'Usuario', 'Observaciones',
        ], ['d', 's', 's', 's', 's', 'n', 'i', 's', 'i', 's']);
        foreach ($this->consulta($filtros)->limit(20000)->get() as $movimiento) {
            $hoja->agregar([
                $movimiento->fecha_efectiva?->toDateString(),
                $movimiento->cuenta?->nombre,
                $movimiento->tipo,
                $movimiento->concepto,
                $movimiento->categoria?->nombre,
                (float) $movimiento->importe,
                (int) $movimiento->signo,
                trim(($movimiento->referencia_tipo ?? '').' '.($movimiento->referencia_texto ?? '')),
                $movimiento->usuario_id,
                $movimiento->observaciones,
            ]);
        }
        $hoja->cerrar();
        $ruta = storage_path('app/exportaciones/libro-financiero.xlsx');
        $libro->guardar($ruta);

        return response()->download($ruta, 'libro-financiero.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * @return array{q: string, cuenta_id: int, tipo: string, categoria_id: int, desde: string, hasta: string, per_page: int}
     */
    private function filtros(Request $request): array
    {
        $perPage = (int) $request->query('per_page', 40);
        if (! in_array($perPage, self::PER_PAGE_PERMITIDOS, true)) {
            $perPage = 40;
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'cuenta_id' => (int) $request->query('cuenta_id', 0),
            'tipo' => trim((string) $request->query('tipo', '')),
            'categoria_id' => (int) $request->query('categoria_id', 0),
            'desde' => trim((string) $request->query('desde', '')),
            'hasta' => trim((string) $request->query('hasta', '')),
            'per_page' => $perPage,
        ];
    }

    private function consultaBase(array $filtros)
    {
        $q = $filtros['q'] ?? '';

        return FinMovimiento::query()
            ->when($filtros['cuenta_id'] > 0, fn ($query) => $query->where('cuenta_id', $filtros['cuenta_id']))
            ->when($filtros['tipo'] !== '', fn ($query) => $query->where('tipo', $filtros['tipo']))
            ->when(($filtros['categoria_id'] ?? 0) > 0, fn ($query) => $query->where('categoria_id', $filtros['categoria_id']))
            ->when($filtros['desde'] !== '', fn ($query) => $query->whereDate('fecha_efectiva', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($query) => $query->whereDate('fecha_efectiva', '<=', $filtros['hasta']))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $idNum = ctype_digit(ltrim($q, '#')) ? (int) ltrim($q, '#') : null;
                $query->where(function ($sub) use ($like, $idNum) {
                    $sub->where('concepto', 'like', $like)
                        ->orWhere('referencia_texto', 'like', $like)
                        ->orWhere('referencia_tipo', 'like', $like)
                        ->orWhere('observaciones', 'like', $like);
                    if ($idNum !== null && $idNum > 0) {
                        $sub->orWhere('id', $idNum)
                            ->orWhere('referencia_id', $idNum);
                    }
                });
            });
    }

    private function consulta(array $filtros)
    {
        return $this->consultaBase($filtros)
            ->with(['cuenta', 'categoria'])
            ->orderByDesc('fecha_efectiva')
            ->orderByDesc('id');
    }
}
