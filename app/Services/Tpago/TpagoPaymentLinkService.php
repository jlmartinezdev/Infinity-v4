<?php

namespace App\Services\Tpago;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\TpagoPaymentLink;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TpagoPaymentLinkService
{
    public function __construct(
        private readonly TpagoClient $client,
    ) {}

    public function disponible(): bool
    {
        return $this->client->enabled();
    }

    /**
     * Genera o reutiliza un link TPago para el saldo de una factura interna.
     *
     * @return array{
     *   link: TpagoPaymentLink,
     *   checkout_url: string,
     *   link_alias: string|null,
     *   amount: int,
     *   reused: bool,
     *   provider: string,
     *   expires_at: string|null
     * }
     */
    public function paraFactura(FacturaInterna $factura, bool $forceNew = false): array
    {
        if (! $this->client->enabled()) {
            throw ValidationException::withMessages([
                'tpago' => ['TPago no está configurado (keys / commerce_code / branch_code).'],
            ]);
        }

        $factura->refresh();

        if (in_array($factura->estado, ['anulada', 'cancelada'], true)) {
            throw ValidationException::withMessages([
                'factura_interna_id' => ['La factura está anulada o cancelada.'],
            ]);
        }

        $saldo = (int) round((float) $factura->saldo_pendiente);
        if ($saldo < 1) {
            throw ValidationException::withMessages([
                'factura_interna_id' => ['La factura no tiene saldo pendiente.'],
            ]);
        }

        if (! $forceNew) {
            $reuso = $this->linkReutilizable($factura, $saldo);
            if ($reuso) {
                return $this->payload($reuso, true);
            }
        }

        $cliente = $factura->relationLoaded('cliente')
            ? $factura->cliente
            : $factura->cliente()->first();

        $nombre = trim(($cliente?->nombre ?? '').' '.($cliente?->apellido ?? ''));
        $descripcion = \Illuminate\Support\Str::limit(
            'Factura #'.$factura->id.($nombre !== '' ? ' - '.$nombre : ''),
            120,
            ''
        );

        $referenceId = 'FI-'.$factura->id.'-'.now()->format('YmdHis');

        $requestPayload = [
            'amount' => $saldo,
            'description' => $descripcion,
            'reference_id' => $referenceId,
            'require_user_data' => false,
        ];

        $link = TpagoPaymentLink::create([
            'factura_interna_id' => $factura->id,
            'cliente_id' => $factura->cliente_id,
            'amount' => $saldo,
            'description' => $descripcion,
            'reference_id' => $referenceId,
            'status' => 'creating',
            'request_payload' => $requestPayload,
        ]);

        try {
            $response = $this->client->generatePaymentLink($requestPayload);
            $pl = $response['payment_link'] ?? [];

            $expiresAt = null;
            if (! empty($pl['expiration_datetime'])) {
                try {
                    $expiresAt = Carbon::parse($pl['expiration_datetime']);
                } catch (Throwable) {
                    $expiresAt = null;
                }
            }

            $link->fill([
                'tpago_link_id' => isset($pl['id']) ? (int) $pl['id'] : null,
                'link_alias' => $pl['link_alias'] ?? null,
                'link_url' => $pl['link_url'] ?? null,
                'status' => ! empty($pl['available']) || ! empty($pl['link_url']) ? 'pending' : 'unavailable',
                'expires_at' => $expiresAt,
            ]);
            $link->save();
        } catch (Throwable $e) {
            $link->status = 'error';
            $link->save();
            Log::error('[TPago] generate link: '.$e->getMessage(), [
                'factura_interna_id' => $factura->id,
                'link_id' => $link->id,
            ]);
            throw new RuntimeException('No se pudo generar el link de pago TPago: '.$e->getMessage(), 0, $e);
        }

        if (empty($link->link_url)) {
            throw new RuntimeException('TPago no devolvió link_url.');
        }

        return $this->payload($link, false);
    }

