<?php

namespace App\Http\Controllers;

use App\Models\TicketAsunto;
use App\Support\TicketAsuntoIconos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketAsuntoController extends Controller
{
    public function index(Request $request)
    {
        $query = TicketAsunto::query()->withCount('tickets')->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = (string) $request->buscar;
            $query->where('nombre', 'like', "%{$q}%");
        }

        $asuntos = $query->paginate(20)->withQueryString();

        return view('ticket-asuntos.index', compact('asuntos'));
    }

    public function create()
    {
        return view('ticket-asuntos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->reglas());

        TicketAsunto::create($validated);

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket creado correctamente.');
    }

    public function edit(TicketAsunto $ticket_asunto)
    {
        return view('ticket-asuntos.edit', ['ticketAsunto' => $ticket_asunto]);
    }

    public function update(Request $request, TicketAsunto $ticket_asunto)
    {
        $validated = $request->validate($this->reglas($ticket_asunto));

        $ticket_asunto->update($validated);

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket actualizado correctamente.');
    }

    private function reglas(?TicketAsunto $asunto = null): array
    {
        return [
            'nombre'    => ['required', 'string', 'max:120', 'unique:ticket_asuntos,nombre'.($asunto ? ','.$asunto->id : '')],
            'icono'     => ['nullable', 'string', 'max:50'],
            'icono_svg' => ['nullable', 'string', Rule::in(TicketAsuntoIconos::claves())],
            'color'     => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function destroy(TicketAsunto $ticket_asunto)
    {
        $ticket_asunto->delete();

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket eliminado correctamente.');
    }
}
