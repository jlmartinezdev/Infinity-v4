<?php

namespace App\Services\WhatsApp\Soporte;

use App\Models\Novedad;
use App\Models\PlanUpsell;
use App\Models\WaOfertaEnvio;
use Illuminate\Support\Collection;

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
        $planActual = mb_strtolower(trim((string) ($cuenta['plan_nombre'] ?? $cuenta['plan'] ?? '')));
        $candidatos = PlanUpsell::publicados()
            ->with('plan')
            ->orderBy('orden')
            ->get()
            ->filter(function (PlanUpsell $item) use ($precio) {
                return $item->plan
                    && ($item->plan->estado ?? '') === 'activo'
                    && (float) $item->plan->precio > $precio;
            })
            ->values();

        $upsell = $this->elegirUpsellPremium($candidatos, $planActual);

        if ($upsell && $upsell->plan) {
            $this->registrar($telefono, 'plan:'.$upsell->plan_id, [
                'plan_id' => (int) $upsell->plan_id,
                'plan_nombre' => (string) $upsell->plan->nombre,
            ]);

            return $this->fraseUpsellSuave($upsell);
        }

        $novedad = Novedad::publicadas()
            ->whereIn('tipo', ['promo', 'upsell'])
            ->orderBy('orden')
            ->first();
        if ($novedad) {
            $this->registrar($telefono, 'novedad:'.$novedad->id);
            $titulo = trim((string) $novedad->titulo);

            return $titulo !== ''
                ? 'Si más adelante le interesa, tenemos una novedad: '.$titulo.'. Sin compromiso, cuando quiera le cuento.'
                : null;
        }

        return null;
    }

    /**
     * Prefiere Premium del mismo “mundo” (fibra/antena) si se puede inferir; si no, cualquier Premium; si no, el primero.
     *
     * @param  Collection<int, PlanUpsell>  $candidatos
     */
    private function elegirUpsellPremium(Collection $candidatos, string $planActualLower): ?PlanUpsell
    {
        if ($candidatos->isEmpty()) {
            return null;
        }

        $quiereFibra = (bool) preg_match('/ftth|fibra|gpon/u', $planActualLower);
        $quiereAntena = (bool) preg_match('/antena|wireless|radio/u', $planActualLower);

        $mismoGrupo = $candidatos->filter(function (PlanUpsell $item) use ($quiereFibra, $quiereAntena) {
            if (! $quiereFibra && ! $quiereAntena) {
                return true;
            }
            $n = mb_strtolower((string) ($item->plan?->nombre ?? ''));

            if ($quiereFibra) {
                return (bool) preg_match('/ftth|fibra|gpon/u', $n);
            }

            return (bool) preg_match('/\b\d+\s*mbps\b/u', $n) && ! preg_match('/ftth|fibra|gpon/u', $n)
                || (bool) preg_match('/antena|wireless/u', $n);
        });

        $pool = $mismoGrupo->isNotEmpty() ? $mismoGrupo : $candidatos;

        $premium = $pool->first(function (PlanUpsell $item) {
            $n = mb_strtolower((string) ($item->plan?->nombre ?? ''));

            return str_contains($n, 'premium') || (bool) $item->es_superior;
        });

        return $premium ?? $pool->first();
    }

    private function fraseUpsellSuave(PlanUpsell $upsell): string
    {
        $nombre = trim((string) ($upsell->plan?->nombre ?? 'el plan Premium'));
        $beneficio = trim((string) ($upsell->beneficios ?? ''));
        if ($beneficio !== '') {
            $beneficio = preg_replace('/\s+/u', ' ', $beneficio) ?: $beneficio;
            $beneficio = mb_substr($beneficio, 0, 140);

            return 'Su plan actual está bien. Si busca más prioridad ante un inconveniente, el '.$nombre.' incluye '.$beneficio.'. ¿Le detallo la diferencia?';
        }

        return 'Su plan actual está bien. Si busca más prioridad de atención cuando hay un inconveniente, el '.$nombre.' lo contempla. ¿Le detallo la diferencia?';
    }

    /**
     * @param  array<string, mixed>  $metaExtra
     */
    private function registrar(string $telefono, string $referencia, array $metaExtra = []): void
    {
        WaOfertaEnvio::query()->create([
            'telefono' => $telefono,
            'referencia' => $referencia,
        ]);
        try {
            app(\App\Services\WhatsApp\WaAgentAnalyticsService::class)->registrarEvento(
                \App\Models\WaAgentEvento::TIPO_OFERTA_PLAN,
                $telefono,
                'oferta',
                array_merge(['referencia' => $referencia], $metaExtra),
            );
        } catch (\Throwable) {
            //
        }
    }
}
