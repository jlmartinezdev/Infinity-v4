<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Canje;
use App\Models\Novedad;
use App\Models\Premio;
use App\Services\Loyalty\CanjeService;
use App\Services\Loyalty\PuntosService;
use App\Services\Loyalty\UpsellService;
use Illuminate\Http\Request;

/**
 * Loyalty / CMS — endpoints del portal cliente.
 */
class PortalLoyaltyController extends ApiController
{
    public function __construct(
        private readonly PuntosService $puntos,
        private readonly CanjeService $canjes,
        private readonly UpsellService $upsell,
    ) {}

    public function novedades()
    {
        $items = Novedad::publicadas()
            ->orderBy('orden')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Novedad $n) => $n->toPortalArray())
            ->values();

        return $this->ok($items);
    }

    public function puntos(Request $request)
    {
        return $this->ok($this->puntos->resumenPortal((int) $request->user()->cliente_id));
    }

    public function premios()
    {
        $items = Premio::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get()
            ->map(fn (Premio $p) => $p->toPortalArray())
            ->values();

        return $this->ok($items);
    }

    public function canjesIndex(Request $request)
    {
        $items = Canje::with('premio')
            ->where('cliente_id', $request->user()->cliente_id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Canje $c) => $c->toPortalArray())
            ->values();

        return $this->ok($items);
    }

    public function canjesStore(Request $request)
    {
        $validated = $request->validate([
            'premio_id' => ['required', 'integer', 'exists:premios,id'],
            // Legacy: la app antigua podía enviar modalidad; se ignora si el premio tiene tipo.
            'modalidad' => ['nullable', 'string', 'in:retiro_oficina,descuento_factura'],
        ]);

        try {
            $canje = $this->canjes->crear(
                (int) $request->user()->cliente_id,
                (int) $validated['premio_id'],
                $validated['modalidad'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($canje->toPortalArray(), 'Canje registrado', 201);
    }

    public function planesUpsell()
    {
        return $this->ok($this->upsell->catalogoPortal());
    }

    public function solicitudCambioPlan(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:planes,plan_id'],
            'servicio_id' => ['required', 'integer', 'exists:servicios,servicio_id'],
        ]);

        try {
            $result = $this->upsell->solicitarCambioPlan(
                (int) $request->user()->cliente_id,
                (int) $validated['servicio_id'],
                (int) $validated['plan_id']
            );
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok($result, $result['mensaje'] ?? 'OK');
    }
}
