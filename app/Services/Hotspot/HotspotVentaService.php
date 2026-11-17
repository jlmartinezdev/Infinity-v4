<?php

namespace App\Services\Hotspot;

use App\Models\HotspotPlan;
use App\Models\HotspotVenta;
use App\Models\ServicioHotspot;
use App\Models\TpagoPaymentLink;
use App\Services\RadiusHotspotSyncService;
use App\Services\Tpago\TpagoPaymentLinkService;
use App\Support\HotspotSesionOrigen;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class HotspotVentaService
{
    public const PREFIJO_REFERENCIA = 'HS-';

    public function __construct(
        private readonly TpagoPaymentLinkService $tpago,
        private readonly RadiusHotspotSyncService $radius,
    ) {}

    public static function esLinkDeVenta(TpagoPaymentLink $link): bool
    {
        return str_starts_with((string) $link->reference_id, self::PREFIJO_REFERENCIA);
    }

    /**
     * @return Collection<int, HotspotPlan>
     */
    public function planesActivos(): Collection
    {
        return HotspotPlan::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('precio')
            ->orderBy('gigas')
            ->get();
    }

    /**
     * @return array{token: string, checkout_url: string, monto: int, gigas: int, nombre: string, precio_texto: string}
     */
    public function iniciar(HotspotPlan $plan, ?string $mac): array
    {
        if (! $plan->activo) {
            throw ValidationException::withMessages([
                'plan_id' => ['Ese plan no está a la venta.'],
            ]);
        }

        if (! $this->tpago->disponible()) {
            throw ValidationException::withMessages([
                'tpago' => ['El pago con TPago no está disponible.'],
            ]);
        }

        $mac = $this->macONull($mac);
        $reuso = $this->ventaReutilizable($plan, $mac);
        if ($reuso) {
            return $this->payloadCompra($reuso, $plan);
        }

        $venta = HotspotVenta::query()->create([
            'hotspot_plan_id' => $plan->getKey(),
            'token' => Str::random(40),
            'mac' => $mac,
            'monto' => (int) $plan->precio,
            'gigas' => (int) $plan->gigas,
            'vigencia_horas' => (int) $plan->vigencia_horas,
            'rate_limit' => $plan->rate_limit,
            'estado' => HotspotVenta::PENDIENTE,
        ]);

        try {
            $pago = $this->tpago->paraVentaHotspot($venta);
        } catch (Throwable $e) {
            $venta->estado = HotspotVenta::ERROR;
            $venta->save();
            throw $e;
        }

        $venta->tpago_payment_link_id = $pago['link']->id;
        $venta->checkout_url = $pago['checkout_url'];
        $venta->save();

        return $this->payloadCompra($venta, $plan);
    }

    public function confirmarPago(TpagoPaymentLink $link): void
    {
        $venta = HotspotVenta::query()
            ->where('tpago_payment_link_id', $link->id)
            ->first();

        if (! $venta && preg_match('/^HS-(\d+)-/', (string) $link->reference_id, $m)) {
            $venta = HotspotVenta::query()->find((int) $m[1]);
            if ($venta && ! $venta->tpago_payment_link_id) {
                $venta->tpago_payment_link_id = $link->id;
            }
        }

        if (! $venta) {
            Log::error('[Hotspot venta] pago TPago sin venta local', [
                'link_id' => $link->id,
                'reference_id' => $link->reference_id,
            ]);

            return;
        }

        if (! $venta->estaPagada()) {
            $venta->username = $venta->username ?: $this->nuevoUsuario();
            $venta->password = $venta->password ?: ServicioHotspot::generarPassword();
            $venta->estado = HotspotVenta::PAGADO;
            $venta->pagado_at = $venta->pagado_at ?? now();
            if (! $venta->vence_at && (int) $venta->vigencia_horas > 0) {
                $venta->vence_at = now()->addHours((int) $venta->vigencia_horas);
            }
            $venta->save();
        }

        $this->publicar($venta);
    }

    public function marcarRechazada(TpagoPaymentLink $link): void
    {
        if (! self::esLinkDeVenta($link)) {
            return;
        }

        HotspotVenta::query()
            ->where('tpago_payment_link_id', $link->id)
            ->where('estado', HotspotVenta::PENDIENTE)
            ->update(['estado' => HotspotVenta::RECHAZADO]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function estadoPublico(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $venta = HotspotVenta::query()->where('token', $token)->first();
        if (! $venta) {
            return null;
        }

        if ($venta->estaPagada() && ! $venta->radius_ok) {
            $this->publicar($venta);
            $venta->refresh();
        }

        return $this->aPublico($venta);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function catalogoPublico(): array
    {
        return $this->planesActivos()->map(fn (HotspotPlan $plan) => [
            'id' => (int) $plan->getKey(),
            'nombre' => (string) $plan->nombre,
            'gigas' => (int) $plan->gigas,
            'precio' => (int) $plan->precio,
            'precio_texto' => $plan->precioTexto(),
            'vigencia' => $plan->vigenciaTexto(),
        ])->all();
    }

    private function publicar(HotspotVenta $venta): void
    {
        if (! $venta->estaPagada()) {
            return;
        }

        $result = $this->radius->publicarVoucher(
            (string) $venta->username,
            (string) $venta->password,
            (int) $venta->gigas,
            $venta->rate_limit ? (string) $venta->rate_limit : null,
            $venta->vence_at
        );

        $ok = ($result['success'] ?? false) && empty($result['skipped']);
        if ($ok !== (bool) $venta->radius_ok) {
            $venta->radius_ok = $ok;
            $venta->save();
        }

        if (! ($result['success'] ?? false)) {
            Log::warning('[Hotspot venta] no se publicó el usuario en RADIUS', [
                'venta_id' => $venta->id,
                'username' => $venta->username,
                'error' => $result['error'] ?? null,
            ]);
        }
    }

    private function nuevoUsuario(): string
    {
        for ($i = 0; $i < 8; $i++) {
            $username = '9'.str_pad((string) random_int(0, 999_999_999), 9, '0', STR_PAD_LEFT);
            $ocupado = HotspotVenta::query()->where('username', $username)->exists()
                || ServicioHotspot::query()->where('username', $username)->exists();
            if (! $ocupado) {
                return $username;
            }
        }

        throw new \RuntimeException('No se pudo generar un usuario de hotspot.');
    }

    private function macONull(?string $mac): ?string
    {
        $mac = trim((string) $mac);
        if ($mac === '' || ! HotspotSesionOrigen::esMac($mac)) {
            return null;
        }

        return HotspotSesionOrigen::macNormalizada($mac);
    }

    private function ventaReutilizable(HotspotPlan $plan, ?string $mac): ?HotspotVenta
    {
        if ($mac === null) {
            return null;
        }

        return HotspotVenta::query()
            ->where('hotspot_plan_id', $plan->getKey())
            ->where('mac', $mac)
            ->where('estado', HotspotVenta::PENDIENTE)
            ->where('monto', (int) $plan->precio)
            ->where('gigas', (int) $plan->gigas)
            ->whereNotNull('checkout_url')
            ->where('created_at', '>=', now()->subMinutes(20))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{token: string, checkout_url: string, monto: int, gigas: int, nombre: string, precio_texto: string}
     */
    private function payloadCompra(HotspotVenta $venta, HotspotPlan $plan): array
    {
        return [
            'token' => (string) $venta->token,
            'checkout_url' => (string) $venta->checkout_url,
            'monto' => (int) $venta->monto,
            'gigas' => (int) $venta->gigas,
            'nombre' => (string) $plan->nombre,
            'precio_texto' => $plan->precioTexto(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function aPublico(HotspotVenta $venta): array
    {
        $base = [
            'estado' => (string) $venta->estado,
            'gigas' => (int) $venta->gigas,
            'monto' => (int) $venta->monto,
            'checkout_url' => $venta->estado === HotspotVenta::PENDIENTE ? $venta->checkout_url : null,
        ];

        if (! $venta->estaPagada()) {
            return $base;
        }

        $base['username'] = (string) $venta->username;
        $base['password'] = (string) $venta->password;
        $base['vence_at'] = $venta->vence_at?->toIso8601String();

        return $base;
    }
}