    /**
     * Link TPago por monto libre (saldo a favor / adelanto), sin factura.
     *
     * @return array{
     *   link: TpagoPaymentLink,
     *   checkout_url: string,
     *   link_alias: string|null,
     *   amount: int,
     *   reused: bool,
     *   provider: string,
     *   expires_at: string|null,
     *   purpose: string
     * }
     */
    public function paraMonto(Cliente $cliente, int $amount, bool $forceNew = false): array
    {
        if (! $this->client->enabled()) {
            throw ValidationException::withMessages([
                'tpago' => ['TPago no está configurado (keys / commerce_code / branch_code).'],
            ]);
        }

        if ($amount < 1) {
            throw ValidationException::withMessages([
                'amount' => ['El monto debe ser al menos 1 Gs.'],
            ]);
        }

        $clienteId = (int) $cliente->cliente_id;

        if (! $forceNew) {
            $reuso = TpagoPaymentLink::query()
                ->where('cliente_id', $clienteId)
                ->whereNull('factura_interna_id')
                ->where('amount', $amount)
                ->whereIn('status', ['pending', 'creating'])
                ->whereNotNull('link_url')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->whereNull('cobro_id')
                ->orderByDesc('id')
                ->first();

            if ($reuso) {
                $payload = $this->payload($reuso, true);
                $payload['purpose'] = 'saldo_favor';

                return $payload;
            }
        }

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $descripcion = \Illuminate\Support\Str::limit(
            'Saldo a favor'.($nombre !== '' ? ' - '.$nombre : ''),
            120,
            ''
        );
        $referenceId = 'SF-'.$clienteId.'-'.now()->format('YmdHis');

        $requestPayload = [
            'amount' => $amount,
            'description' => $descripcion,
            'reference_id' => $referenceId,
            'require_user_data' => false,
        ];

        $link = TpagoPaymentLink::create([
            'factura_interna_id' => null,
            'cliente_id' => $clienteId,
            'amount' => $amount,
            'description' => $descripcion,
            'reference_id' => $referenceId,
            'status' => 'creating',
            'request_payload' => $requestPayload,
        ]);

        try {
            $response = $this->client->generatePaymentLink($requestPayload);
            $pl = $response['payment_link'] ?? [];

            $expiresAt = null;
            if (! empty($pl['expiration_datetime'])) {
                try {
                    $expiresAt = Carbon::parse($pl['expiration_datetime']);
                } catch (Throwable) {
                    $expiresAt = null;
                }
            }

            $link->fill([
                'tpago_link_id' => isset($pl['id']) ? (int) $pl['id'] : null,
                'link_alias' => $pl['link_alias'] ?? null,
                'link_url' => $pl['link_url'] ?? null,
                'status' => ! empty($pl['available']) || ! empty($pl['link_url']) ? 'pending' : 'unavailable',
                'expires_at' => $expiresAt,
            ]);
            $link->save();
        } catch (Throwable $e) {
            $link->status = 'error';
            $link->save();
            Log::error('[TPago] generate link saldo a favor: '.$e->getMessage(), [
                'cliente_id' => $clienteId,
                'link_id' => $link->id,
            ]);
            throw new RuntimeException('No se pudo generar el link de pago TPago: '.$e->getMessage(), 0, $e);
        }

        if (empty($link->link_url)) {
            throw new RuntimeException('TPago no devolvió link_url.');
        }

        $payload = $this->payload($link, false);
        $payload['purpose'] = 'saldo_favor';

        return $payload;
    }

    /**
     * Primera factura pendiente del cliente (por vencimiento / emisión).
     */
    public function primeraFacturaPendiente(Cliente|int $cliente): ?FacturaInterna
    {
        $clienteId = $cliente instanceof Cliente ? $cliente->cliente_id : (int) $cliente;
        $saldoExpr = FacturaInterna::sqlSaldoPendienteExpr();

        return FacturaInterna::query()
            ->where('cliente_id', $clienteId)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($saldoExpr.' > 0.009')
            ->orderByRaw('fecha_vencimiento IS NULL, fecha_vencimiento ASC')
            ->orderBy('fecha_emision')
            ->orderBy('id')
            ->first();
    }

    public function ultimoLinkActivo(FacturaInterna $factura): ?TpagoPaymentLink
    {
        $saldo = (int) round((float) $factura->saldo_pendiente);

        return $this->linkReutilizable($factura, $saldo);
    }

    private function linkReutilizable(FacturaInterna $factura, int $saldo): ?TpagoPaymentLink
    {
        return TpagoPaymentLink::query()
            ->where('factura_interna_id', $factura->id)
            ->where('amount', $saldo)
            ->whereIn('status', ['pending', 'creating'])
            ->whereNotNull('link_url')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('cobro_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *   link: TpagoPaymentLink,
     *   checkout_url: string,
     *   link_alias: string|null,
     *   amount: int,
     *   reused: bool,
     *   provider: string,
     *   expires_at: string|null
     * }
     */
    private function payload(TpagoPaymentLink $link, bool $reused): array
    {
        return [
            'link' => $link,
            'checkout_url' => (string) $link->link_url,
            'link_alias' => $link->link_alias,
            'amount' => (int) $link->amount,
            'reused' => $reused,
            'provider' => 'tpago',
            'expires_at' => $link->expires_at?->toIso8601String(),
        ];
    }
}
