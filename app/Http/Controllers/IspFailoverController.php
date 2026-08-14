<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\User;
use App\Services\Monitoreo\IspFailoverService;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use App\Support\IspFailoverConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class IspFailoverController extends Controller
{
    public function index(): View
    {
        $config = IspFailoverConfig::get();
        $estado = IspFailoverConfig::estado();
        $routers = Router::query()->orderBy('nombre')->get(['router_id', 'nombre', 'ip', 'webhook_token']);
        $staff = User::staff()->activos()->orderBy('name')->get(['usuario_id', 'name', 'telefono']);
        $script = $this->scriptNetwatch($config, $routers->firstWhere('router_id', $config['router_id']));

        return view('sistema.isp-failover.index', compact('config', 'estado', 'routers', 'staff', 'script'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'auto_failover' => ['nullable', 'boolean'],
            'router_id' => ['nullable', 'integer', 'exists:routers,router_id'],
            'ping_host' => ['required', 'ip'],
            'ping_count' => ['required', 'integer', 'min:1', 'max:10'],
            'isp1_nombre' => ['required', 'string', 'max:64'],
            'isp1_interface' => ['nullable', 'string', 'max:64'],
            'isp1_src_address' => ['required', 'ip'],
            'isp1_ruta_comentario' => ['nullable', 'string', 'max:64'],
            'isp1_gateway' => ['nullable', 'string', 'max:64'],
            'isp2_nombre' => ['required', 'string', 'max:64'],
            'isp2_ruta_comentario' => ['nullable', 'string', 'max:64'],
            'isp2_gateway' => ['nullable', 'string', 'max:64'],
            'confirmaciones' => ['required', 'integer', 'min:1', 'max:20'],
            'confirmaciones_ok' => ['required', 'integer', 'min:1', 'max:20'],
            'webhook_base_url' => ['nullable', 'string', 'max:255'],
            'usuario_ids' => ['nullable', 'array'],
            'usuario_ids.*' => ['integer', Rule::exists('users', 'usuario_id')->whereNull('cliente_id')],
        ]);

        $iface = trim((string) ($validated['isp1_interface'] ?? ''));
        $src = trim((string) ($validated['isp1_src_address'] ?? ''));
        if ($src === '') {
            return redirect()
                ->route('sistema.isp-failover.index')
                ->withInput()
                ->with('error', 'En RouterOS 7 el ping solo admite src-address. Cargá la IP WAN de ISP 1 (no la interfaz).');
        }

        $comment1 = trim((string) ($validated['isp1_ruta_comentario'] ?? ''));
        $gw1 = trim((string) ($validated['isp1_gateway'] ?? ''));
        $comment2 = trim((string) ($validated['isp2_ruta_comentario'] ?? ''));
        $gw2 = trim((string) ($validated['isp2_gateway'] ?? ''));
        if ($request->boolean('auto_failover') && ($comment1 === '' && $gw1 === '' || $comment2 === '' && $gw2 === '')) {
            return redirect()
                ->route('sistema.isp-failover.index')
                ->withInput()
                ->with('error', 'Para cambiar rutas automáticamente, cargá comentario o gateway de ISP 1 y de ISP 2.');
        }

        IspFailoverConfig::guardar([
            'enabled' => $request->boolean('enabled'),
            'auto_failover' => $request->boolean('auto_failover'),
            'router_id' => $validated['router_id'] ?? null,
            'ping_host' => $validated['ping_host'],
            'ping_count' => (int) $validated['ping_count'],
            'isp1_nombre' => $validated['isp1_nombre'],
            'isp1_interface' => $iface,
            'isp1_src_address' => $src,
            'isp1_ruta_comentario' => $comment1,
            'isp1_gateway' => $gw1,
            'isp2_nombre' => $validated['isp2_nombre'],
            'isp2_ruta_comentario' => $comment2,
            'isp2_gateway' => $gw2,
            'confirmaciones' => (int) $validated['confirmaciones'],
            'confirmaciones_ok' => (int) $validated['confirmaciones_ok'],
            'webhook_base_url' => trim((string) ($validated['webhook_base_url'] ?? '')),
            'usuario_ids' => $validated['usuario_ids'] ?? [],
        ]);

        return redirect()
            ->route('sistema.isp-failover.index')
            ->with('success', 'Configuración de failover ISP guardada.');
    }

    public function pingAhora(Request $request, IspFailoverService $failover): RedirectResponse
    {
        $r = $failover->verificar();

        return redirect()
            ->route('sistema.isp-failover.index')
            ->with(
                ($r['ok'] ?? false) ? 'success' : 'error',
                $r['message'] ?? 'Sin respuesta.'
            );
    }

    public function forzarFailover(Request $request, IspFailoverService $failover): RedirectResponse
    {
        $r = $failover->forzarFailover();

        return redirect()
            ->route('sistema.isp-failover.index')
            ->with(($r['ok'] ?? false) ? 'success' : 'error', $r['message']);
    }

    public function restaurarPrimario(Request $request, IspFailoverService $failover): RedirectResponse
    {
        $r = $failover->restaurarPrimario();

        return redirect()
            ->route('sistema.isp-failover.index')
            ->with(($r['ok'] ?? false) ? 'success' : 'error', $r['message']);
    }

    public function probar(Request $request, WhatsAppOutboundNotifier $whatsapp): RedirectResponse
    {
        $destinatarios = IspFailoverConfig::destinatarios();
        if ($destinatarios->isEmpty()) {
            return redirect()
                ->route('sistema.isp-failover.index')
                ->with('error', 'No hay destinatarios con teléfono. Guardá usuarios primero.');
        }

        $cfg = IspFailoverConfig::get();
        $router = IspFailoverConfig::router();
        $ok = $whatsapp->ispFailover(
            $cfg['isp1_nombre'].' sin internet (ping '.$cfg['ping_host'].'). Failover activo hacia '.$cfg['isp2_nombre'].'.',
            $cfg['ping_host'],
            $router?->nombre ?? 'router borde',
            $destinatarios,
            true
        );

        return redirect()
            ->route('sistema.isp-failover.index')
            ->with(
                $ok ? 'success' : 'error',
                $ok
                    ? 'Aviso de prueba enviado a '.$destinatarios->count().' destinatario(s).'
                    : 'No se pudo enviar la prueba. Revisá WhatsApp (token, plantilla y teléfonos).'
            );
    }

    public function rutas(Request $request, IspFailoverService $failover): JsonResponse
    {
        $validated = $request->validate([
            'router_id' => ['required', 'integer', 'exists:routers,router_id'],
        ]);

        $router = Router::query()->findOrFail((int) $validated['router_id']);

        try {
            $rutas = $failover->rutasDefault($router);
            $direcciones = app(\App\Services\MikroTikService::class)->listIpv4Addresses($router);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el router: '.$e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'rutas' => $rutas,
            'direcciones' => $direcciones,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function scriptNetwatch(array $config, ?Router $router): string
    {
        $token = $router?->webhook_token ?: 'PEGAR_TOKEN_WEBHOOK';
        $base = trim((string) ($config['webhook_base_url'] ?? ''));
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }
        $base = rtrim($base, '/');
        $url = $base.'/api/v1/webhooks/mikrotik/isp-failover';
        $host = $config['ping_host'] ?: '1.1.1.1';
        $comment1 = $config['isp1_ruta_comentario'] ?: 'ISP1';
        $src = $config['isp1_src_address'] !== '' ? $config['isp1_src_address'] : 'IP_WAN_ISP1';

        return <<<RSC
# Pegar en Terminal del router de borde (RouterOS 7.23+).
# El ping/netwatch NO admite interface: usá src-address = IP WAN de ISP 1.
# Comentá las rutas default: {$comment1} e ISP2.
# El MikroTik debe alcanzar {$url} (IP LAN del servidor, no localhost).

/system script add name=infinity-isp1-down dont-require-permissions=yes source={
  :log warning "ISP1 DOWN — failover"
  /ip route disable [find comment="{$comment1}"]
  /tool fetch url="{$url}" http-method=post http-header-field="Authorization: Bearer {$token},Content-Type: application/x-www-form-urlencoded" http-data="evento=down" keep-result=no
}

/system script add name=infinity-isp1-up dont-require-permissions=yes source={
  :log info "ISP1 UP — failback"
  /ip route enable [find comment="{$comment1}"]
  /tool fetch url="{$url}" http-method=post http-header-field="Authorization: Bearer {$token},Content-Type: application/x-www-form-urlencoded" http-data="evento=up" keep-result=no
}

/tool netwatch add name=infinity-isp1-salida type=icmp host={$host} interval=10s timeout=3s src-address={$src} down-script="/system script run infinity-isp1-down" up-script="/system script run infinity-isp1-up"
RSC;
    }
}
