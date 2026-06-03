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
        $factura->loadMissing(['cliente', 'detalles']);

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

        $pdf = Pdf::loadView('facturas.kude-pdf', [
            'factura' => $factura,
            'config' => $config,
            'ajustes' => $ajustes,
            'logoBase64' => $logoBase64,
            'qrImageUrl' => $qrImageUrl,
            'cdcFormateado' => $this->formatearCdc($factura->set_cdc),
        ])
            ->setPaper('a4')
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

    public function formatearCdc(?string $cdc): ?string
    {
        if (! $cdc || strlen($cdc) !== 44) {
            return $cdc;
        }

        return trim(chunk_split($cdc, 4, ' '));
    }
}
