<?php

namespace App\Services\WhatsApp;

use App\Models\Cliente;
use App\Models\FacturaInterna;
use App\Models\WhatsappMensaje;
use App\Support\FacturaReclamoMensaje;
use App\Support\PendientesResumenPublico;
use Illuminate\Support\Collection;

/**
 * Estado e historial WhatsApp para el listado Pendiente de pago.
 */
class PendientesWhatsAppService
{
    public function __construct(
        private readonly WhatsAppService $whatsapp,
        private readonly WhatsAppPhoneNormalizer $phones,
    ) {}

    /**
     * Último aviso por factura y último reclamo por cliente (página actual).
     *
     * @param  list<int>  $facturaIds
     * @param  list<int>  $clienteIds
     * @return array{0: Collection<int, WhatsappMensaje>, 1: Collection<int, WhatsappMensaje>}
     */
    public function ultimosPorPagina(array $facturaIds, array $clienteIds): array
    {
        $avisos = collect();
        if ($facturaIds !== []) {
            $avisos = WhatsappMensaje::query()
                ->where('contexto_tipo', 'factura')
                ->whereIn('contexto_id', $facturaIds)
                ->orderByDesc('id')
                ->get(['id', 'contexto_id', 'cliente_id', 'estado', 'error_code', 'error_message', 'created_at', 'telefono', 'cuerpo', 'template_name'])
                ->unique('contexto_id')
                ->keyBy(fn (WhatsappMensaje $m) => (int) $m->contexto_id);
        }

        $reclamos = collect();
        if ($clienteIds !== []) {
            $reclamos = WhatsappMensaje::query()
                ->where('contexto_tipo', 'factura_reclamo')
                ->whereIn('cliente_id', $clienteIds)
                ->orderByDesc('id')
                ->get(['id', 'contexto_id', 'cliente_id', 'estado', 'error_code', 'error_message', 'created_at', 'telefono', 'cuerpo', 'template_name'])
                ->unique('cliente_id')
                ->keyBy(fn (WhatsappMensaje $m) => (int) $m->cliente_id);
        }

        return [$avisos, $reclamos];
    }

    /**
     * Resumen compacto para una fila agrupada por cliente.
     *
     * @param  Collection<int, FacturaInterna>  $facturas
     * @param  Collection<int, WhatsappMensaje>  $avisosPorFactura
     * @param  Collection<int, WhatsappMensaje>  $reclamosPorCliente
     * @return array<string, mixed>
     */
    public function resumenFila(
        Collection $facturas,
        ?Cliente $cliente,
        Collection $avisosPorFactura,
        Collection $reclamosPorCliente,
    ): array {
        $hoy = now()->startOfDay();
        $tieneVencida = $facturas->contains(
            fn (FacturaInterna $f) => $f->fecha_vencimiento && $f->fecha_vencimiento->lt($hoy)
        );

        $aviso = null;
        foreach ($facturas as $f) {
            $m = $avisosPorFactura->get((int) $f->id);
            if ($m && ($aviso === null || (int) $m->id > (int) $aviso->id)) {
                $aviso = $m;
            }
        }

        $clienteId = (int) ($cliente?->cliente_id ?? 0);
        $reclamo = $clienteId > 0 ? $reclamosPorCliente->get($clienteId) : null;
        $tieneTelefono = filled($cliente?->telefono);
        $modo = $tieneVencida ? 'reclamo' : 'aviso';
        $msg = $modo === 'reclamo' ? $reclamo : $aviso;
        $estado = $msg?->estado ?? 'sin_envio';

        return [
            'modo' => $modo,
            'estado' => $estado,
            'icono' => self::iconoDesdeEstado($estado),
            'titulo' => self::titulo($modo, $estado, $msg, $tieneTelefono),
            'tiene_vencida' => $tieneVencida,
            'tiene_telefono' => $tieneTelefono,
            'aviso_estado' => $aviso?->estado ?? 'sin_envio',
            'reclamo_estado' => $reclamo?->estado ?? 'sin_envio',
        ];
    }

