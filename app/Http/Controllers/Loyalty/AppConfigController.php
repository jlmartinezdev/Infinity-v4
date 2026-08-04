<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Services\Portal\PortalAppConfigService;
use App\Services\Portal\PortalFeatureFlagsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppConfigController extends Controller
{
    public function __construct(
        private readonly PortalAppConfigService $config,
        private readonly PortalFeatureFlagsService $flags,
    ) {}

    public function edit(): View
    {
        $flagsRaw = $this->config->flagsRaw();
        $flagKeys = array_keys(config('portal_app.flags', []));
        $pago = $this->config->pagoOnline();
        $referidos = $this->config->referidos();
        $whatsapp = $this->config->whatsapp();
        $resumen = $this->config->resumen();
        $faqs = $this->config->faqsTopics();
        $previewFlags = $this->flags->flags();

        return view('loyalty.app-config.edit', compact(
            'flagsRaw',
            'flagKeys',
            'pago',
            'referidos',
            'whatsapp',
            'resumen',
            'faqs',
            'previewFlags'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $states = ['enabled', 'coming_soon', 'hidden', 'auto'];
        $flagKeys = array_keys(config('portal_app.flags', []));

        $flagsRules = [];
        foreach ($flagKeys as $key) {
            $flagsRules['flags.'.$key] = ['nullable', 'string', 'in:'.implode(',', $states)];
        }

        $validated = $request->validate(array_merge($flagsRules, [
            'pago_online.checkout_url' => ['nullable', 'string', 'max:1000'],
            'pago_online.provider' => ['nullable', 'string', 'max:60'],
            'referidos.puntos_por_alta' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'referidos.link_base' => ['nullable', 'string', 'max:500'],
            'whatsapp.pagos' => ['nullable', 'string', 'max:30'],
            'whatsapp.soporte' => ['nullable', 'string', 'max:30'],
            'resumen.disponibilidad_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'faqs_json' => ['nullable', 'string'],
        ]));

        $flags = [];
        foreach ($flagKeys as $key) {
            $val = $validated['flags'][$key] ?? null;
            if ($val !== null && $val !== '') {
                $flags[$key] = $val;
            }
        }

        $faqs = null;
        if (filled($validated['faqs_json'] ?? null)) {
            $decoded = json_decode($validated['faqs_json'], true);
            if (! is_array($decoded)) {
                return back()->withInput()->with('error', 'FAQs: JSON inválido.');
            }
            $faqs = ['topics' => $decoded['topics'] ?? $decoded];
        }

        $disp = $validated['resumen']['disponibilidad_pct'] ?? null;

        $this->config->guardar([
            'flags' => $flags,
            'pago_online' => [
                'checkout_url' => trim((string) ($validated['pago_online']['checkout_url'] ?? '')) ?: null,
                'provider' => trim((string) ($validated['pago_online']['provider'] ?? 'bancard')) ?: 'bancard',
            ],
            'referidos' => [
                'puntos_por_alta' => (int) ($validated['referidos']['puntos_por_alta'] ?? 50),
                'link_base' => trim((string) ($validated['referidos']['link_base'] ?? '')) ?: null,
            ],
            'whatsapp' => [
                'pagos' => trim((string) ($validated['whatsapp']['pagos'] ?? '')) ?: null,
                'soporte' => trim((string) ($validated['whatsapp']['soporte'] ?? '')) ?: null,
            ],
            'resumen' => [
                'disponibilidad_pct' => $disp === null || $disp === '' ? null : (float) $disp,
            ],
            'faqs' => $faqs,
        ]);

        return redirect()
            ->route('loyalty.app-config.edit')
            ->with('success', 'Configuración de la app clientes guardada. La API portal/v1 ya usa estos valores.');
    }
}
