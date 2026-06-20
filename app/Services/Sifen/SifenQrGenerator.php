<?php

namespace App\Services\Sifen;

use App\Models\SifenConfiguracion;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use RuntimeException;

class SifenQrGenerator
{
    private const NS = 'http://ekuatia.set.gov.py/sifen/xsd';

    private const NS_DSIG = 'http://www.w3.org/2000/09/xmldsig#';

    /**
     * Construye el QR leyendo los mismos valores del XML firmado (evita desajustes).
     * Manual Técnico v150 §13.8.2–13.8.4.
     */
    public function construirUrlDesdeXmlFirmado(
        string $xml,
        ?SifenConfiguracion $config = null,
    ): string {
        $xml = SifenXmlManipulator::normalizarPrefijosFirma($xml);

        $dom = new DOMDocument;
        if (! $dom->loadXML($xml)) {
            throw new RuntimeException('XML inválido para generar QR.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', self::NS);

        $cdc = trim($xpath->evaluate('string(//s:DE/@Id)'));
        $dFeEmiDE = trim($xpath->evaluate('string(//s:dFeEmiDE)'));
        $dTotGralOpe = trim($xpath->evaluate('string(//s:dTotGralOpe)'));
        $dTotIVA = trim($xpath->evaluate('string(//s:dTotIVA)'));
        $cItems = $xpath->query('//s:DE//s:gCamItem')->length;
        $digestValueBase64 = trim($xpath->evaluate(
            'string(//*[local-name()="DigestValue" and namespace-uri()="'.self::NS_DSIG.'"])'
        ));

        $dRucRec = trim($xpath->evaluate('string(//s:dRucRec)'));
        $receptor = $dRucRec !== ''
            ? ['dRucRec' => $dRucRec]
            : ['dNumIDRec' => trim($xpath->evaluate('string(//s:dNumIDRec)'))];

        if ($cdc === '' || $dFeEmiDE === '' || $digestValueBase64 === '') {
            throw new RuntimeException('El XML firmado no contiene CDC, dFeEmiDE o DigestValue.');
        }

        return $this->construirUrl(
            $cdc,
            $dFeEmiDE,
            $receptor,
            $dTotGralOpe,
            $dTotIVA,
            $cItems,
            $digestValueBase64,
            $config,
        );
    }

    /**
     * @param  CarbonInterface|string  $fechaEmisionDe  Fecha exacta del XML (Y-m-d\TH:i:s)
     * @param  array<string, mixed>  $receptor
     */
    public function construirUrl(
        string $cdc,
        CarbonInterface|string $fechaEmisionDe,
        array $receptor,
        float|string $dTotGralOpe,
        float|string $dTotIVA,
        int $cItems,
        string $digestValueBase64,
        ?SifenConfiguracion $config = null,
    ): string {
        $config = $config ?: SifenConfiguracion::activa();
        $cscId = $config?->cscIdEfectivo() ?? str_pad((string) config('sifen.csc.id', '0001'), 4, '0', STR_PAD_LEFT);
        $cscToken = trim((string) ($config?->cscTokenEfectivo() ?? config('sifen.csc.token')));

        if ($cscToken === '') {
            throw new RuntimeException('Configure el CSC (código secreto del contribuyente) en sifen_configuracion o SIFEN_CSC_TOKEN.');
        }

        $fechaStr = $fechaEmisionDe instanceof CarbonInterface
            ? $fechaEmisionDe->format('Y-m-d\TH:i:s')
            : $fechaEmisionDe;

        // MT §13.8.3: dFeEmiDE y DigestValue en hexadecimal; totales en decimal.
        $fechaHex = bin2hex($fechaStr);
        $digestHex = bin2hex($digestValueBase64);

        $identificadorReceptor = ! empty($receptor['dRucRec'])
            ? 'dRucRec='.$receptor['dRucRec']
            : 'dNumIDRec='.($receptor['dNumIDRec'] ?? '');

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

        return $this->urlBaseQr().$paso1.'&cHashQR='.$hash;
    }

    /**
     * Inserta gCamFuFD después de Signature sin re-serializar el DOM (MT §13.8 / campo J002).
     */
    public function insertarEnXml(string $xml, string $qrUrl): string
    {
        $xml = preg_replace('/<gCamFuFD\b[^>]*>.*?<\/gCamFuFD>/s', '', $xml) ?? $xml;

        $bloque = '<gCamFuFD><dCarQR>'
            .SifenXmlManipulator::escaparTextoXml($qrUrl)
            .'</dCarQR></gCamFuFD>';

        return SifenXmlManipulator::insertarDespuesDeFirma($xml, $bloque);
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

    private function entero(float|string $valor): string
    {
        if (is_string($valor) && $valor !== '' && ctype_digit($valor)) {
            return $valor;
        }

        return (string) (int) round((float) $valor);
    }
}
