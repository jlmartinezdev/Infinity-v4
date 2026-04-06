<?php

namespace App\Http\Controllers;

use App\Models\Nodo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CorteServicioController extends Controller
{
    public function index()
    {
        $nodos = Nodo::query()->orderBy('descripcion')->get(['nodo_id', 'descripcion']);

        return view('admin.corte-servicio', compact('nodos'));
    }

    public function ejecutarTodos()
    {
        Artisan::call('servicios:corte-automatico', ['--force' => true]);
        $output = trim(Artisan::output());

        return redirect()
            ->route('admin.corte-servicio.index')
            ->with('success', 'Corte automático ejecutado para todos los nodos.')
            ->with('corte_output', $output !== '' ? $output : null);
    }

    public function ejecutarNodo(Request $request)
    {
        $validated = $request->validate([
            'nodo_id' => ['required', 'integer', 'exists:nodos,nodo_id'],
        ]);

        Artisan::call('servicios:corte-automatico', [
            '--force' => true,
            '--nodo' => (string) $validated['nodo_id'],
        ]);
        $output = trim(Artisan::output());

        return redirect()
            ->route('admin.corte-servicio.index')
            ->with('success', 'Corte automático ejecutado para el nodo seleccionado.')
            ->with('corte_output', $output !== '' ? $output : null);
    }
}
