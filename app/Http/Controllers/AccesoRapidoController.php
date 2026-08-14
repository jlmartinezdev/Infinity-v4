<?php

namespace App\Http\Controllers;

use App\Support\MenuUsuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccesoRapidoController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        abort_unless($user, 403);

        $disponibles = MenuUsuario::catalogoAccesoRapidoVisible($user);
        $seleccionados = MenuUsuario::accesoRapidoSeleccionado($user);
        $usaDefault = $user->acceso_rapido === null;

        return view('acceso-rapido.edit', [
            'disponibles' => $disponibles,
            'seleccionados' => $seleccionados,
            'usaDefault' => $usaDefault,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['string', 'max:80'],
        ]);

        $names = MenuUsuario::sanitizarAccesoRapido($user, $validated['items'] ?? []);
        $user->acceso_rapido = $names;
        $user->save();

        return redirect()
            ->route('acceso-rapido.edit')
            ->with('success', 'Acceso rápido actualizado. Ya se refleja en el menú lateral.');
    }

    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $user->acceso_rapido = null;
        $user->save();

        return redirect()
            ->route('acceso-rapido.edit')
            ->with('success', 'Se restauró el acceso rápido por defecto.');
    }
}
