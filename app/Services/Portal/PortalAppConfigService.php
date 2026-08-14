<?php

namespace App\Services\Portal;

use App\Models\PortalAppConfig;

/**
 * Merge de defaults (config/*.php + .env) con overrides guardados en BD (panel web).
 */
class PortalAppConfigService
{
    public function row(): PortalAppConfig
    {
        return PortalAppConfig::obtener();
    }

    /**
     * @return array<string, string>
     */
    public function flagsRaw(): array
    {
        $defaults = config('portal_app.flags', []);
        $override = $this->row()->flags ?? [];

        return array_merge($defaults, is_array($override) ? $override : []);
    }

    /**
     * @return array{checkout_url: ?string, provider: string}
     */
    public function pagoOnline(): array
    {
        $defaults = config('portal_app.pago_online', []);
        $override = $this->row()->pago_online ?? [];

        return [
            'checkout_url' => $override['checkout_url'] ?? ($defaults['checkout_url'] ?? null),
            'provider' => $override['provider'] ?? ($defaults['provider'] ?? 'bancard'),
        ];
    }

    /**
     * Metadata por método de pago (merge defaults + BD).
     *
     * @return array<string, array<string, mixed>>
     */
    public function metodosPago(): array
    {
        $defaults = config('portal_app.metodos_pago', []);
        $override = $this->row()->metodos_pago ?? [];
        $keys = config('portal_app.pago_metodos_keys', array_keys($defaults));
        $out = [];

        foreach ($keys as $key) {
            $base = is_array($defaults[$key] ?? null) ? $defaults[$key] : [];
            $over = is_array($override[$key] ?? null) ? $override[$key] : [];
            $out[$key] = array_merge($base, $over);
        }

        return $out;
    }

    /**
     * @return array{puntos_por_alta: int, link_base: string}
     */
    public function referidos(): array
    {
        $defaults = config('portal_app.referidos', []);
        $override = $this->row()->referidos ?? [];

        return [
            'puntos_por_alta' => (int) ($override['puntos_por_alta'] ?? ($defaults['puntos_por_alta'] ?? 50)),
            'link_base' => (string) ($override['link_base'] ?? ($defaults['link_base'] ?? '')),
        ];
    }

    /**
     * @return array{pagos: ?string, soporte: ?string}
     */
    public function whatsapp(): array
    {
        $defaults = config('portal_app.whatsapp', []);
        $override = $this->row()->whatsapp ?? [];

        return [
            'pagos' => $override['pagos'] ?? ($defaults['pagos'] ?? null),
            'soporte' => $override['soporte'] ?? ($defaults['soporte'] ?? null),
        ];
    }

    /**
     * @return array{disponibilidad_pct: mixed}
     */
    public function resumen(): array
    {
        $defaults = config('portal_app.resumen', []);
        $override = $this->row()->resumen ?? [];

        return [
            'disponibilidad_pct' => $override['disponibilidad_pct'] ?? ($defaults['disponibilidad_pct'] ?? null),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function faqsTopics(): array
    {
        $row = $this->row();
        if (is_array($row->faqs) && $row->faqs !== []) {
            return $row->faqs['topics'] ?? $row->faqs;
        }

        return config('portal_faqs.topics', []);
    }

    /**
     * @param  array{
     *   flags?: array<string, string>,
     *   pago_online?: array,
     *   metodos_pago?: array,
     *   referidos?: array,
     *   whatsapp?: array,
     *   resumen?: array,
     *   faqs?: array
     * }  $data
     */
    public function guardar(array $data): PortalAppConfig
    {
        $row = $this->row();
        foreach (['flags', 'pago_online', 'metodos_pago', 'referidos', 'whatsapp', 'resumen', 'faqs'] as $key) {
            if (array_key_exists($key, $data)) {
                $row->{$key} = $data[$key];
            }
        }
        $row->save();

        return $row;
    }
}