    /**
     * Detalle para el modal (sin abandonar Pendiente de pago).
     *
     * @return array<string, mixed>
     */
    public function detalle(Cliente $cliente): array
    {
        $facturas = $this->facturasPendientesDe($cliente);
        $hoy = now()->startOfDay();
        $facturaIds = $facturas->pluck('id')->map(fn ($id) => (int) $id)->all();

        [$avisos, $reclamos] = $this->ultimosPorPagina($facturaIds, [(int) $cliente->cliente_id]);
        $reclamo = $reclamos->get((int) $cliente->cliente_id);

        $aviso = null;
        foreach ($avisos as $m) {
            if ($aviso === null || (int) $m->id > (int) $aviso->id) {
                $aviso = $m;
            }
        }

        $saldoVencido = 0.0;
        $saldoVigente = 0.0;
        foreach ($facturas as $f) {
            $saldo = (float) $f->saldo_pendiente;
            if ($f->fecha_vencimiento && $f->fecha_vencimiento->lt($hoy)) {
                $saldoVencido += $saldo;
            } else {
                $saldoVigente += $saldo;
            }
        }

        $tieneVencida = $saldoVencido > 0.00001;
        $historial = $this->historial($cliente, $facturaIds);

        $reclamoHoy = WhatsappMensaje::query()
            ->where('contexto_tipo', 'factura_reclamo')
            ->where('cliente_id', $cliente->cliente_id)
            ->where('created_at', '>=', now()->startOfDay())
            ->whereIn('estado', [
                WhatsappMensaje::ESTADO_PENDIENTE,
                WhatsappMensaje::ESTADO_ENVIADO,
                WhatsappMensaje::ESTADO_ENTREGADO,
                WhatsappMensaje::ESTADO_LEIDO,
            ])
            ->exists();

        $nombre = trim(($cliente->nombre ?? '').' '.($cliente->apellido ?? ''));
        $saludo = $nombre !== '' ? $nombre : 'cliente';
        $vencidas = $facturas->filter(
            fn (FacturaInterna $f) => $f->fecha_vencimiento && $f->fecha_vencimiento->lt($hoy)
        )->values();
        $cantidad = $vencidas->count();
        $primeraVencida = $vencidas->first();
        $vencimiento = $primeraVencida?->fecha_vencimiento?->format('d/m/Y') ?? '—';
        $saldoFmt = number_format($saldoVencido > 0 ? $saldoVencido : (float) $facturas->sum(fn (FacturaInterna $f) => (float) $f->saldo_pendiente), 0, ',', '.');

        $previewReclamo = FacturaReclamoMensaje::cuerpo(
            $saludo,
            max(1, $cantidad),
            $vencimiento,
            $saldoFmt,
            PendientesResumenPublico::url((int) $cliente->cliente_id),
        );

        return [
            'cliente' => [
                'id' => (int) $cliente->cliente_id,
                'nombre' => $nombre,
                'telefono' => (string) ($cliente->telefono ?? ''),
            ],
            'tiene_vencida' => $tieneVencida,
            'saldo_vencido' => $saldoVencido,
            'saldo_vigente' => $saldoVigente,
            'saldo_total' => $saldoVencido + $saldoVigente,
            'pdf_url' => PendientesResumenPublico::url((int) $cliente->cliente_id),
            'plantilla_reclamo' => trim((string) config('whatsapp.templates.factura_reclamo', '')),
            'reclamo_hoy' => $reclamoHoy,
            'preview_reclamo' => $previewReclamo,
            'aviso' => $this->serializar($aviso),
            'reclamo' => $this->serializar($reclamo),
            'facturas' => $facturas->map(function (FacturaInterna $f) use ($hoy, $avisos) {
                $avisoF = $avisos->get((int) $f->id);
                $vencida = $f->fecha_vencimiento && $f->fecha_vencimiento->lt($hoy);

                return [
                    'id' => (int) $f->id,
                    'periodo_desde' => $f->periodo_desde?->format('Y-m-d'),
                    'periodo_hasta' => $f->periodo_hasta?->format('Y-m-d'),
                    'fecha_vencimiento' => $f->fecha_vencimiento?->format('Y-m-d'),
                    'saldo_pendiente' => (float) $f->saldo_pendiente,
                    'moneda' => $f->moneda ?? 'PYG',
                    'vencida' => $vencida,
                    'aviso' => $this->serializar($avisoF),
                ];
            })->values()->all(),
            'historial' => $historial,
        ];
    }

