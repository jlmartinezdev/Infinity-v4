<?php

namespace App\Services\Portal;

use App\Services\Tpago\TpagoClient;
use App\Support\LoyaltyImageUploader;

class PortalFeatureFlagsService
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
        private readonly TpagoClient $tpago,
    ) {}

    /**
     * @return list<array{key: string, state: string, label: string|null, metadata: mixed}>
     */
    public function flags(): array
    {
        $cfg = $this->config->flagsRaw();
        $labels = config('portal_app.flag_labels', []);
        $pago = $this->config->pagoOnline();
        $metodos = $this->config->metodosPago();
        $waPagos = trim((string) ($this->config->whatsapp()['pagos'] ?? ''));
        $out = [];

        foreach ($cfg as $key => $state) {
            $metaRaw = in_array($key, self::PAGO_KEYS, true)
                ? ($metodos[$key] ?? [])
                : [];
            if ($key === 'pago_online') {
                $metaRaw = $this->enrichPagoOnlineMeta($metaRaw, $pago);
            }

            $normalized = $this->normalizeState((string) $state, (string) $key, $pago, $metaRaw);
            $isPago = in_array($key, self::PAGO_KEYS, true);
            $item = [
                'key' => (string) $key,
                'state' => $normalized,
                'label' => $isPago ? null : ($labels[$key] ?? null),
                'metadata' => null,
            ];

            if ($key === 'speed_test_screen' && $normalized === 'enabled') {
                $item['metadata'] = ['engine' => 'smart_check'];
            }

            if ($isPago) {
                $item['metadata'] = $this->buildPagoMetadata((string) $key, $metaRaw, $waPagos);
                if ($normalized === 'coming_soon') {
                    $item['label'] = $labels[$key] ?? 'Pronto';
                }
            }

            $out[] = $item;
        }

        $wa = $this->config->whatsapp();
        if ($wa['pagos'] || $wa['soporte']) {
            $out[] = [
                'key' => 'whatsapp_contactos',
                'state' => 'enabled',
                'label' => null,
                'metadata' => [
                    'whatsapp_pagos' => $wa['pagos'],
                    'whatsapp_soporte' => $wa['soporte'],
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  array{checkout_url: ?string, provider: string}  $pago
     * @param  array<string, mixed>  $meta
     */
    private function normalizeState(string $state, string $key, array $pago, array $meta = []): string
    {
        $state = strtolower(trim($state));

        if ($key === 'pago_online' && ($state === 'auto' || $state === '')) {
            if ($this->tpago->enabled()) {
                return 'enabled';
            }
            $url = trim((string) ($pago['checkout_url'] ?? ''));

            return $url !== '' && str_starts_with($url, 'http') ? 'enabled' : 'coming_soon';
        }

        if (in_array($key, ['pago_tigo_money', 'pago_transferencia', 'pago_qr'], true)
            && ($state === 'auto' || $state === '')) {
            return $this->metodoTieneDatos($key, $meta) ? 'enabled' : 'coming_soon';
        }

        if (in_array($state, ['enabled', 'enable', 'on', 'true', '1'], true)) {
            return 'enabled';
        }
        if (in_array($state, ['coming_soon', 'coming-soon', 'soon'], true)) {
            return 'coming_soon';
        }
        if ($state === 'auto') {
            return 'enabled';
        }

        return 'hidden';
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array{checkout_url: ?string, provider: string}  $pago
     * @return array<string, mixed>
     */
    private function enrichPagoOnlineMeta(array $meta, array $pago): array
    {
        if (empty($meta['provider']) && ! empty($pago['provider'])) {
            $meta['provider'] = $pago['provider'];
        }
        if (empty($meta['note']) && ! empty($meta['instructions'])) {
            // note es alias informativo; la app puede leer instructions
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function buildPagoMetadata(string $key, array $raw, string $waPagos): array
    {
        $meta = [];
        foreach ($raw as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $meta[$k] = $v;
        }

        if (empty($meta['whatsapp']) && $waPagos !== '') {
            $meta['whatsapp'] = $waPagos;
        }

        if (! array_key_exists('show_whatsapp_cta', $meta)) {
            $meta['show_whatsapp_cta'] = ! empty($meta['whatsapp']) && $key !== 'pago_online';
        } else {
            $meta['show_whatsapp_cta'] = filter_var($meta['show_whatsapp_cta'], FILTER_VALIDATE_BOOLEAN);
        }

        if (isset($meta['sort_order'])) {
            $meta['sort_order'] = (int) $meta['sort_order'];
        }

        $iconPath = trim((string) ($meta['icon_path'] ?? ''));
        unset($meta['icon_path']);
        if ($iconPath !== '') {
            $iconUrl = LoyaltyImageUploader::urlPublica($iconPath);
            if ($iconUrl) {
                $meta['icon_url'] = $iconUrl;
            }
        } elseif (! empty($meta['icon_url']) && is_string($meta['icon_url'])) {
            // Permitir URL absoluta pegada a mano en panel/JSON.
            $meta['icon_url'] = trim($meta['icon_url']);
        }

        $fields = $this->resolveFields($key, $meta);
        if ($fields !== []) {
            $meta['fields'] = $fields;
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array{label: string, value: string, copyable: bool}>
     */
    private function resolveFields(string $key, array $meta): array
    {
        if (! empty($meta['fields']) && is_array($meta['fields'])) {
            $out = [];
            foreach ($meta['fields'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['label'] ?? ''));
                $value = trim((string) ($row['value'] ?? ''));
                if ($label === '' || $value === '') {
                    continue;
                }
                $out[] = [
                    'label' => $label,
                    'value' => $value,
                    'copyable' => array_key_exists('copyable', $row)
                        ? filter_var($row['copyable'], FILTER_VALIDATE_BOOLEAN)
                        : true,
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $flat = [];
        for ($n = 1; $n <= 8; $n++) {
            $label = trim((string) ($meta["field_{$n}_label"] ?? ''));
            $value = trim((string) ($meta["field_{$n}_value"] ?? ''));
            if ($label !== '' && $value !== '') {
                $flat[] = ['label' => $label, 'value' => $value, 'copyable' => true];
            }
        }
        if ($flat !== []) {
            return $flat;
        }

        $map = match ($key) {
            'pago_tigo_money' => [
                ['Número', ['tigo_phone', 'phone']],
                ['Alias', ['tigo_alias', 'alias']],
                ['CI / RUC', ['tigo_ci', 'ci', 'ruc']],
            ],
            'pago_transferencia' => [
                ['Banco', ['bank', 'banco']],
                ['Tipo de cuenta', ['account_type']],
                ['Nº de cuenta', ['account_number', 'cuenta']],
                ['Titular', ['account_holder', 'titular']],
                ['RUC', ['account_ci_ruc', 'ruc']],
                ['Alias bancario', ['bank_alias']],
            ],
            'pago_qr' => [
                ['Alias QR', ['qr_alias']],
                ['Link QR', ['qr_link']],
                ['ID QR', ['qr_id']],
            ],
            default => [],
        };

        $out = [];
        foreach ($map as [$label, $keys]) {
            $value = '';
            foreach ($keys as $k) {
                $candidate = trim((string) ($meta[$k] ?? ''));
                if ($candidate !== '') {
                    $value = $candidate;
                    break;
                }
            }
            if ($value !== '') {
                $out[] = ['label' => $label, 'value' => $value, 'copyable' => true];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function metodoTieneDatos(string $key, array $meta): bool
    {
        return $this->resolveFields($key, $meta) !== []
            || filled($meta['instructions'] ?? null)
            || filled($meta['qr_link'] ?? null);
    }
}
