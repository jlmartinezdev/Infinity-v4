<?php

namespace App\Http\Controllers\Loyalty;

use App\Helpers\TelefonoParaguayHelper;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\PromoAjuste;
use App\Models\SorteoCampana;
use App\Models\SorteoPremioCategoria;
use App\Services\Sorteo\SorteoService;
use App\Support\XlsxWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SorteoController extends Controller
{
    public function __construct(private readonly SorteoService $sorteo) {}

    public function index(Request $request)
    {
        $campanas = SorteoCampana::query()->orderByDesc('id')->get();
        $campana = $this->campana($request, $campanas);
        $filtro = (string) $request->input('filtro', '');
        $buscar = trim((string) $request->input('q', ''));
        $resultado = $campana
            ? $this->sorteo->listado($campana, $buscar, $filtro)
            : ['filas' => [], 'totales' => ['100' => 0, '50' => 0, 'aptos' => 0, 'no' => 0]];
        $page = max(1, (int) $request->input('page', 1));
        $filas = collect($resultado['filas']);
        $paginadas = new LengthAwarePaginator(
            $filas->forPage($page, 40)->values(),
            $filas->count(),
            40,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('loyalty.sorteo.index', [
            'campanas' => $campanas,
            'campana' => $campana?->load('categorias'),
            'filas' => $paginadas,
            'totales' => $resultado['totales'],
            'filtro' => $filtro,
            'buscar' => $buscar,
            'bienvenida' => PromoAjuste::bienvenida(),
        ]);
    }

    public function actualizar(Request $request, SorteoCampana $campana)
    {
        $data = $this->validarCampana($request);
        $campana->update($data);
        $this->guardarCategorias($request, $campana);

        return back()->with('success', 'Campaña actualizada.');
    }

    public function crear(Request $request)
    {
        $data = $this->validarCampana($request);
        $data['activa'] = false;
        $campana = SorteoCampana::create($data);
        $this->guardarCategorias($request, $campana);

        return redirect()->route('loyalty.sorteo.index', ['campana' => $campana->id])
            ->with('success', 'Campaña creada. Activala cuando corresponda.');
    }

    public function activar(SorteoCampana $campana)
    {
        $campana->activar();

        return back()->with('success', 'Campaña activa. El recibo usa esta promo.');
    }

    public function desactivar(SorteoCampana $campana)
    {
        $campana->update(['activa' => false]);

        return back()->with('success', 'Campaña desactivada. No se generan tickets nuevos.');
    }

    public function imprimir(Request $request, SorteoCampana $campana)
    {
        $campana->update(['imprimir_en_recibo' => $request->boolean('imprimir_en_recibo')]);

        return back()->with('success', $campana->imprimir_en_recibo
            ? 'El cupón vuelve a salir en el recibo.'
            : 'El recibo queda como siempre, sin cupón.');
    }

    public function bienvenida(Request $request)
    {
        $data = $request->validate([
            'activo' => ['nullable', 'boolean'],
            'porcentaje' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
        $ajuste = PromoAjuste::bienvenida();
        if (! $ajuste) {
            $ajuste = new PromoAjuste(['clave' => PromoAjuste::BIENVENIDA]);
        }
        $ajuste->activo = $request->boolean('activo');
        $ajuste->porcentaje = $data['porcentaje'];
        $ajuste->save();

        return back()->with('success', 'Cupón de bienvenida actualizado.');
    }

    public function resena(Request $request, SorteoCampana $campana)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'telefono' => ['required', 'string', 'max:40'],
        ]);
        $tel = TelefonoParaguayHelper::paraGuardar($data['telefono']);
        if ($tel === '') {
            return back()->with('error', 'Anotá el celular.');
        }
        $cliente = Cliente::findOrFail($data['cliente_id']);
        $cliente->telefono = $tel;
        $cliente->save();
        $this->sorteo->guardarResena($campana, $cliente, $tel, $request->user()?->usuario_id);
        $clase = $this->sorteo->clasificar($campana, $cliente->fresh());
        $nombre = trim($cliente->nombre.' '.$cliente->apellido);
        if ($clase['nivel'] === 'no') {
            return back()->with('success', 'Reseña guardada para '.$nombre.'. El ticket extra se crea cuando quede apto.');
        }

        return back()->with('success', 'Reseña marcada para '.$nombre.'. Quedó el ticket extra.');
    }

    public function ganadores(Request $request, SorteoCampana $campana)
    {
        $data = $request->validate([
            'categoria_id' => ['required', 'integer', 'exists:sorteo_premio_categorias,id'],
            'clientes' => ['required', 'string'],
        ]);
        $categoria = SorteoPremioCategoria::query()
            ->where('campana_id', $campana->id)
            ->findOrFail($data['categoria_id']);
        $ids = $this->resolverClientes($data['clientes']);
        if ($ids === []) {
            return back()->with('error', 'No encontré clientes en la lista.');
        }
        try {
            $resultado = $this->sorteo->cargarGanadores($campana, $categoria, $ids, $request->user()?->usuario_id);
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        $mensaje = 'Ganadores cargados: '.$resultado['cargados'].'.';
        if ($resultado['avisos'] !== []) {
            $mensaje .= ' '.implode(' ', $resultado['avisos']);
        }

        return back()->with('success', $mensaje);
    }

    public function csv(Request $request, SorteoCampana $campana): StreamedResponse
    {
        $filas = $this->sorteo->listado($campana, (string) $request->input('q', ''), (string) $request->input('filtro', ''))['filas'];

        return response()->streamDownload(function () use ($filas) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Nombre', 'CI', 'Celular', 'Nivel', 'Motivo', 'Ticket', 'Extra']);
            foreach ($filas as $fila) {
                fputcsv($out, [
                    $fila['cliente_id'],
                    $fila['nombre'],
                    $fila['cedula'],
                    $fila['telefono'],
                    $fila['etiqueta'],
                    $this->motivo($fila['motivo']),
                    $fila['ticket'],
                    $fila['extra'],
                ]);
            }
            fclose($out);
        }, 'sorteo-'.$campana->id.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function excel(Request $request, SorteoCampana $campana)
    {
        $filas = $this->sorteo->listado($campana, (string) $request->input('q', ''), (string) $request->input('filtro', ''))['filas'];
        $libro = new XlsxWriter;
        $hoja = $libro->hoja('Sorteo', ['ID', 'Nombre', 'CI', 'Celular', 'Nivel', 'Motivo', 'Ticket', 'Extra'], ['i', 's', 's', 's', 's', 's', 's', 's']);
        foreach ($filas as $fila) {
            $hoja->agregar([
                $fila['cliente_id'],
                $fila['nombre'],
                (string) $fila['cedula'],
                (string) $fila['telefono'],
                $fila['etiqueta'],
                $this->motivo($fila['motivo']),
                (string) $fila['ticket'],
                (string) $fila['extra'],
            ]);
        }
        $hoja->cerrar();
        $ruta = storage_path('app/sorteo-'.$campana->id.'.xlsx');
        $libro->guardar($ruta);

        return response()->download($ruta, 'sorteo-'.$campana->id.'.xlsx')->deleteFileAfterSend(true);
    }

    public function urna(Request $request, SorteoCampana $campana)
    {
        $filas = $this->sorteo->listado($campana, (string) $request->input('q', ''), (string) $request->input('filtro', ''))['filas'];
        $tickets = [];
        foreach ($filas as $fila) {
            if ($fila['ticket']) {
                $tickets[] = $fila + ['numero' => $fila['ticket'], 'extra_nro' => null];
            }
            if ($fila['extra']) {
                $tickets[] = $fila + ['numero' => $fila['extra'], 'extra_nro' => $fila['extra']];
            }
        }
        $pdf = Pdf::loadView('loyalty.sorteo.urna', [
            'campana' => $campana,
            'tickets' => $tickets,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('urna-'.$campana->id.'.pdf');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SorteoCampana>  $campanas
     */
    private function campana(Request $request, $campanas): ?SorteoCampana
    {
        if ($request->filled('campana')) {
            return $campanas->firstWhere('id', (int) $request->input('campana'));
        }

        return $campanas->firstWhere('activa', true) ?? $campanas->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validarCampana(Request $request): array
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:200'],
            'texto_ticket' => ['required', 'string', 'max:200'],
            'texto_invitacion' => ['nullable', 'string'],
            'fecha_sorteo' => ['required', 'date'],
            'timezone' => ['required', 'string', 'max:64'],
            'periodo_uso_desde' => ['required', 'date'],
            'periodo_uso_hasta' => ['required', 'date'],
            'pago_limite' => ['required', 'date'],
            'criterio_puntualidad' => ['required', 'in:vencimiento,dia_fijo'],
            'dia_puntualidad' => ['required', 'integer', 'min:1', 'max:28'],
            'meses_puntualidad_100' => ['nullable', 'string'],
            'exige_app' => ['nullable', 'boolean'],
            'permite_multiples_premios' => ['nullable', 'boolean'],
        ]);
        $meses = array_values(array_filter(array_map('trim', explode(',', (string) ($data['meses_puntualidad_100'] ?? '')))));
        $data['meses_puntualidad_100'] = $meses;
        $data['exige_app'] = $request->boolean('exige_app');
        $data['permite_multiples_premios'] = $request->boolean('permite_multiples_premios');
        $crudo = (string) $request->input('pago_limite');
        $data['pago_limite'] = strlen($crudo) <= 10
            ? \Carbon\Carbon::parse($crudo)->endOfDay()->format('Y-m-d H:i:s')
            : \Carbon\Carbon::parse($crudo)->format('Y-m-d H:i:s');

        return $data;
    }

    private function guardarCategorias(Request $request, SorteoCampana $campana): void
    {
        $filas = $request->input('categorias', []);
        if (! is_array($filas)) {
            return;
        }
        foreach ($filas as $fila) {
            if (! is_array($fila) || empty($fila['codigo'])) {
                continue;
            }
            $ventanas = array_values(array_filter(array_map('trim', explode(',', (string) ($fila['ventanas'] ?? '')))));
            SorteoPremioCategoria::query()->updateOrCreate(
                ['campana_id' => $campana->id, 'codigo' => $fila['codigo']],
                [
                    'nombre' => $fila['nombre'] ?? $fila['codigo'],
                    'cupo' => (int) ($fila['cupo'] ?? 5),
                    'porcentaje' => ($fila['porcentaje'] ?? '') === '' ? null : $fila['porcentaje'],
                    'ventanas_emision' => $ventanas,
                    'orden' => (int) ($fila['orden'] ?? 0),
                ]
            );
        }
    }

    /**
     * @return list<int>
     */
    private function resolverClientes(string $texto): array
    {
        $partes = preg_split('/[\s,;]+/', trim($texto)) ?: [];
        $ids = [];
        foreach ($partes as $parte) {
            if ($parte === '') {
                continue;
            }
            $cliente = ctype_digit($parte)
                ? Cliente::query()->where('cliente_id', (int) $parte)->orWhere('cedula', $parte)->first()
                : Cliente::query()->where('cedula', $parte)->first();
            if ($cliente) {
                $ids[] = (int) $cliente->cliente_id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function motivo(?string $motivo): string
    {
        return match ($motivo) {
            'sin_app' => 'Sin app',
            'sin_pago' => 'Sin pago',
            'pago_tarde' => 'Pagó tarde',
            default => '',
        };
    }
}
