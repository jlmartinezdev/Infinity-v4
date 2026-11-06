<?php

namespace App\Http\Controllers;

use App\Models\WaMemoria;
use App\Services\WhatsApp\Soporte\EstiloInterplus;
use App\Services\WhatsApp\Soporte\WaMemoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppMemoriaController extends Controller
{
    public function index(EstiloInterplus $estilo, WaMemoriaService $memoria): View
    {
        $canned = $memoria->cannedEfectivo();

        return view('whatsapp.memoria', [
            'estilo' => $estilo->base(),
            'canned' => $canned,
            'memorias' => WaMemoria::query()->orderByDesc('updated_at')->orderByDesc('id')->get(),
            'tipos' => WaMemoria::TIPOS,
        ]);
    }

    public function guardarEstilo(Request $request, EstiloInterplus $estilo): RedirectResponse
    {
        $data = $request->validate([
            'estilo' => ['required', 'string', 'max:8000'],
        ]);
        $estilo->guardar($data['estilo']);

        return back()->with('success', 'Estilo actualizado. El próximo turno del agente ya lo usa.');
    }

    public function guardarCanned(Request $request, WaMemoriaService $memoria): RedirectResponse
    {
        $data = $request->validate([
            'transferencia' => ['required', 'string', 'max:4000'],
            'beneficios' => ['required', 'string', 'max:4000'],
            'condiciones' => ['required', 'string', 'max:8000'],
        ]);
        $memoria->guardarCanned('transferencia', $data['transferencia']);
        $memoria->guardarCanned('beneficios', $data['beneficios']);
        $memoria->guardarCanned('condiciones', $data['condiciones']);

        return back()->with('success', 'Textos fijos (cuentas, beneficios, condiciones) guardados. Activos en el próximo mensaje.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        WaMemoria::query()->create($data + ['version' => 1]);

        return back()->with('success', 'Documento de memoria creado. La IA lo puede consumir en el próximo turno.');
    }

    public function update(Request $request, WaMemoria $memoria): RedirectResponse
    {
        $data = $this->validar($request);
        $memoria->fill($data);
        $memoria->version = (int) $memoria->version + 1;
        $memoria->save();

        return back()->with('success', 'Memoria actualizada (v'.$memoria->version.').');
    }

    public function destroy(WaMemoria $memoria): RedirectResponse
    {
        $memoria->delete();

        return back()->with('success', 'Memoria eliminada.');
    }

    public function importar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:512'],
            'titulo' => ['nullable', 'string', 'max:160'],
            'tipo' => ['nullable', 'string', 'max:40'],
            'palabras' => ['nullable', 'string', 'max:255'],
        ]);
        $file = $request->file('archivo');
        $cuerpo = trim((string) file_get_contents($file->getRealPath()));
        if ($cuerpo === '') {
            return back()->with('success', 'El archivo estaba vacío.');
        }
        if (mb_strlen($cuerpo) > 12000) {
            $cuerpo = mb_substr($cuerpo, 0, 12000);
        }
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Documento';
        }
        $tipo = (string) ($data['tipo'] ?? 'instruccion');
        if (! array_key_exists($tipo, WaMemoria::TIPOS)) {
            $tipo = 'instruccion';
        }

        WaMemoria::query()->create([
            'titulo' => mb_substr($titulo, 0, 160),
            'tipo' => $tipo,
            'palabras' => $data['palabras'] ?? null,
            'cuerpo' => $cuerpo,
            'activo' => true,
            'version' => 1,
        ]);

        return back()->with('success', 'Documento importado a memoria.');
    }

    public function mejorar(Request $request, WaMemoria $memoria, WaMemoriaService $svc): RedirectResponse
    {
        $aplicar = $request->boolean('aplicar');
        $nuevo = '';
        if ($aplicar && (int) session('borrador_ia_id') === (int) $memoria->id && filled(session('borrador_ia'))) {
            $nuevo = trim((string) session('borrador_ia'));
        } else {
            try {
                $nuevo = $svc->mejorarConIa($memoria);
            } catch (\Throwable $e) {
                return back()->with('success', 'No se pudo mejorar con IA: '.$e->getMessage());
            }
        }
        if ($aplicar) {
            $memoria->cuerpo = $nuevo;
            $memoria->version = (int) $memoria->version + 1;
            $memoria->save();
            session()->forget(['borrador_ia', 'borrador_ia_id']);

            return back()->with('success', 'Memoria mejorada con IA y guardada (v'.$memoria->version.').');
        }

        return back()
            ->with('success', 'Borrador IA listo. Revisá y pulsá «Aplicar este borrador».')
            ->with('borrador_ia_id', $memoria->id)
            ->with('borrador_ia', $nuevo);
    }

    /**
     * @return array{titulo: string, tipo: string, palabras: ?string, cuerpo: string, activo: bool}
     */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            'tipo' => ['required', 'string', 'max:40'],
            'palabras' => ['nullable', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:12000'],
            'activo' => ['nullable', 'boolean'],
        ]);
        $tipo = $data['tipo'];
        if (! array_key_exists($tipo, WaMemoria::TIPOS)) {
            $tipo = 'instruccion';
        }

        return [
            'titulo' => $data['titulo'],
            'tipo' => $tipo,
            'palabras' => $data['palabras'] ?? null,
            'cuerpo' => $data['cuerpo'],
            'activo' => $request->boolean('activo', true),
        ];
    }
}
