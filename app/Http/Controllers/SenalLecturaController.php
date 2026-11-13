<?php

namespace App\Http\Controllers;

use App\Models\SenalLectura;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SenalLecturaController extends Controller
{
    public function index(): View
    {
        $lecturas = SenalLectura::query()->orderByDesc('id')->paginate(20);

        return view('sistema.senal-lecturas.index', compact('lecturas'));
    }

    public function show(Request $request, SenalLectura $lectura): View
    {
        $tipo = $request->query('tipo');
        $tipo = in_array($tipo, ['optica', 'antena'], true) ? $tipo : null;

        $fallos = $lectura->fallos()
            ->with(['servicio.cliente'])
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('sistema.senal-lecturas.show', compact('lectura', 'fallos', 'tipo'));
    }
}
