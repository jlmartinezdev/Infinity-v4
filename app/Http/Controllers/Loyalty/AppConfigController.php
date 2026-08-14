<?php

namespace App\Http\Controllers\Loyalty;

use App\Http\Controllers\Controller;
use App\Services\Portal\PortalAppConfigService;
use App\Services\Portal\PortalFeatureFlagsService;
use App\Support\LoyaltyImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppConfigController extends Controller
{
    /** @var list<string> */
    private const PAGO_KEYS = [
        'pago_online',
        'pago_tigo_money',
        'pago_transferencia',
        'pago_qr',
    ];

    public function __construct(
        private readonly PortalAppConfigService $config,
        private readonly PortalFeatureFlagsService $flags,
    ) {}

    public function edit(): View
    {
        $flagsRaw = $this->config->flagsRaw();
        $flagKeys = array_keys(config('portal_app.flags', []));
        $pago = $this->config->pagoOnline();
        $metodosPago = $this->config->metodosPago();
        $referidos = $this->config->referidos();
        $whatsapp = $this->config->whatsapp();
        $resumen = $this->config->resumen();
        $faqs = $this->config->faqsTopics();
        $previewFlags = $this->flags->flags();
        $pagoKeys = self::PAGO_KEYS;

        $iconUrls = [];
        foreach ($pagoKeys as $key) {
            $iconUrls[$key] = LoyaltyImageUploader::urlPublica($metodosPago[$key]['icon_path'] ?? null)
                ?? (filled($metodosPago[$key]['icon_url'] ?? null) ? (string) $metodosPago[$key]['icon_url'] : null);
        }

        return view('loyalty.app-config.edit', compact(
            'flagsRaw',
            'flagKeys',
            'pago',
            'metodosPago',
            'referidos',
            'whatsapp',
            'resumen',
            'faqs',
            'previewFlags',
            'pagoKeys',
            'iconUrls'
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

        $metodoRules = [];
        foreach (self::PAGO_KEYS as $key) {
            $prefix = 'metodos_pago.'.$key;
            $metodoRules[$prefix] = ['nullable', 'array'];
            $metodoRules[$prefix.'.title'] = ['nullable', 'string', 'max:120'];
            $metodoRules[$prefix.'.subtitle'] = ['nullable', 'string', 'max:200'];
            $metodoRules[$prefix.'.badge'] = ['nullable', 'string', 'max:40'];
            $metodoRules[$prefix.'.sort_order'] = ['nullable', 'integer', 'min:0', 'max:9999'];
            $metodoRules[$prefix.'.icon'] = ['nullable', 'string', 'max:40'];
            $metodoRules[$prefix.'.icon_url'] = ['nullable', 'string', 'max:1000'];
            $metodoRules[$prefix.'.icon_file'] = ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,gif,svg', 'max:2048'];
            $metodoRules[$prefix.'.eliminar_icono'] = ['nullable'];
            $metodoRules[$prefix.'.instructions'] = ['nullable', 'string', 'max:5000'];
            $metodoRules[$prefix.'.whatsapp'] = ['nullable', 'string', 'max:30'];
            $metodoRules[$prefix.'.whatsapp_template'] = ['nullable', 'string', 'max:1000'];
            $metodoRules[$prefix.'.whatsapp_cta_label'] = ['nullable', 'string', 'max:80'];
            $metodoRules[$prefix.'.show_whatsapp_cta'] = ['nullable'];
            $metodoRules[$prefix.'.fields_json'] = ['nullable', 'string'];
            $metodoRules[$prefix.'.provider'] = ['nullable', 'string', 'max:60'];
            $metodoRules[$prefix.'.note'] = ['nullable', 'string', 'max:2000'];
            $metodoRules[$prefix.'.tigo_phone'] = ['nullable', 'string', 'max:40'];
            $metodoRules[$prefix.'.tigo_alias'] = ['nullable', 'string', 'max:80'];
            $metodoRules[$prefix.'.tigo_ci'] = ['nullable', 'string', 'max:40'];
            $metodoRules[$prefix.'.bank'] = ['nullable', 'string', 'max:120'];
            $metodoRules[$prefix.'.account_type'] = ['nullable', 'string', 'max:80'];
            $metodoRules[$prefix.'.account_number'] = ['nullable', 'string', 'max:80'];
            $metodoRules[$prefix.'.account_holder'] = ['nullable', 'string', 'max:120'];
            $metodoRules[$prefix.'.account_ci_ruc'] = ['nullable', 'string', 'max:40'];
            $metodoRules[$prefix.'.bank_alias'] = ['nullable', 'string', 'max:80'];
            $metodoRules[$prefix.'.qr_alias'] = ['nullable', 'string', 'max:120'];
            $metodoRules[$prefix.'.qr_link'] = ['nullable', 'string', 'max:1000'];
            $metodoRules[$prefix.'.qr_id'] = ['nullable', 'string', 'max:120'];
        }

        $validated = $request->validate(array_merge($flagsRules, $metodoRules, [
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

        $actuales = $this->config->metodosPago();
        $metodosPago = [];
        foreach (self::PAGO_KEYS as $key) {
            $raw = $validated['metodos_pago'][$key] ?? [];
            if (! is_array($raw)) {
                continue;
            }

            $fieldsJson = trim((string) ($raw['fields_json'] ?? ''));
            unset($raw['fields_json'], $raw['icon_file'], $raw['eliminar_icono']);

            $fields = null;
            if ($fieldsJson !== '') {
                $decodedFields = json_decode($fieldsJson, true);
                if (! is_array($decodedFields)) {
                    return back()->withInput()->with('error', "Método {$key}: lista de datos (JSON) inválida.");
                }
                $fields = $decodedFields;
            }

            $meta = [];
            foreach ($raw as $field => $value) {
                if ($field === 'show_whatsapp_cta') {
                    $meta[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }
                if ($field === 'sort_order') {
                    if ($value !== null && $value !== '') {
                        $meta[$field] = (int) $value;
                    }
                    continue;
                }
                if ($field === 'icon_path') {
                    continue;
                }
                $trimmed = is_string($value) ? trim($value) : $value;
                if ($trimmed === null || $trimmed === '') {
                    continue;
                }
                $meta[$field] = $trimmed;
            }

            if ($fields !== null) {
                $meta['fields'] = $fields;
            }

            $pathActual = $actuales[$key]['icon_path'] ?? null;
            $nuevoPath = LoyaltyImageUploader::guardarArchivo(
                $request->file('metodos_pago.'.$key.'.icon_file'),
                'portal/metodos-pago',
                is_string($pathActual) ? $pathActual : null,
                $request->boolean('metodos_pago.'.$key.'.eliminar_icono')
            );
            if ($nuevoPath) {
                $meta['icon_path'] = $nuevoPath;
                // Si subió imagen, no hace falta icon_url manual.
                unset($meta['icon_url']);
            } elseif (! empty($meta['icon_url'])) {
                // URL externa / pegada: sin archivo local.
            }

            $metodosPago[$key] = $meta;
        }

        $provider = trim((string) ($validated['pago_online']['provider'] ?? 'bancard')) ?: 'bancard';
        if (empty($metodosPago['pago_online']['provider'])) {
            $metodosPago['pago_online']['provider'] = $provider;
        }

        $disp = $validated['resumen']['disponibilidad_pct'] ?? null;

        $this->config->guardar([
            'flags' => $flags,
            'pago_online' => [
                'checkout_url' => trim((string) ($validated['pago_online']['checkout_url'] ?? '')) ?: null,
                'provider' => $provider,
            ],
            'metodos_pago' => $metodosPago,
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
            ->with('success', 'Listo. La app clientes ya usa esta configuración.');
    }
}
