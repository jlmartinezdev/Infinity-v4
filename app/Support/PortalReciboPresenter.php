<?php

namespace App\Support;

use App\Models\AjustesGenerales;
use App\Models\Cobro;
use App\Models\Servicio;
use Illuminate\Support\Str;

/**
 * Payload del recibo térmico (modo con_grafico) para la app Interplus Clientes.
 * La app recorre data.layout y pinta; no hace falta hardcodear el orden de bloques.
 */
class PortalReciboPresenter
{
    public function __construct(
        private ?AjustesGenerales $ajustes = null,
    ) {
        $this->ajustes ??= AjustesGenerales::obtener();
    }

    /**
     * @return array<string, mixed>
     */
    public function estilo(): array
    {
        return [
            'version' => 1,
            'modo' => 'con_grafico',
            'fondo' => '#FFFFFF',
            'texto' => '#111827',
            'texto_muted' => '#6B7280',
            'linea' => '#9CA3AF',
            'borde_factura' => '#D1D5DB',
            'ancho_dp' => 320,
            'padding_dp' => 16,
            'logo_alto_dp' => 40,
            'texto_sp' => 12,
            'titulo_sp' => 16,
            'total_sp' => 16,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function empresa(): array
    {
        $a = $this->ajustes;
        $nombre = $this->ascii($a?->nombre_empresa ?: config('app.name'));
        $logoUrl = $a?->urlLogo();

        return [
            'nombre' => $nombre,
            'direccion' => $this->ascii($a?->direccion) ?: null,
            'telefono' => $this->ascii($a?->telefono) ?: null,
            'email' => $this->ascii($a?->email) ?: null,
            'sitio_web' => $this->ascii($a?->sitio_web) ?: null,
            'logo_url' => $logoUrl ?: null,
        ];
    }

    /**
     * Ítem de listado (historial de pagos).
     *
     * @return array<string, mixed>
     */
    public function listadoItem(Cobro $cobro): array
    {
        $monto = (float) $cobro->monto;

        return [
            'id' => (int) $cobro->id,
            'cliente_id' => (int) $cobro->cliente_id,
            'numero_recibo' => (string) ($cobro->numero_recibo ?? ''),
            'fecha_pago' => optional($cobro->fecha_pago)?->toIso8601String(),
            'fecha_pago_formato' => optional($cobro->fecha_pago)?->format('d/m/Y H:i'),
            'monto' => $monto,
            'monto_formato' => $this->pyg($monto),
            'forma_pago' => (string) ($cobro->forma_pago ?? ''),
            'forma_pago_label' => $this->formaPagoLabel($cobro),
            'concepto' => $this->ascii($cobro->concepto) ?: null,
            'referencia' => $this->ascii($cobro->referencia) ?: null,
        ];
    }

    /**
     * Recibo completo para pintar / compartir.
     *
     * @return array<string, mixed>
     */
    public function detalle(Cobro $cobro): array
    {
        $cobro->loadMissing(['cliente.servicios', 'facturaInternas.detalles.servicio', 'usuario']);

        $cliente = $cobro->cliente;
        $empresa = $this->empresa();
        $saldoFavor = $this->saldoAFavor($cobro);
        $facturas = $this->facturas($cobro);
        $recibo = [
            'id' => (int) $cobro->id,
            'numero_recibo' => (string) ($cobro->numero_recibo ?? ''),
            'fecha_pago' => optional($cobro->fecha_pago)?->toIso8601String(),
            'fecha_pago_formato' => optional($cobro->fecha_pago)?->format('d/m/Y H:i'),
            'monto' => (float) $cobro->monto,
            'monto_formato' => $this->pyg((float) $cobro->monto),
            'forma_pago' => (string) ($cobro->forma_pago ?? ''),
            'forma_pago_label' => $this->formaPagoLabel($cobro),
            'referencia' => $this->ascii($cobro->referencia) ?: null,
            'concepto' => $this->ascii($cobro->concepto) ?: null,
            'observaciones' => $this->ascii($cobro->observaciones) ?: null,
            'cajero' => $this->ascii($cobro->usuario?->name) ?: '—',
            'saldo_a_favor' => $saldoFavor,
            'saldo_a_favor_formato' => $saldoFavor > 0 ? $this->pyg($saldoFavor) : null,
        ];

        return [
            'estilo' => $this->estilo(),
            'empresa' => $empresa,
            'cliente' => [
                'cliente_id' => (int) ($cliente?->cliente_id ?? $cobro->cliente_id),
                'nombre' => $this->ascii($cliente?->nombre),
                'apellido' => $this->ascii($cliente?->apellido),
                'nombre_completo' => trim($this->ascii($cliente?->nombre).' '.$this->ascii($cliente?->apellido)),
                'cedula' => $this->ascii($cliente?->cedula) ?: '—',
                'direccion' => $this->ascii($cliente?->direccion) ?: null,
            ],
            'recibo' => $recibo,
            'facturas' => $facturas,
            'layout' => $this->layout($empresa, $recibo, $facturas, $cliente),
            'compartir_texto' => $this->textoPlano($empresa, $recibo, $facturas, $cliente),
            'pdf_url' => $cobro->urlPublicaPdf(),
            'archivo_nombre' => 'recibo-'.preg_replace('/[^a-zA-Z0-9\-_.]+/', '_', (string) $cobro->numero_recibo).'.png',
        ];
    }

    /**
     * @param  array<string, mixed>  $empresa
     * @param  array<string, mixed>  $recibo
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array<string, mixed>>
     */
    private function layout(array $empresa, array $recibo, array $facturas, mixed $cliente): array
    {
        $bloques = [];

        if (! empty($empresa['logo_url'])) {
            $bloques[] = ['tipo' => 'logo', 'url' => $empresa['logo_url']];
        } else {
            $bloques[] = ['tipo' => 'titulo', 'texto' => mb_strtoupper((string) $empresa['nombre']), 'align' => 'center'];
        }

        $contacto = array_values(array_filter([
            $empresa['direccion'] ?? null,
            ! empty($empresa['telefono']) ? 'TEL: '.$empresa['telefono'] : null,
            $empresa['email'] ?? null,
            $empresa['sitio_web'] ?? null,
        ]));
        if ($contacto !== []) {
            $bloques[] = ['tipo' => 'contacto', 'lineas' => $contacto, 'align' => 'center'];
        }

        $bloques[] = ['tipo' => 'separador'];
        $bloques[] = ['tipo' => 'texto', 'texto' => (string) $recibo['fecha_pago_formato']];
        $bloques[] = ['tipo' => 'fila', 'izq' => 'RECIBO: #'.$recibo['numero_recibo'], 'der' => null];
        $bloques[] = ['tipo' => 'separador'];

        $nombre = trim((string) ($this->ascii($cliente?->nombre).' '.$this->ascii($cliente?->apellido)));
        $bloques[] = ['tipo' => 'texto', 'texto' => 'CLIENTE: '.$nombre, 'bold' => true];
        $bloques[] = ['tipo' => 'texto', 'texto' => 'CEDULA: '.($this->ascii($cliente?->cedula) ?: '—')];
        if (! empty($cliente?->direccion)) {
            $bloques[] = [
                'tipo' => 'fila',
                'izq' => 'DIRECCION:',
                'der' => $this->ascii($cliente->direccion),
            ];
        }

        if ($facturas !== []) {
            $bloques[] = [
                'tipo' => 'texto',
                'texto' => count($facturas) > 1 ? 'FACTURAS INTERNAS:' : 'FACTURA INTERNA:',
                'bold' => true,
            ];
            foreach ($facturas as $fi) {
                $bloques[] = [
                    'tipo' => 'factura',
                    'id' => $fi['id'],
                    'izq' => '#'.$fi['id'].(! empty($fi['alias']) ? ' · '.$fi['alias'] : ''),
                    'der' => $fi['monto_formato'],
                    'periodo' => $fi['periodo'],
                    'alias' => $fi['alias'] ?? null,
                ];
            }
        }

        if (! empty($recibo['concepto'])) {
            $bloques[] = ['tipo' => 'texto', 'texto' => (string) $recibo['concepto']];
        }

        $bloques[] = ['tipo' => 'separador'];
        $bloques[] = [
            'tipo' => 'fila',
            'izq' => 'TOTAL:',
            'der' => $recibo['monto_formato'],
            'destacado' => true,
            'bold' => true,
        ];
        if (! empty($recibo['saldo_a_favor_formato'])) {
            $bloques[] = [
                'tipo' => 'fila',
                'izq' => 'SALDO A FAVOR:',
                'der' => $recibo['saldo_a_favor_formato'],
                'bold' => true,
            ];
        }

        $bloques[] = ['tipo' => 'separador'];
        $bloques[] = ['tipo' => 'fila', 'izq' => 'FORMA DE PAGO:', 'der' => $recibo['forma_pago_label']];
        if (! empty($recibo['referencia'])) {
            $bloques[] = ['tipo' => 'fila', 'izq' => 'REF:', 'der' => $recibo['referencia']];
        }
        $bloques[] = ['tipo' => 'fila', 'izq' => 'CAJERO:', 'der' => $recibo['cajero']];

        if (! empty($recibo['observaciones'])) {
            $bloques[] = ['tipo' => 'texto', 'texto' => 'OBS:', 'bold' => true];
            $bloques[] = ['tipo' => 'texto', 'texto' => (string) $recibo['observaciones']];
        }

        $bloques[] = ['tipo' => 'separador'];
        $bloques[] = [
            'tipo' => 'pie',
            'lineas' => ['GRACIAS POR SU PAGO', 'VALIDO COMO COMPROBANTE'],
            'numero' => '#'.$recibo['numero_recibo'],
            'align' => 'center',
        ];

        return $bloques;
    }

    /**
     * @param  array<string, mixed>  $empresa
     * @param  array<string, mixed>  $recibo
     * @param  list<array<string, mixed>>  $facturas
     */
    private function textoPlano(array $empresa, array $recibo, array $facturas, mixed $cliente): string
    {
        $l = [];
        $l[] = mb_strtoupper((string) $empresa['nombre']);
        foreach ([$empresa['direccion'] ?? null, ! empty($empresa['telefono']) ? 'TEL: '.$empresa['telefono'] : null, $empresa['email'] ?? null, $empresa['sitio_web'] ?? null] as $c) {
            if ($c) {
                $l[] = $c;
            }
        }
        $l[] = '---------------------';
        $l[] = (string) $recibo['fecha_pago_formato'];
        $l[] = 'RECIBO: #'.$recibo['numero_recibo'];
        $l[] = '---------------------';
        $l[] = 'CLIENTE: '.trim($this->ascii($cliente?->nombre).' '.$this->ascii($cliente?->apellido));
        $l[] = 'CEDULA: '.($this->ascii($cliente?->cedula) ?: '—');
        if (! empty($cliente?->direccion)) {
            $l[] = 'DIRECCION: '.$this->ascii($cliente->direccion);
        }
        if ($facturas !== []) {
            $l[] = count($facturas) > 1 ? 'FACTURAS INTERNAS:' : 'FACTURA INTERNA:';
            foreach ($facturas as $fi) {
                $l[] = '#'.$fi['id'].(! empty($fi['alias']) ? ' · '.$fi['alias'] : '').' '.$fi['monto_formato'];
                if (! empty($fi['periodo'])) {
                    $l[] = $fi['periodo'];
                }
            }
        }
        if (! empty($recibo['concepto'])) {
            $l[] = (string) $recibo['concepto'];
        }
        $l[] = '---------------------';
        $l[] = 'TOTAL: '.$recibo['monto_formato'];
        if (! empty($recibo['saldo_a_favor_formato'])) {
            $l[] = 'SALDO A FAVOR: '.$recibo['saldo_a_favor_formato'];
        }
        $l[] = 'FORMA DE PAGO: '.$recibo['forma_pago_label'];
        if (! empty($recibo['referencia'])) {
            $l[] = 'REF: '.$recibo['referencia'];
        }
        $l[] = 'CAJERO: '.$recibo['cajero'];
        if (! empty($recibo['observaciones'])) {
            $l[] = 'OBS: '.$recibo['observaciones'];
        }
        $l[] = '---------------------';
        $l[] = 'GRACIAS POR SU PAGO';
        $l[] = 'VALIDO COMO COMPROBANTE';
        $l[] = '#'.$recibo['numero_recibo'];

        return implode("\n", $l);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function facturas(Cobro $cobro): array
    {
        return $cobro->facturaInternas
            ->map(function ($fi) {
                $monto = (float) ($fi->pivot->monto ?? $fi->total);
                $desde = optional($fi->periodo_desde)?->format('d/m/Y');
                $hasta = optional($fi->periodo_hasta)?->format('d/m/Y');
                $alias = $fi->aliasesServicioTexto();
                $periodo = ($desde || $hasta) ? 'PERIODO: '.($desde ?: '—').' - '.($hasta ?: '—') : null;

                return [
                    'id' => (int) $fi->id,
                    'monto' => $monto,
                    'monto_formato' => $this->pyg($monto),
                    'periodo_desde' => $desde,
                    'periodo_hasta' => $hasta,
                    'periodo' => $periodo,
                    'alias' => $alias,
                ];
            })
            ->values()
            ->all();
    }

    private function saldoAFavor(Cobro $cobro): float
    {
        $cliente = $cobro->cliente;
        if (! $cliente) {
            return 0.0;
        }
        if ($cliente->relationLoaded('servicios')) {
            return (float) $cliente->servicios->sum(fn ($s) => (float) ($s->saldo_a_favor ?? 0));
        }

        return (float) Servicio::query()
            ->where('cliente_id', $cliente->cliente_id)
            ->sum('saldo_a_favor');
    }

    private function formaPagoLabel(Cobro $cobro): string
    {
        $key = (string) ($cobro->forma_pago ?? '');
        $label = Cobro::formasPago()[$key] ?? $key;

        return $this->ascii($label) ?: $key;
    }

    private function pyg(float $n): string
    {
        return number_format($n, 0, ',', '.').' PYG';
    }

    private function ascii(?string $s): string
    {
        return trim(Str::ascii((string) $s));
    }
}
