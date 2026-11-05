<?php

namespace App\Services\Tpago;

use App\Models\Cobro;
use App\Models\FacturaInterna;
use App\Models\TpagoPaymentLink;
use App\Services\FacturacionService;
use App\Services\WhatsApp\WhatsAppOutboundNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TpagoCallbackService
{
    public const MAX_VERIFICACIONES = 12;

    public function __construct(
        private readonly FacturacionService $facturacion,
        private readonly TpagoPagoVerificador $verificador,
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
        $campos = self::camposDesdePayload($payload);
        $alias = $campos['alias'];
        $responseCode = $campos['response_code'];
        $amount = $campos['amount'];
        $ticket = $campos['ticket_number'];
        $authCode = $campos['authorization_code'];
        $status = $campos['status'];

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

        $intentosPrevios = (int) data_get($link->callback_payload, '_verify.attempts', 0);
        $this->aplicarCallback($link, $payload, $campos);

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

        $consulta = $this->verificador->consultar((string) ($link->link_alias ?: $alias), (int) $link->amount);
        if ($consulta['estado'] !== 'confirmado') {
            $this->dejarPendienteVerificacion($link, $payload, $campos, $consulta, $intentosPrevios);

            return [
                'handled' => true,
                'link_id' => $link->id,
                'cobro_id' => null,
                'message' => 'Pago no confirmado en TPago; no se registra cobro.',
            ];
        }

        $amount = $consulta['monto'];
        if ($consulta['ticket'] !== '') {
            $ticket = $consulta['ticket'];
            $campos['ticket_number'] = $consulta['ticket'];
        }
        if ($consulta['authorization_code'] !== '') {
            $authCode = $consulta['authorization_code'];
            $campos['authorization_code'] = $consulta['authorization_code'];
        }
        if ($consulta['response_code'] !== '') {
            $campos['response_code'] = $consulta['response_code'];
        }

        $creado = false;

        try {
            $cobroId = DB::transaction(function () use ($link, $payload, $campos, $amount, $ticket, $authCode, &$creado) {
                $locked = TpagoPaymentLink::query()->lockForUpdate()->findOrFail($link->id);
                $this->aplicarCallback($locked, $payload, $campos);

                if ($locked->cobro_id) {
                    $locked->status = 'confirmed';
                    $locked->paid_at = $locked->paid_at ?? now();
                    $locked->save();

                    return $locked->cobro_id;
                }

                $factura = $locked->factura_interna_id
                    ? FacturaInterna::query()->find($locked->factura_interna_id)
                    : null;

                $monto = $amount > 0 ? $amount : (int) $locked->amount;
                if ($factura) {
                    $saldo = (int) round((float) $factura->saldo_pendiente);
                    if ($saldo > 0) {
                        $monto = min($monto, $saldo);
                    }
                }

                if ($monto <= 0) {
                    $locked->status = 'confirmed';
                    $locked->paid_at = now();
                    $locked->save();

                    return null;
                }

                $cobro = $this->facturacion->registrarCobro([
                    'cliente_id' => $locked->cliente_id,
                    'factura_interna_id' => $locked->factura_interna_id,
                    'monto' => $monto,
                    'fecha_pago' => now()->toDateString(),
                    'forma_pago' => 'tarjeta',
                    'referencia' => $ticket !== '' ? 'TPAGO-'.$ticket : 'TPAGO-'.$locked->link_alias,
                    'concepto' => $locked->factura_interna_id
                        ? null
                        : 'Saldo a favor (TPago)',
                    'observaciones' => 'Pago TPago'
                        .($locked->factura_interna_id ? '' : ' saldo a favor')
                        .($authCode !== '' ? ' auth '.$authCode : '')
                        .($locked->link_alias ? ' alias '.$locked->link_alias : ''),
                ], null);

                if (! $locked->factura_interna_id && $monto > 0) {
                    $this->facturacion->sumarSaldoAFavorCliente(
                        (int) $locked->cliente_id,
                        (float) $monto
                    );
                }

                $locked->cobro_id = $cobro->id;
                $locked->status = 'confirmed';
                $locked->paid_at = now();
                $locked->amount = $monto;
                $locked->save();
                $creado = true;

                return $cobro->id;
            });
        } catch (Throwable $e) {
            Log::error('[TPago callback] error registrando cobro: '.$e->getMessage(), [
                'link_id' => $link->id,
                'exception' => $e,
            ]);
            throw $e;
        }

        if ($creado && $cobroId) {
            $this->enviarReciboWhatsApp((int) $cobroId);
        }

        return [
            'handled' => true,
            'link_id' => $link->id,
            'cobro_id' => $cobroId,
            'message' => 'Pago confirmado y cobro registrado.',
        ];
    }

    /**
     * Reintenta links cuyo callback llegó antes de que TPago liste el pago,
     * o cuando la API no respondió. No llama a Bancard.
     */
    public function reintentarPendientes(): int
    {
        $links = TpagoPaymentLink::query()
            ->whereNull('cobro_id')
            ->where('status', 'pending_verify')
            ->where('updated_at', '<=', now()->subMinutes(2))
            ->orderBy('id')
            ->limit(30)
            ->get();

        $revisados = 0;
        foreach ($links as $link) {
            $intentos = (int) data_get($link->callback_payload, '_verify.attempts', 0);
            if ($intentos >= self::MAX_VERIFICACIONES) {
                continue;
            }

            $payload = $link->callback_payload;
            if (! is_array($payload)) {
                continue;
            }

            unset($payload['_verify']);
            $this->handle($payload);
            $revisados++;
        }

        return $revisados;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{alias: string, response_code: string, amount: int, ticket_number: string, authorization_code: string, status: string}  $campos
     * @param  array{estado: string, monto: int, ticket: string, authorization_code: string, response_code: string, motivo: string}  $consulta
     */
    private function dejarPendienteVerificacion(TpagoPaymentLink $link, array $payload, array $campos, array $consulta, int $intentosPrevios): void
    {
        $intentos = $intentosPrevios + 1;
        $payload['_verify'] = [
            'attempts' => $intentos,
            'estado' => $consulta['estado'],
            'motivo' => $consulta['motivo'],
            'at' => now()->toIso8601String(),
        ];

        $this->aplicarCallback($link, $payload, $campos);
        $link->status = 'pending_verify';
        $link->save();

        Log::warning('[TPago callback] cobro no registrado: pago sin confirmación de la API', [
            'link_id' => $link->id,
            'link_alias' => $link->link_alias,
            'estado' => $consulta['estado'],
            'motivo' => $consulta['motivo'],
            'intentos' => $intentos,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{alias: string, response_code: string, amount: int, ticket_number: string, authorization_code: string, status: string}
     */
    public static function camposDesdePayload(array $payload): array
    {
        return [
            'alias' => (string) (
                $payload['link_alias']
                ?? $payload['hook_alias']
                ?? data_get($payload, 'payment.link_alias')
                ?? data_get($payload, 'payment.hook_alias')
                ?? data_get($payload, 'payment_link.link_alias')
                ?? ''
            ),
            'response_code' => (string) (
                $payload['response_code']
                ?? data_get($payload, 'payment.response_code')
                ?? ''
            ),
            'amount' => (int) (
                $payload['amount']
                ?? data_get($payload, 'payment.amount')
                ?? 0
            ),
            'ticket_number' => (string) (
                $payload['ticket_number']
                ?? data_get($payload, 'payment.ticket_number')
                ?? ''
            ),
            'authorization_code' => (string) (
                $payload['authorization_code']
                ?? data_get($payload, 'payment.authorization_code')
                ?? ''
            ),
            'status' => strtolower((string) (
                $payload['status']
                ?? data_get($payload, 'payment.status')
                ?? ''
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{alias: string, response_code: string, amount: int, ticket_number: string, authorization_code: string, status: string}  $campos
     */
    private function aplicarCallback(TpagoPaymentLink $link, array $payload, array $campos): void
    {
        $link->callback_payload = $payload;
        if ($campos['response_code'] !== '') {
            $link->response_code = $campos['response_code'];
        }
        if ($campos['ticket_number'] !== '') {
            $link->ticket_number = $campos['ticket_number'];
        }
        if ($campos['authorization_code'] !== '') {
            $link->authorization_code = $campos['authorization_code'];
        }
    }

    private function enviarReciboWhatsApp(int $cobroId): void
    {
        $enviar = function () use ($cobroId): void {
            try {
                $cobro = Cobro::query()->with('cliente')->find($cobroId);
                if (! $cobro) {
                    return;
                }

                $telefono = trim((string) ($cobro->cliente?->telefono ?? ''));
                if ($telefono === '') {
                    Log::info('[TPago] Recibo WhatsApp omitido: cliente sin teléfono', [
                        'cobro_id' => $cobroId,
                        'cliente_id' => $cobro->cliente_id,
                    ]);
                    Log::channel('tpago')->info('Recibo WhatsApp omitido: sin teléfono', [
                        'cobro_id' => $cobroId,
                    ]);

                    return;
                }

                $result = app(WhatsAppOutboundNotifier::class)->reciboPago($cobro, forzar: true);
                Log::info('[TPago] Recibo WhatsApp', [
                    'cobro_id' => $cobroId,
                    'ok' => $result['ok'],
                    'message' => $result['message'],
                ]);
                Log::channel('tpago')->info('Recibo WhatsApp', [
                    'cobro_id' => $cobroId,
                    'ok' => $result['ok'],
                    'message' => $result['message'],
                ]);
            } catch (Throwable $e) {
                Log::warning('[TPago] Recibo WhatsApp falló: '.$e->getMessage(), [
                    'cobro_id' => $cobroId,
                ]);
            }
        };

        defer($enviar);
    }
}
