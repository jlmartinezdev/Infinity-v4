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

    private function fraseUpsellSuave(PlanUpsell $upsell): string
    {
        $nombre = trim((string) ($upsell->plan?->nombre ?? 'un plan con más prioridad'));
        $beneficio = trim((string) ($upsell->beneficios ?? ''));
        // Una sola idea: prioridad / tranquilidad; el plan actual sigue siendo válido.
        if ($beneficio !== '') {
            $beneficio = preg_replace('/\s+/u', ' ', $beneficio) ?: $beneficio;
            $beneficio = mb_substr($beneficio, 0, 140);

            return 'Su plan actual está bien. Si alguna vez quiere más tranquilidad ante un reclamo, el '.$nombre.' incluye '.$beneficio.'. Cuando quiera, le detallo.';
        }

        return 'Su plan actual está bien. Si alguna vez quiere más prioridad de atención cuando hay un inconveniente, el '.$nombre.' lo contempla. Cuando quiera, le cuento.';
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
