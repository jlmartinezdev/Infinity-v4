<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\RouterCaidaAvisoConfig;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RouterCaidaAvisoController extends Controller
{
    public function index(): View
    {
        if (! auth()->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden configurar alertas de caída.');
        }

        $config = [
            'enabled' => RouterCaidaAvisoConfig::enabled(),
            'confirmaciones' => RouterCaidaAvisoConfig::confirmaciones(),
            'usuario_ids' => RouterCaidaAvisoConfig::usuarioIds(),
        ];

        $staff = User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name', 'telefono']);

        return view('sistema.router-caida-avisos.index', compact('config', 'staff'));
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

        RouterCaidaAvisoConfig::guardar(
            $request->boolean('enabled'),
            (int) $validated['confirmaciones'],
            $validated['usuario_ids'] ?? []
        );

        return redirect()
            ->route('sistema.router-caida-avisos.index')
            ->with('success', 'Configuración de alertas de caída guardada.');
    }

    public function probar(Request $request, WhatsAppOutboundNotifier $whatsapp)
    {
        if (! $request->user()?->esAdministrador()) {
            abort(403, 'Solo administradores pueden probar alertas de caída.');
        }

        $destinatarios = RouterCaidaAvisoConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            return redirect()
                ->route('sistema.router-caida-avisos.index')
                ->with('error', 'No hay destinatarios configurados (o sin teléfono). Guardá usuarios con WhatsApp primero.');
        }

        $router = Router::query()
            ->where('estado', Router::ESTADO_DESCONECTADO)
            ->orderBy('nombre')
            ->first()
            ?? Router::query()->orderBy('nombre')->first();

        if (! $router) {
            return redirect()
                ->route('sistema.router-caida-avisos.index')
                ->with('error', 'No hay routers en el sistema para armar la prueba.');
        }

        // Simular umbral en el texto de prueba
        if ((int) ($router->ping_fallos_seguidos ?? 0) < 1) {
            $router->ping_fallos_seguidos = RouterCaidaAvisoConfig::confirmaciones();
        }

        $ok = $whatsapp->routerCaido($router, $destinatarios, true);

        return redirect()
            ->route('sistema.router-caida-avisos.index')
            ->with(
                $ok ? 'success' : 'error',
                $ok
                    ? 'Aviso de prueba enviado a '.$destinatarios->count().' destinatario(s) (router: '.$router->nombre.').'
                    : 'No se pudo enviar la prueba. Revisá WhatsApp (token, plantilla y teléfonos).'
            );
    }
}
