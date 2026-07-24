<?php

namespace App\Services\WhatsApp;

/**
 * Normaliza teléfonos a formato WhatsApp (solo dígitos, con código país, sin +).
 * Ej.: 0981 123 456 → 595981123456
 * AR móvil: +54 11… ↔ 54911… (WhatsApp inserta el 9).
 */
class WhatsAppPhoneNormalizer
{
    public function normalize(?string $phone, ?string $defaultCountryCode = null): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return null;
        }

        $country = preg_replace('/\D+/', '', (string) ($defaultCountryCode ?? config('whatsapp.default_country_code', '595'))) ?: '595';

        // Prefijo internacional 00…
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // Argentina móvil: +54 11… → +54 9 11… (WhatsApp usa 549…)
        if (str_starts_with($digits, '54') && ! str_starts_with($digits, '549') && strlen($digits) >= 12) {
            $digits = '549'.substr($digits, 2);
        }

        // Ya viene con el código país por defecto
        if (str_starts_with($digits, $country) && strlen($digits) >= strlen($country) + 8) {
            return $digits;
        }

        // Local (Paraguay u otro): 09XXXXXXXX → quitar 0 y anteponer país default
        if (str_starts_with($digits, '0')) {
            return $country.substr($digits, 1);
        }

        // Ya parece E.164 sin + (ej. 5491141914293, 595981123456)
        if (strlen($digits) >= 11) {
            return $digits;
        }

        return $country.$digits;
    }

    /**
     * Variantes de un número para buscar en BD (formato local, +54, 549, etc.).
     *
     * @return list<string>
     */
    public function variants(?string $phone): array
    {
        $normalized = $this->normalize($phone);
        if (! $normalized) {
            return [];
        }

        $out = [$normalized];
        $raw = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($raw !== '') {
            $out[] = $raw;
            $out[] = '+'.$raw;
        }

        // Paraguay: 595981… ↔ 0981…
        if (str_starts_with($normalized, '595') && strlen($normalized) >= 12) {
            $local = '0'.substr($normalized, 3);
            $out[] = $local;
            $out[] = substr($normalized, 3);
        }

        // Argentina: 54911… ↔ 5411… ↔ +54 11… ↔ 11…
        if (str_starts_with($normalized, '549') && strlen($normalized) >= 13) {
            $sinNueve = '54'.substr($normalized, 3);
            $out[] = $sinNueve;
            $out[] = '+'.$sinNueve;
            $out[] = '+'.$normalized;
            $out[] = substr($normalized, 3); // 911…
            $out[] = '0'.substr($normalized, 3); // 0911…
            // Sin el 9 de móvil: 11…
            if (str_starts_with(substr($normalized, 3), '9')) {
                $areaLocal = substr($normalized, 4); // 1126668022
                $out[] = $areaLocal;
                $out[] = '0'.$areaLocal;
                $out[] = '+54'.$areaLocal;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }
}
