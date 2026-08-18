<?php

namespace App\Http\Controllers;

use App\Models\NodoApWireless;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\ApWirelessCaidaAvisoConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApWirelessCaidaAvisoController extends Controller
{
    public function index(): View
    {
        if (! auth()->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden configurar alertas de caída.');
        }

        $config = [
            'enabled' => ApWirelessCaidaAvisoConfig::enabled(),
            'confirmaciones' => ApWirelessCaidaAvisoConfig::confirmaciones(),
            'usuario_ids' => ApWirelessCaidaAvisoConfig::usuarioIds(),
        ];

        $staff = User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name', 'telefono']);

        return view('sistema.aps-wireless-avisos.index', compact('config', 'staff'));
    }

    public function update(Request $request)
    {
        if (! $request->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden configurar alertas de caída.');
        }

        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'confirmaciones' => ['required', 'integer', 'min:1', 'max:20'],
            'usuario_ids' => ['nullable', 'array'],
            'usuario_ids.*' => ['integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
        ]);

        ApWirelessCaidaAvisoConfig::guardar(
            $request->boolean('enabled'),
            (int) $validated['confirmaciones'],
            $validated['usuario_ids'] ?? []
        );

        return redirect()
            ->route('sistema.aps-wireless-avisos.index')
            ->with('success', 'Configuración de alertas de APs wireless guardada.');
    }

    public function probar(Request $request, WhatsAppOutboundNotifier $whatsapp)
    {
        if (! $request->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden probar alertas de caída.');
        }

        $destinatarios = ApWirelessCaidaAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            return redirect()
                ->route('sistema.aps-wireless-avisos.index')
                ->with('error', 'No hay destinatarios configurados (o sin teléfono). Guardá usuarios con WhatsApp primero.');
        }

        $ap = NodoApWireless::query()
            ->with('nodo')
            ->where('ping_ok', false)
            ->orderBy('nombre')
            ->first()
            ?? NodoApWireless::query()->with('nodo')->orderBy('nombre')->first();

        if (! $ap) {
            return redirect()
                ->route('sistema.aps-wireless-avisos.index')
                ->with('error', 'No hay APs wireless registrados para armar la prueba.');
        }

        if ((int) ($ap->ping_fallos_seguidos ?? 0) < 1) {
            $ap->ping_fallos_seguidos = ApWirelessCaidaAvisoConfig::confirmaciones();
        }

        $ok = $whatsapp->apWirelessCaido($ap, $destinatarios, true);

        return redirect()
            ->route('sistema.aps-wireless-avisos.index')
            ->with(
                $ok ? 'success' : 'error',
                $ok
                    ? 'Aviso de prueba enviado a '.$destinatarios->count().' destinatario(s) (AP: '.$ap->nombre.').'
                    : 'No se pudo enviar la prueba. Revisá WhatsApp (token, plantilla y teléfonos).'
            );
    }
}
