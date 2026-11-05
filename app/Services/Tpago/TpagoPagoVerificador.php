<?php

namespace App\Services\Tpago;

use Throwable;

/**
 * Confirma contra la API de TPago que el alias tiene un pago aprobado
 * por el mismo monto del link. El cuerpo del callback no alcanza.
 */
class TpagoPagoVerificador
{
    public function __construct(
        private readonly TpagoClient $client,
    ) {}

    /**
     * @return array{
     *     estado: 'confirmado'|'no_pago'|'indisponible',
     *     monto: int,
     *     ticket: string,
     *     authorization_code: string,
     *     response_code: string,
     *     motivo: string
     * }
     */
    public function consultar(string $alias, int $montoLink): array
    {
        $alias = trim($alias);
        if ($alias === '' || ! $this->puedeConsultar()) {
            return $this->resultado('indisponible', 0, '', '', '', 'TPago no está listo para consultar el pago.');
        }

        try {
            $json = $this->client->getPaymentsByAlias($alias);
        } catch (Throwable $e) {
            return $this->resultado('indisponible', 0, '', '', '', 'No se pudo consultar el pago en TPago.');
        }

        if (! is_array($json)) {
            return $this->resultado('indisponible', 0, '', '', '', 'TPago respondió un cuerpo inválido.');
        }

        foreach ($this->extraerPagos($json) as $pago) {
            if (! is_array($pago) || ! $this->aprobado($pago)) {
                continue;
            }

            $monto = $this->monto($pago);
            if ($monto <= 0 || ($montoLink > 0 && $monto !== $montoLink)) {
                continue;
            }

            return $this->resultado(
                'confirmado',
                $monto,
                (string) ($pago['ticket_number'] ?? ''),
                (string) ($pago['authorization_code'] ?? ''),
                (string) ($pago['response_code'] ?? '00'),
                'Pago confirmado en TPago.',
            );
        }

        return $this->resultado('no_pago', 0, '', '', '', 'TPago no tiene un pago aprobado por el monto del link.');
    }

    private function puedeConsultar(): bool
    {
        return $this->client->hasCredentials()
            && filled(config('tpago.commerce_code'))
            && filled(config('tpago.branch_code'));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function extraerPagos(array $json): array
    {
        if (array_key_exists('payments', $json) || array_key_exists('payment', $json)) {
            foreach (['payments', 'payment'] as $key) {
                $lista = $this->normalizarLista($json[$key] ?? null);
                if ($lista !== []) {
                    return $lista;
                }
            }

            return [];
        }

        $data = $json['data'] ?? null;
        if (is_array($data)) {
            if (array_key_exists('payments', $data) || array_key_exists('payment', $data)) {
                foreach (['payments', 'payment'] as $key) {
                    $lista = $this->normalizarLista($data[$key] ?? null);
                    if ($lista !== []) {
                        return $lista;
                    }
                }

                return [];
            }

            $lista = $this->normalizarLista($data);
            if ($lista !== []) {
                return $lista;
            }
        }

        return $this->normalizarLista($json);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizarLista(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_values(array_filter($value, 'is_array'));
        }

        if (isset($value['response_code']) || isset($value['amount']) || isset($value['status']) || isset($value['ticket_number'])) {
            return [$value];
        }

        return [];
    }

    /** @param  array<string, mixed>  $pago */
    private function aprobado(array $pago): bool
    {
        $code = (string) ($pago['response_code'] ?? '');
        $status = strtolower((string) ($pago['status'] ?? ''));

        return $code === '00'
            || in_array($status, ['confirmed', 'approved', 'success', 'paid'], true);
    }

    /** @param  array<string, mixed>  $pago */
    private function monto(array $pago): int
    {
        $raw = $pago['amount'] ?? 0;
        if (is_string($raw)) {
            $raw = str_replace([' ', ','], '', $raw);
        }

        return (int) round((float) $raw);
    }

    /**
     * @return array{
     *     estado: 'confirmado'|'no_pago'|'indisponible',
     *     monto: int,
     *     ticket: string,
     *     authorization_code: string,
     *     response_code: string,
     *     motivo: string
     * }
     */
    private function resultado(
        string $estado,
        int $monto,
        string $ticket,
        string $authorization,
        string $responseCode,
        string $motivo,
    ): array {
        return [
            'estado' => $estado,
            'monto' => $monto,
            'ticket' => $ticket,
            'authorization_code' => $authorization,
            'response_code' => $responseCode,
            'motivo' => $motivo,
        ];
    }
}