    /**
     * Conversación reciente con el cliente (entrada + salida), no solo avisos automáticos.
     *
     * @param  list<int>  $facturaIds
     * @return list<array<string, mixed>>
     */
    public function historial(Cliente $cliente, array $facturaIds = []): array
    {
        $tels = $this->phones->variants($cliente->telefono);

        $rows = WhatsappMensaje::query()
            ->where(function ($q) use ($cliente, $tels) {
                $q->where('cliente_id', $cliente->cliente_id);
                if ($tels !== []) {
                    $q->orWhereIn('telefono', $tels);
                }
            })
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->reverse()
            ->values();

        return $rows->map(fn (WhatsappMensaje $m) => $this->serializar($m))->all();
    }

    /**
     * @return Collection<int, FacturaInterna>
     */
    public function facturasPendientesDe(Cliente $cliente): Collection
    {
        return FacturaInterna::query()
            ->where('factura_internas.cliente_id', $cliente->cliente_id)
            ->whereIn('factura_internas.estado', ['pendiente', 'emitida'])
            ->whereRaw('factura_internas.total > COALESCE((SELECT SUM(monto) FROM cobro_factura_interna WHERE factura_interna_id = factura_internas.id), 0)')
            ->orderByRaw('factura_internas.fecha_vencimiento IS NULL ASC')
            ->orderBy('factura_internas.fecha_vencimiento')
            ->orderBy('factura_internas.id')
            ->get();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serializar(?WhatsappMensaje $m): ?array
    {
        if (! $m) {
            return null;
        }

        $fallo = $m->esFallido() ? $m->detalleFallo() : null;
        $created = $m->created_at?->timezone(config('app.timezone'));

        return [
            'id' => (int) $m->id,
            'direccion' => $m->direccion ?: WhatsappMensaje::DIRECCION_SALIDA,
            'tipo' => $m->tipo,
            'contexto_tipo' => $m->contexto_tipo,
            'contexto_id' => $m->contexto_id ? (int) $m->contexto_id : null,
            'estado' => $m->estado,
            'icono' => self::iconoDesdeEstado((string) $m->estado),
            'telefono' => $m->telefono,
            'cuerpo' => $this->whatsapp->cuerpoVisibleMensaje($m),
            'template_name' => $m->template_name,
            'error_code' => $m->error_code,
            'error_message' => $m->error_message,
            'fallo' => $fallo,
            'created_at' => $created?->format('d/m/Y H:i'),
            'created_at_iso' => $m->created_at?->toIso8601String(),
            'hora' => $created?->format('H:i'),
            'dia' => $created?->format('Y-m-d'),
            'dia_label' => $created?->translatedFormat('d M Y'),
        ];
    }

    public static function iconoDesdeEstado(string $estado): string
    {
        return match ($estado) {
            WhatsappMensaje::ESTADO_LEIDO, WhatsappMensaje::ESTADO_ENTREGADO => 'ok',
            WhatsappMensaje::ESTADO_ENVIADO, WhatsappMensaje::ESTADO_PENDIENTE => 'pendiente',
            default => 'alerta',
        };
    }

    public static function titulo(string $modo, string $estado, ?WhatsappMensaje $m, bool $tieneTelefono): string
    {
        $ambito = $modo === 'reclamo' ? 'reclamo de mora' : 'aviso de factura';
        if (! $tieneTelefono && in_array($estado, ['sin_envio', 'sin_telefono'], true)) {
            return 'Sin teléfono — no se puede enviar WhatsApp';
        }

        return match ($estado) {
            WhatsappMensaje::ESTADO_LEIDO => 'WhatsApp leído ('.$ambito.')',
            WhatsappMensaje::ESTADO_ENTREGADO => 'WhatsApp entregado ('.$ambito.')',
            WhatsappMensaje::ESTADO_ENVIADO => 'WhatsApp enviado ('.$ambito.')',
            WhatsappMensaje::ESTADO_PENDIENTE => 'WhatsApp pendiente ('.$ambito.')',
            WhatsappMensaje::ESTADO_FALLIDO => self::tituloFallo($ambito, $m),
            default => $modo === 'reclamo' ? 'Reclamo de mora no enviado' : 'Aviso de factura no enviado',
        };
    }

    private static function tituloFallo(string $ambito, ?WhatsappMensaje $m): string
    {
        $base = 'WhatsApp fallido ('.$ambito.')';
        if ($m?->error_code) {
            $base .= ' · '.$m->error_code;
        }
        if (filled($m?->error_message)) {
            $base .= ': '.mb_substr((string) $m->error_message, 0, 80);
        }

        return $base;
    }
}
