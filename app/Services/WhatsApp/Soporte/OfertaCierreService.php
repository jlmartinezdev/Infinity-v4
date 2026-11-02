<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Novedad;
use App\Models\PlanUpsell;
use App\Models\WaOfertaEnvio;

class OfertaCierreService
{
    /**
     * @param  array<string, mixed>|null  $cuenta
     */
    public function sugerir(?array $cuenta, bool $bloquear): ?string
    {
        if ($bloquear || $cuenta === null) {
            return null;
        }
        if (($cuenta['estado'] ?? '') !== 'activo') {
            return null;
        }
        if ((float) ($cuenta['saldo'] ?? 0) > 0 || (int) ($cuenta['dias_mora'] ?? 0) > 0) {
            return null;
        }
        if ((bool) ($cuenta['ticket_abierto'] ?? false)) {
            return null;
        }

        $telefono = (string) ($cuenta['whatsapp'] ?? '');
        if ($telefono === '') {
            return null;
        }

        $reciente = WaOfertaEnvio::query()
            ->where('telefono', $telefono)
            ->where('created_at', '>=', now()->subDays(14))
            ->exists();
        if ($reciente) {
            return null;
        }

        $precio = (float) ($cuenta['plan_precio'] ?? 0);
        $upsell = PlanUpsell::publicados()
            ->with('plan')
            ->orderBy('orden')
            ->get()
            ->first(function (PlanUpsell $item) use ($precio) {
                return $item->plan
                    && ($item->plan->estado ?? '') === 'activo'
                    && (float) $item->plan->precio > $precio;
            });

        if ($upsell && $upsell->plan) {
            $this->registrar($telefono, 'plan:'.$upsell->plan_id);

            return 'Si más adelante querés más velocidad, tenemos un plan superior. Cuando quieras te cuento.';
        }

        $novedad = Novedad::publicadas()
            ->whereIn('tipo', ['promo', 'upsell'])
            ->orderBy('orden')
            ->first();
        if ($novedad) {
            $this->registrar($telefono, 'novedad:'.$novedad->id);
            $titulo = trim((string) $novedad->titulo);

            return $titulo !== ''
                ? 'Tenemos una novedad: '.$titulo.'. Si te interesa, te cuento.'
                : null;
        }

        return null;
    }

    private function registrar(string $telefono, string $referencia): void
    {
        WaOfertaEnvio::query()->create([
            'telefono' => $telefono,
            'referencia' => $referencia,
        ]);
    }
}
