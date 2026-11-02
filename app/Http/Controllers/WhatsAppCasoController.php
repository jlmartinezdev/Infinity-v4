<?php

namespace App\Http\Controllers;

use App\Models\WaCaso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppCasoController extends Controller
{
    public function index(): View
    {
        return view('whatsapp.casos', [
            'casos' => WaCaso::query()->orderByDesc('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validar($request);
        WaCaso::query()->create($data);

        return back()->with('success', 'Caso guardado. El próximo mensaje ya puede usarlo.');
    }

    public function update(Request $request, WaCaso $caso): RedirectResponse
    {
        $caso->update($this->validar($request));

        return back()->with('success', 'Caso actualizado.');
    }

    public function destroy(WaCaso $caso): RedirectResponse
    {
        $caso->delete();

        return back()->with('success', 'Caso eliminado.');
    }

    /**
     * @return array{titulo: string, etiqueta: ?string, palabras: ?string, texto: string, activo: bool}
     */
    private function validar(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:120'],
            'etiqueta' => ['nullable', 'string', 'max:40'],
            'palabras' => ['nullable', 'string', 'max:255'],
            'texto' => ['required', 'string', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ]);

        return [
            'titulo' => $data['titulo'],
            'etiqueta' => $data['etiqueta'] ?? null,
            'palabras' => $data['palabras'] ?? null,
            'texto' => $data['texto'],
            'activo' => $request->boolean('activo'),
        ];
    }
}
