<?php

namespace App\Services\Tpago;

use App\Models\FacturaInterna;
use App\Models\TpagoPaymentLink;
use App\Services\FacturacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TpagoCallbackService
{
    public function __construct(
        private readonly FacturacionService $facturacion,
    ) {}

    /**
     * Procesa el POST de confirmación de TPago.
     * Debe terminar en éxito para que Bancard no revierta el cobro.
     *
     * @param  array<string, mixed>  $payload
     * @return array{handled: bool, link_id: ?int, cobro_id: ?int, message: string}
     */
    public function handle(array $payload): array
    {
        $alias = (string) (
            $payload['link_alias']
            ?? data_get($payload, 'payment.link_alias')
            ?? data_get($payload, 'payment_link.link_alias')
            ?? ''
        );
        $responseCode = (string) (
            $payload['response_code']
            ?? data_get($payload, 'payment.response_code')
            ?? ''
        );
        $amount = (int) (
            $payload['amount']
            ?? data_get($payload, 'payment.amount')
            ?? 0
        );
        $ticket = (string) (
            $payload['ticket_number']
            ?? data_get($payload, 'payment.ticket_number')
            ?? ''
        );
        $authCode = (string) (
            $payload['authorization_code']
            ?? data_get($payload, 'payment.authorization_code')
            ?? ''
        );
        $status = strtolower((string) (
            $payload['status']
            ?? data_get($payload, 'payment.status')
            ?? ''
        ));

        Log::info('[TPago callback] recibido', [
            'link_alias' => $alias,
            'response_code' => $responseCode,
            'amount' => $amount,
            'ticket_number' => $ticket,
            'status' => $status,
        ]);

        $link = $alias !== ''
            ? TpagoPaymentLink::query()->where('link_alias', $alias)->first()
            : null;

        if (! $link && $ticket !== '') {
            $link = TpagoPaymentLink::query()->where('ticket_number', $ticket)->first();
        }

        if (! $link) {
            Log::warning('[TPago callback] link no encontrado (aún se confirma a Bancard)', [
                'link_alias' => $alias,
                'ticket_number' => $ticket,
            ]);

            return [
                'handled' => true,
                'link_id' => null,
                'cobro_id' => null,
                'message' => 'Callback recibido; sin link local asociado.',
            ];
        }

        $link->callback_payload = $payload;
        $link->response_code = $responseCode !== '' ? $responseCode : $link->response_code;
        $link->ticket_number = $ticket !== '' ? $ticket : $link->ticket_number;
        $link->authorization_code = $authCode !== '' ? $authCode : $link->authorization_code;

        $approved = $responseCode === '00'
            || in_array($status, ['confirmed', 'approved', 'success', 'paid'], true);

        if (! $approved) {
            $link->status = $status !== '' ? $status : 'rejected';
            $link->save();

            return [
                'handled' => true,
                'link_id' => $link->id,
                'cobro_id' => $link->cobro_id,
                'message' => 'Pago no aprobado; callback registrado.',
            ];
        }

        if ($link->cobro_id) {
            $link->status = 'confirmed';
            $link->paid_at = $link->paid_at ?? now();
            $link->save();

            return [
                'handled' => true,
                'link_id' => $link->id,
                'cobro_id' => $link->cobro_id,
                'message' => 'Cobro ya registrado (idempotente).',
            ];
        }

        try {
            $cobroId = DB::transaction(function () use ($link, $amount, $ticket, $authCode) {
                $link = TpagoPaymentLink::query()->lockForUpdate()->findOrFail($link->id);
                if ($link->cobro_id) {
                    return $link->cobro_id;
                }

                $factura = $link->factura_interna_id
                    ? FacturaInterna::query()->find($link->factura_interna_id)
                    : null;

                $monto = $amount > 0 ? $amount : (int) $link->amount;
                if ($factura) {
                    $saldo = (int) round((float) $factura->saldo_pendiente);
                    if ($saldo > 0) {
                        $monto = min($monto, $saldo);
                    }
                }

                if ($monto <= 0) {
                    $link->status = 'confirmed';
                    $link->paid_at = now();
                    $link->save();

                    return null;
                }

                $cobro = $this->facturacion->registrarCobro([
                    'cliente_id' => $link->cliente_id,
                    'factura_interna_id' => $link->factura_interna_id,
                    'monto' => $monto,
                    'fecha_pago' => now()->toDateString(),
                    'forma_pago' => 'tarjeta',
                    'referencia' => $ticket !== '' ? 'TPAGO-'.$ticket : 'TPAGO-'.$link->link_alias,
                    'concepto' => $link->factura_interna_id
                        ? null
                        : 'Saldo a favor (TPago)',
                    'observaciones' => 'Pago TPago'
                        .($link->factura_interna_id ? '' : ' saldo a favor')
                        .($authCode !== '' ? ' auth '.$authCode : '')
                        .($link->link_alias ? ' alias '.$link->link_alias : ''),
                ], null);

                if (! $link->factura_interna_id && $monto > 0) {
                    $this->facturacion->sumarSaldoAFavorCliente(
                        (int) $link->cliente_id,
                        (float) $monto
                    );
                }

                $link->cobro_id = $cobro->id;
                $link->status = 'confirmed';
                $link->paid_at = now();
                $link->amount = $monto;
                $link->save();

                return $cobro->id;
            });
        } catch (Throwable $e) {
            Log::error('[TPago callback] error registrando cobro: '.$e->getMessage(), [
                'link_id' => $link->id,
                'exception' => $e,
            ]);
            throw $e;
        }

        return [
            'handled' => true,
            'link_id' => $link->id,
            'cobro_id' => $cobroId,
            'message' => 'Pago confirmado y cobro registrado.',
        ];
    }
}
