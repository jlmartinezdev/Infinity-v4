<?php

namespace App\Services\Portal;

class PortalFeatureFlagsService
{
    public function __construct(
        private readonly PortalAppConfigService $config
    ) {}

    /**
     * @return list<array{key: string, state: string, label: string|null, metadata: mixed}>
     */
    public function flags(): array
    {
        $cfg = $this->config->flagsRaw();
        $labels = config('portal_app.flag_labels', []);
        $pago = $this->config->pagoOnline();
        $out = [];

        foreach ($cfg as $key => $state) {
            $normalized = $this->normalizeState((string) $state, (string) $key, $pago);
            $item = [
                'key' => (string) $key,
                'state' => $normalized,
                'label' => $labels[$key] ?? null,
                'metadata' => null,
            ];

            if ($key === 'speed_test_screen' && $normalized === 'enabled') {
                $item['metadata'] = ['engine' => 'smart_check'];
            }

            if ($key === 'pago_online' && $normalized === 'coming_soon' && empty($item['label'])) {
                $item['label'] = 'Pago con tarjeta en camino';
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
     */
    private function normalizeState(string $state, string $key, array $pago): string
    {
        $state = strtolower(trim($state));

        if ($key === 'pago_online' && ($state === 'auto' || $state === '')) {
            $url = trim((string) ($pago['checkout_url'] ?? ''));

            return $url !== '' && str_starts_with($url, 'http') ? 'enabled' : 'coming_soon';
        }

        if (in_array($state, ['enabled', 'enable', 'on', 'true', '1'], true)) {
            return 'enabled';
        }
        if (in_array($state, ['coming_soon', 'coming-soon', 'soon'], true)) {
            return 'coming_soon';
        }

        return 'hidden';
    }
}
