<?php

namespace App\Http\Controllers;

use App\Models\TicketAsunto;
use Illuminate\Http\Request;

class TicketAsuntoController extends Controller
{
    public function index(Request $request)
    {
        $query = TicketAsunto::query()->orderBy('nombre');

        if ($request->filled('buscar')) {
            $q = (string) $request->buscar;
            $query->where('nombre', 'like', "%{$q}%");
        }

        $asuntos = $query->paginate(15)->withQueryString();

        return view('ticket-asuntos.index', compact('asuntos'));
    }

    public function create()
    {
        return view('ticket-asuntos.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:ticket_asuntos,nombre'],
        ]);

        TicketAsunto::create($validated);

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket creado correctamente.');
    }

    public function edit(TicketAsunto $ticket_asunto)
    {
        return view('ticket-asuntos.edit', ['ticketAsunto' => $ticket_asunto]);
    }

    public function update(Request $request, TicketAsunto $ticket_asunto)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:120', 'unique:ticket_asuntos,nombre,' . $ticket_asunto->id],
        ]);

        $ticket_asunto->update($validated);

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket actualizado correctamente.');
    }

    public function destroy(TicketAsunto $ticket_asunto)
    {
        $ticket_asunto->delete();

        return redirect()->route('ticket-asuntos.index')->with('success', 'Asunto de ticket eliminado correctamente.');
    }
}
