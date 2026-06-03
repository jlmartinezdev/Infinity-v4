<?php

namespace App\Services\Sifen;

use App\Models\SifenConfiguracion;
use Carbon\CarbonInterface;
use DOMDocument;
use RuntimeException;

class SifenQrGenerator
{
    private const NS = 'http://ekuatia.set.gov.py/sifen/xsd';

    /**
     * @param  array<string, mixed>  $receptor
     */
    public function construirUrl(
        string $cdc,
        CarbonInterface $fechaEmisionDe,
        array $receptor,
        float $dTotGralOpe,
        float $dTotIVA,
        int $cItems,
        string $digestValueBase64,
        ?SifenConfiguracion $config = null,
    ): string {
        $config = $config ?: SifenConfiguracion::activa();
        $cscId = $config?->cscIdEfectivo() ?? config('sifen.csc.id', '0001');
        $cscToken = $config?->cscTokenEfectivo() ?? config('sifen.csc.token');

        if (blank($cscToken)) {
            throw new RuntimeException('Configure el CSC (código secreto del contribuyente) en sifen_configuracion o SIFEN_CSC_TOKEN.');
        }

        $fechaHex = bin2hex($fechaEmisionDe->format('Y-m-d\TH:i:s'));
        $digestHex = bin2hex($digestValueBase64);

        $identificadorReceptor = $receptor['dRucRec']
            ? 'dRucRec='.$receptor['dRucRec']
            : 'dNumIdRec='.$receptor['dNumIDRec'];

        $paso1 = sprintf(
            'nVersion=%d&Id=%s&dFeEmiDE=%s&%s&dTotGralOpe=%s&dTotIVA=%s&cItems=%d&DigestValue=%s&IdCSC=%s',
            (int) config('sifen.version_formato', 150),
            $cdc,
            $fechaHex,
            $identificadorReceptor,
            $this->entero($dTotGralOpe),
            $this->entero($dTotIVA),
            $cItems,
            $digestHex,
            $cscId,
        );

        $hash = hash('sha256', $paso1.$cscToken);
        $baseUrl = $this->urlBaseQr();

        return $baseUrl.$paso1.'&cHashQR='.$hash;
    }

    public function insertarEnXml(string $xml, string $qrUrl): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido para insertar QR.');
        }

        $root = $dom->documentElement;
        $existente = $dom->getElementsByTagName('gCamFuFD')->item(0);
        if ($existente) {
            $root->removeChild($existente);
        }

        $gCamFuFD = $dom->createElementNS(self::NS, 'gCamFuFD');
        $dCarQR = $dom->createElementNS(self::NS, 'dCarQR', str_replace('&', '&amp;', $qrUrl));
        $gCamFuFD->appendChild($dCarQR);
        $root->appendChild($gCamFuFD);

        return $dom->saveXML();
    }

    public function urlImagenQr(string $qrUrl, int $size = 150): string
    {
        return 'https://quickchart.io/qr?size='.$size.'&margin=1&text='.rawurlencode($qrUrl);
    }

    private function urlBaseQr(): string
    {
        $ambiente = config('sifen.ambiente', 'test');

        return config("sifen.ws.{$ambiente}.qr_base").'?';
    }

    private function entero(float $valor): string
    {
        return (string) (int) round($valor);
    }
}
