<?php

namespace App\Http\Controllers;

use App\Models\WhatsappAsunto;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppAsuntoController extends Controller
{
    public function index(Request $request): View
    {
        $query = WhatsappAsunto::query()->orderBy('orden')->orderBy('nombre');

        if ($buscar = trim((string) $request->get('buscar', ''))) {
            $query->where('nombre', 'like', '%'.$buscar.'%');
        }

        return view('whatsapp.asuntos-index', [
            'asuntos' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('whatsapp.asuntos-form', ['asunto' => null]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:whatsapp_asuntos,nombre'],
            'color' => ['required', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        WhatsappAsunto::query()->create([
            'nombre' => $validated['nombre'],
            'color' => $validated['color'],
            'orden' => $validated['orden'] ?? 100,
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()->route('whatsapp.asuntos.index')->with('success', 'Asunto creado.');
    }

    public function edit(WhatsappAsunto $asunto): View
    {
        return view('whatsapp.asuntos-form', ['asunto' => $asunto]);
    }

    public function update(Request $request, WhatsappAsunto $asunto)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:whatsapp_asuntos,nombre,'.$asunto->id],
            'color' => ['required', 'string', 'max:20', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $asunto->update([
            'nombre' => $validated['nombre'],
            'color' => $validated['color'],
            'orden' => $validated['orden'] ?? $asunto->orden,
            'activo' => $request->boolean('activo'),
        ]);

        return redirect()->route('whatsapp.asuntos.index')->with('success', 'Asunto actualizado.');
    }

    public function destroy(WhatsappAsunto $asunto)
    {
        $asunto->delete();

        return redirect()->route('whatsapp.asuntos.index')->with('success', 'Asunto eliminado.');
    }
}
