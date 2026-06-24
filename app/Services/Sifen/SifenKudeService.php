<?php

namespace App\Services\Sifen;

use App\Models\AjustesGenerales;
use App\Models\Factura;
use App\Models\SifenConfiguracion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class SifenKudeService
{
    public function __construct(
        private SifenQrGenerator $qrGenerator,
    ) {}

    public function generar(Factura $factura, SifenConfiguracion $config, ?string $qrUrl = null): string
    {
        $vista = $this->datosParaVista($factura, $config, $qrUrl);

        $pdf = Pdf::loadView('facturas.kude-pdf', $vista)
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true);

        $directorio = config('sifen.paths.pdf');
        File::ensureDirectoryExists($directorio);

        $nombre = sprintf(
            'KuDE_%s_%s.pdf',
            $factura->set_cdc ?: $factura->id,
            now()->format('YmdHis')
        );

        $rutaAbsoluta = $directorio.DIRECTORY_SEPARATOR.$nombre;
        File::put($rutaAbsoluta, $pdf->output());

        return 'sifen/pdf/'.$nombre;
    }

    /**
     * @return array<string, mixed>
     */
    public function datosParaVista(Factura $factura, SifenConfiguracion $config, ?string $qrUrl = null): array
    {
        $factura->loadMissing(['cliente', 'detalles.impuesto', 'usuario']);

        $qrUrl = $qrUrl ?: $factura->set_qr_url;
        $qrImageUrl = $qrUrl ? $this->qrGenerator->urlImagenQr($qrUrl) : null;

        $ajustes = AjustesGenerales::obtener();
        $logoBase64 = null;
        if ($ajustes?->logo && File::exists(public_path('storage/'.$ajustes->logo))) {
            $mime = mime_content_type(public_path('storage/'.$ajustes->logo)) ?: 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(
                File::get(public_path('storage/'.$ajustes->logo))
            );
        }

        $fantasia = trim((string) ($config->nombre_fantasia ?? ''));

        return array_merge($this->construirDatosVista($factura, $config, $ajustes), [
            'factura' => $factura,
            'config' => $config,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
            'qrImageUrl' => $qrImageUrl,
            'cdcFormateado' => $this->formatearCdc($factura->set_cdc),
            'nombreEmisor' => $fantasia !== '' ? $fantasia : trim((string) $config->razon_social),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function construirDatosVista(Factura $factura, SifenConfiguracion $config, ?AjustesGenerales $ajustes): array
    {
        $lineas = [];
        $sumExentas = 0.0;
        $sumGrav5 = 0.0;
        $sumGrav10 = 0.0;
        $iva5 = 0.0;
        $iva10 = 0.0;

        foreach ($factura->detalles as $index => $detalle) {
            $porcentaje = (int) round((float) $detalle->porcentaje_impuesto);
            $importeLinea = round((float) $detalle->cantidad * (float) $detalle->precio_unitario, 2);
            $exentas = $porcentaje === 0 ? $importeLinea : 0.0;
            $grav5 = $porcentaje === 5 ? $importeLinea : 0.0;
            $grav10 = $porcentaje === 10 ? $importeLinea : 0.0;

            $sumExentas += $exentas;
            $sumGrav5 += $grav5;
            $sumGrav10 += $grav10;

            if ($porcentaje === 5) {
                $iva5 += (float) $detalle->monto_impuesto;
            } elseif ($porcentaje === 10) {
                $iva10 += (float) $detalle->monto_impuesto;
            }

            $lineas[] = [
                'codigo' => (string) ($detalle->servicio_id ?: ($index + 1)),
                'descripcion' => $detalle->descripcion,
                'unidad' => 'UNI',
                'cantidad' => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'importe' => $importeLinea,
                'descuento' => 0.0,
                'anticipo' => 0.0,
                'exentas' => $exentas,
                'grav5' => $grav5,
                'grav10' => $grav10,
            ];
        }

        $filasVacias = max(0, 10 - count($lineas));

        $fechaEmision = $factura->fechaEmisionDeEfectiva();
        $ambiente = config('sifen.ambiente', 'test');

        return [
            'lineas' => $lineas,
            'filasVacias' => $filasVacias,
            'sumExentas' => $sumExentas,
            'sumGrav5' => $sumGrav5,
            'sumGrav10' => $sumGrav10,
            'iva5' => $iva5,
            'iva10' => $iva10,
            'totalIva' => $iva5 + $iva10,
            'fechaEmisionFmt' => $fechaEmision->format('d-m-Y H:i:s'),
            'vigenciaTimbradoFmt' => $factura->timbrado_vigencia_desde?->format('d-m-Y')
                ?? $config->timbrado_vigencia_desde?->format('d-m-Y')
                ?? '—',
            'condicionVenta' => $factura->tipo_documento === 'factura_credito' ? 'Crédito' : 'Contado',
            'tipoTransaccion' => 'Prestación de servicios',
            'monedaDescripcion' => $factura->moneda === 'USD' ? 'Dólar' : 'Guarani',
            'direccionEmisor' => trim($config->direccion.($config->ciudad_descripcion ? ', '.$config->ciudad_descripcion : '')),
            'consultaUrl' => $ambiente === 'production'
                ? 'https://ekuatia.set.gov.py/consultas'
                : 'https://ekuatia.set.gov.py/consultas-test',
            'vendedor' => $factura->usuario?->name,
            'sitioWeb' => $ajustes?->sitio_web,
            'barcodeUrl' => $factura->set_cdc
                ? 'https://barcode.tec-it.com/barcode.ashx?data='.urlencode($factura->set_cdc).'&code=Code128&dpi=96'
                : null,
        ];
    }

    public function formatearCdc(?string $cdc): ?string
    {
        if (! $cdc || strlen($cdc) !== 44) {
            return $cdc;
        }

        return trim(chunk_split($cdc, 4, ' '));
    }
}
