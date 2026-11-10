<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\Servicio;
use App\Services\Tpago\TpagoPaymentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class WebConsultaController extends ApiController
{
    public function cuenta(Request $request): JsonResponse
    {
        $data = $request->validate([
            'documento' => ['required', 'string', 'min:4', 'max:32'],
        ]);

        $cliente = Cliente::buscarPorCedula($data['documento']);
        if (! $cliente) {
            return $this->fail('No encontramos una cuenta con ese documento.', 404);
        }

        return $this->ok($this->presentar($cliente));
    }

    public function pago(Request $request, TpagoPaymentLinkService $tpago): JsonResponse
    {
        $data = $request->validate([
            'documento' => ['required', 'string', 'min:4', 'max:32'],
            'factura_interna_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $cliente = Cliente::buscarPorCedula($data['documento']);
        if (! $cliente) {
            return $this->fail('No encontramos una cuenta con ese documento.', 404);
        }

        if (! $tpago->disponible()) {
            return $this->fail('El pago con TPago no está disponible.', 503);
        }

        $factura = $this->facturaParaPago($cliente, (int) ($data['factura_interna_id'] ?? 0));
        if (! $factura) {
            return $this->fail('Esa cuenta no tiene saldo para pagar.', 422);
        }

        try {
            $result = $tpago->paraFactura($factura, $request->boolean('force_new'));
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first() ?? 'No se pudo crear el link de pago.';

            return $this->fail((string) $msg, 422);
        } catch (Throwable $e) {
            return $this->fail(
                $e instanceof RuntimeException ? $e->getMessage() : 'No se pudo crear el link de pago.',
                502
            );
        }

        return $this->ok([
            'checkout_url' => $result['checkout_url'],
            'factura_interna_id' => $factura->id,
            'amount' => $result['amount'],
            'moneda' => 'PYG',
            'link_alias' => $result['link_alias'],
            'expires_at' => $result['expires_at'],
            'reused' => $result['reused'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Cliente $cliente): array
    {
        $cliente->loadMissing(['servicios.plan']);
        $servicio = $cliente->servicios
            ->sortBy(fn (Servicio $s) => match ($s->estado) {
                Servicio::ESTADO_ACTIVO => 0,
                Servicio::ESTADO_SUSPENDIDO => 1,
                Servicio::ESTADO_CORTADO => 2,
                default => 9,
            })
            ->first();

        $expr = FacturaInterna::sqlSaldoPendienteExpr();
        $facturas = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($expr.' > 0.009')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();

        $saldo = (int) round($facturas->sum(fn (FacturaInterna $f) => (float) $f->saldo_pendiente));

        return [
            'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
            'documento' => (string) $cliente->cedula,
            'estado' => $servicio?->estado,
            'plan' => $servicio?->plan?->nombre,
            'saldo' => $saldo,
            'moneda' => 'PYG',
            'facturas' => $facturas->map(fn (FacturaInterna $f) => [
                'id' => (int) $f->id,
                'estado' => $f->estado,
                'periodo_desde' => optional($f->periodo_desde)?->toDateString(),
                'periodo_hasta' => optional($f->periodo_hasta)?->toDateString(),
                'vencimiento' => optional($f->fecha_vencimiento)?->toDateString(),
                'total' => (int) round((float) $f->total),
                'saldo' => (int) round((float) $f->saldo_pendiente),
            ])->values()->all(),
        ];
    }

    private function facturaParaPago(Cliente $cliente, int $facturaId): ?FacturaInterna
    {
        $expr = FacturaInterna::sqlSaldoPendienteExpr();
        $query = FacturaInterna::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->whereNotIn('estado', ['anulada', 'cancelada'])
            ->whereRaw($expr.' > 0.009');

        if ($facturaId > 0) {
            return $query->where('id', $facturaId)->first();
        }

        return $query->orderBy('fecha_vencimiento')->orderBy('id')->first();
    }
}
