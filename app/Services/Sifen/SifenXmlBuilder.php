<?php

namespace App\Services\Sifen;

use App\Models\Factura;
use App\Models\SifenConfiguracion;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMElement;

class SifenXmlBuilder
{
    private const NS = 'http://ekuatia.set.gov.py/sifen/xsd';

    private const NS_XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    public function __construct(
        private SifenIvaCalculator $ivaCalculator,
        private SifenReceptorParser $receptorParser,
    ) {}

    /**
     * @return array{xml: string, cdc: string, codigo_seguridad: int, items_calculados: array, totales: array}
     */
    public function construir(
        Factura $factura,
        SifenConfiguracion $config,
        string $cdc,
        int $codigoSeguridad,
        CarbonInterface $fechaEmisionDe,
    ): array {
        $factura->loadMissing(['cliente', 'detalles.impuesto']);

        $tipoDe = (int) config('sifen.tipos_documento.'.$factura->tipo_documento, 1);
        $descripcionTipo = config('sifen.descripciones_tipo_documento.'.$tipoDe, 'Factura electrónica');

        $itemsCalculados = [];
        foreach ($factura->detalles as $index => $detalle) {
            $porcentaje = $detalle->impuesto
                ? (float) $detalle->impuesto->porcentaje
                : (float) $detalle->porcentaje_impuesto;

            $itemsCalculados[$index] = array_merge(
                $this->ivaCalculator->calcularItem(
                    (float) $detalle->cantidad,
                    (float) $detalle->total,
                    $porcentaje,
                ),
                [
                    'dCodInt' => (string) ($detalle->servicio_id ?? ($index + 1)),
                    'dDesProSer' => $detalle->descripcion,
                    'dCantProSer' => (float) $detalle->cantidad,
                ]
            );
        }

        $totales = $this->ivaCalculator->calcularTotales($itemsCalculados);
        $receptor = $factura->esOcasional()
            ? $this->receptorParser->parseDesdeDocumento(
                (string) $factura->receptor_documento,
                $factura->receptorNombreCompleto(),
            )
            : $this->receptorParser->parse($factura->cliente);
        $condicionOperacion = $factura->tipo_documento === 'factura_credito' ? 2 : 1;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;

        $rDE = $dom->createElementNS(self::NS, 'rDE');
        $dom->appendChild($rDE);
        $rDE->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::NS_XSI);
        $rDE->setAttributeNS(self::NS_XSI, 'xsi:schemaLocation', self::NS.' siRecepDE_v150.xsd');

        $this->appendText($dom, $rDE, 'dVerFor', (string) config('sifen.version_formato', 150));

        $de = $dom->createElementNS(self::NS, 'DE');
        $de->setAttribute('Id', $cdc);
        $rDE->appendChild($de);

        $digitoCdc = substr($cdc, -1);
        $this->appendText($dom, $de, 'dDVId', $digitoCdc);
        $this->appendText($dom, $de, 'dFecFirma', $fechaEmisionDe->format('Y-m-d\TH:i:s'));
        $this->appendText($dom, $de, 'dSisFact', (string) config('sifen.defaults.sistema_facturacion', 1));

        $gOpeDE = $this->appendGroup($dom, $de, 'gOpeDE');
        $this->appendText($dom, $gOpeDE, 'iTipEmi', (string) config('sifen.defaults.tipo_emision', 1));
        $this->appendText($dom, $gOpeDE, 'dDesTipEmi', 'Normal');
        $this->appendText($dom, $gOpeDE, 'dCodSeg', str_pad((string) $codigoSeguridad, 9, '0', STR_PAD_LEFT));

        $gTimb = $this->appendGroup($dom, $de, 'gTimb');
        $this->appendText($dom, $gTimb, 'iTiDE', (string) $tipoDe);
        $this->appendText($dom, $gTimb, 'dDesTiDE', $descripcionTipo);
        $this->appendText($dom, $gTimb, 'dNumTim', $config->numero_timbrado);
        $this->appendText($dom, $gTimb, 'dEst', str_pad((string) $factura->establecimiento, 3, '0', STR_PAD_LEFT));
        $this->appendText($dom, $gTimb, 'dPunExp', str_pad((string) $factura->punto_emision, 3, '0', STR_PAD_LEFT));
        $numeroDoc = (int) ($factura->numero ?? 0);
        if ($numeroDoc < 1) {
            throw new \RuntimeException('El número de documento (dNumDoc) no puede ser cero. Prepare el DE antes de emitir.');
        }
        $this->appendText($dom, $gTimb, 'dNumDoc', str_pad((string) $numeroDoc, 7, '0', STR_PAD_LEFT));
        if ($factura->set_serie) {
            $this->appendText($dom, $gTimb, 'dSerieNum', $factura->set_serie);
        }
        $this->appendText($dom, $gTimb, 'dFeIniT', $config->timbrado_vigencia_desde->format('Y-m-d'));

        $gDatGralOpe = $this->appendGroup($dom, $de, 'gDatGralOpe');
        $this->appendText($dom, $gDatGralOpe, 'dFeEmiDE', $fechaEmisionDe->format('Y-m-d\TH:i:s'));

        if (in_array($tipoDe, [1, 4], true)) {
            $gOpeCom = $this->appendGroup($dom, $gDatGralOpe, 'gOpeCom');
            $tipoTra = (string) config('sifen.defaults.tipo_transaccion', 2);
            $this->appendText($dom, $gOpeCom, 'iTipTra', $tipoTra);
            $this->appendText($dom, $gOpeCom, 'dDesTipTra', 'Prestación de servicios');
            $this->appendText($dom, $gOpeCom, 'iTImp', '1');
            $this->appendText($dom, $gOpeCom, 'dDesTImp', 'IVA');
            $this->appendText($dom, $gOpeCom, 'cMoneOpe', $factura->moneda ?: 'PYG');
            $this->appendText($dom, $gOpeCom, 'dDesMoneOpe', $factura->moneda === 'USD' ? 'US Dollar' : 'Guarani');
            if ($factura->moneda !== 'PYG' && $factura->tipo_cambio) {
                $this->appendText($dom, $gOpeCom, 'dCondTiCam', '1');
                $this->appendText($dom, $gOpeCom, 'dTiCam', $this->formatNumero((float) $factura->tipo_cambio, 4));
            }
        }

        $this->appendEmisor($dom, $gDatGralOpe, $config);
        $this->appendReceptor($dom, $gDatGralOpe, $receptor, $factura->codigoClienteSifen());

        $gDtipDE = $this->appendGroup($dom, $de, 'gDtipDE');

        if ($tipoDe === 1) {
            $gCamFE = $this->appendGroup($dom, $gDtipDE, 'gCamFE');
            $indPres = (string) config('sifen.defaults.indicador_presencia', 2);
            $this->appendText($dom, $gCamFE, 'iIndPres', $indPres);
            $this->appendText($dom, $gCamFE, 'dDesIndPres', 'Operación electrónica');
        }

        $gCamCond = $this->appendGroup($dom, $gDtipDE, 'gCamCond');
        $this->appendText($dom, $gCamCond, 'iCondOpe', (string) $condicionOperacion);
        $this->appendText($dom, $gCamCond, 'dDCondOpe', $condicionOperacion === 2 ? 'Crédito' : 'Contado');

        if ($condicionOperacion === 1) {
            $gPaConEIni = $this->appendGroup($dom, $gCamCond, 'gPaConEIni');
            $this->appendText($dom, $gPaConEIni, 'iTiPago', '5');
            $this->appendText($dom, $gPaConEIni, 'dDesTiPag', 'Transferencia');
            $this->appendText($dom, $gPaConEIni, 'dMonTiPag', $this->formatNumero($totales['dTotGralOpe']));
            $this->appendText($dom, $gPaConEIni, 'cMoneTiPag', $factura->moneda ?: 'PYG');
            $this->appendText($dom, $gPaConEIni, 'dDMoneTiPag', $factura->moneda === 'USD' ? 'US Dollar' : 'Guarani');
        } else {
            $gPagCred = $this->appendGroup($dom, $gCamCond, 'gPagCred');
            $this->appendText($dom, $gPagCred, 'iCondCred', '1');
            $this->appendText($dom, $gPagCred, 'dDCondCred', 'Plazo');
            if ($factura->fecha_vencimiento) {
                $this->appendText($dom, $gPagCred, 'dPlazoCre', '30 días');
            }
        }

        foreach ($itemsCalculados as $item) {
            $this->appendItem($dom, $gDtipDE, $item, $factura->moneda);
        }

        $this->appendTotales($dom, $de, $totales);

        return [
            'xml' => $dom->saveXML(),
            'cdc' => $cdc,
            'codigo_seguridad' => $codigoSeguridad,
            'items_calculados' => $itemsCalculados,
            'totales' => $totales,
        ];
    }

    private function appendEmisor(DOMDocument $dom, DOMElement $parent, SifenConfiguracion $config): void
    {
        $gEmis = $this->appendGroup($dom, $parent, 'gEmis');
        $this->appendText($dom, $gEmis, 'dRucEm', $config->ruc);
        $this->appendText($dom, $gEmis, 'dDVEmi', (string) $config->dv_ruc);
        $this->appendText($dom, $gEmis, 'iTipCont', (string) $config->tipo_contribuyente);
        $this->appendText($dom, $gEmis, 'dNomEmi', $this->normalizarNombre($config->razon_social));
        if ($config->nombre_fantasia) {
            $this->appendText($dom, $gEmis, 'dNomFanEmi', $this->normalizarNombre($config->nombre_fantasia));
        }
        $this->appendText($dom, $gEmis, 'dDirEmi', $config->direccion);
        $this->appendText($dom, $gEmis, 'dNumCas', $config->numero_casa ?: '0');
        $this->appendText($dom, $gEmis, 'cDepEmi', (string) $config->departamento);
        $this->appendText($dom, $gEmis, 'dDesDepEmi', $this->normalizarDescripcionGeo($config->departamento_descripcion));
        $this->appendText($dom, $gEmis, 'cDisEmi', (string) $config->distrito);
        $this->appendText($dom, $gEmis, 'dDesDisEmi', $this->normalizarDescripcionGeo($config->distrito_descripcion));
        $this->appendText($dom, $gEmis, 'cCiuEmi', (string) $config->ciudad);
        $this->appendText($dom, $gEmis, 'dDesCiuEmi', $this->normalizarDescripcionGeo($config->ciudad_descripcion));
        $this->appendText($dom, $gEmis, 'dTelEmi', $this->normalizarTelefono($config->telefono));
        $this->appendText($dom, $gEmis, 'dEmailE', $config->email);

        if ($config->codigo_actividad_economica) {
            $gActEco = $this->appendGroup($dom, $gEmis, 'gActEco');
            $this->appendText($dom, $gActEco, 'cActEco', $config->codigo_actividad_economica);
            $this->appendText($dom, $gActEco, 'dDesActEco', $config->descripcion_actividad_economica ?? 'Servicios');
        }
    }

    /**
     * @param  array<string, mixed>  $receptor
     */
    private function appendReceptor(DOMDocument $dom, DOMElement $parent, array $receptor, int $clienteId): void
    {
        $gDatRec = $this->appendGroup($dom, $parent, 'gDatRec');
        $this->appendText($dom, $gDatRec, 'iNatRec', (string) $receptor['iNatRec']);
        $this->appendText($dom, $gDatRec, 'iTiOpe', (string) $receptor['iTiOpe']);
        $this->appendText($dom, $gDatRec, 'cPaisRec', config('sifen.defaults.pais', 'PRY'));
        $this->appendText($dom, $gDatRec, 'dDesPaisRe', config('sifen.defaults.pais_descripcion', 'Paraguay'));

        if ($receptor['dRucRec']) {
            $this->appendText($dom, $gDatRec, 'iTiContRec', (string) $receptor['iTiContRec']);
            $this->appendText($dom, $gDatRec, 'dRucRec', $receptor['dRucRec']);
            $this->appendText($dom, $gDatRec, 'dDVRec', (string) $receptor['dDVRec']);
        } else {
            $this->appendText($dom, $gDatRec, 'iTipIDRec', (string) $receptor['iTipIDRec']);
            $this->appendText($dom, $gDatRec, 'dDTipIDRec', (string) $receptor['dDTipIDRec']);
            $this->appendText($dom, $gDatRec, 'dNumIDRec', (string) $receptor['dNumIDRec']);
        }

        $this->appendText($dom, $gDatRec, 'dNomRec', $this->normalizarNombre($receptor['dNomRec']));
        $this->appendText($dom, $gDatRec, 'dCodCliente', str_pad((string) $clienteId, 3, '0', STR_PAD_LEFT));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function appendItem(DOMDocument $dom, DOMElement $parent, array $item, string $moneda): void
    {
        $gCamItem = $this->appendGroup($dom, $parent, 'gCamItem');
        $this->appendText($dom, $gCamItem, 'dCodInt', $item['dCodInt']);
        $this->appendText($dom, $gCamItem, 'dDesProSer', $item['dDesProSer']);
        $this->appendText($dom, $gCamItem, 'cUniMed', (string) config('sifen.defaults.unidad_medida', 77));
        $this->appendText($dom, $gCamItem, 'dDesUniMed', config('sifen.defaults.unidad_medida_descripcion', 'UNI'));
        $this->appendText($dom, $gCamItem, 'dCantProSer', $this->formatNumero($item['dCantProSer'], 4));

        $gValorItem = $this->appendGroup($dom, $gCamItem, 'gValorItem');
        $this->appendText($dom, $gValorItem, 'dPUniProSer', $this->formatNumero($item['dPUniProSer'], 8));
        $this->appendText($dom, $gValorItem, 'dTotBruOpeItem', $this->formatNumero($item['dTotBruOpeItem']));

        $gValorRestaItem = $this->appendGroup($dom, $gValorItem, 'gValorRestaItem');
        $this->appendText($dom, $gValorRestaItem, 'dTotOpeItem', $this->formatNumero($item['dTotOpeItem']));

        $gCamIVA = $this->appendGroup($dom, $gCamItem, 'gCamIVA');
        $this->appendText($dom, $gCamIVA, 'iAfecIVA', (string) $item['iAfecIVA']);
        $this->appendText($dom, $gCamIVA, 'dDesAfecIVA', $item['dDesAfecIVA']);
        $this->appendText($dom, $gCamIVA, 'dPropIVA', $this->formatNumero($item['dPropIVA'], 8));
        $this->appendText($dom, $gCamIVA, 'dTasaIVA', (string) $item['dTasaIVA']);
        $this->appendText($dom, $gCamIVA, 'dBasGravIVA', $this->formatNumero($item['dBasGravIVA'], 8));
        $this->appendText($dom, $gCamIVA, 'dLiqIVAItem', $this->formatNumero($item['dLiqIVAItem'], 8));
        $this->appendText($dom, $gCamIVA, 'dBasExe', $this->formatNumero($item['dBasExe'], 8));
    }

    /**
     * @param  array<string, float>  $totales
     */
    private function appendTotales(DOMDocument $dom, DOMElement $de, array $totales): void
    {
        $gTotSub = $this->appendGroup($dom, $de, 'gTotSub');

        if ($totales['dSubExe'] > 0) {
            $this->appendText($dom, $gTotSub, 'dSubExe', $this->formatNumero($totales['dSubExe']));
        }
        if ($totales['dSubExo'] > 0) {
            $this->appendText($dom, $gTotSub, 'dSubExo', $this->formatNumero($totales['dSubExo']));
        }
        if ($totales['dSub5'] > 0) {
            $this->appendText($dom, $gTotSub, 'dSub5', $this->formatNumero($totales['dSub5']));
        }
        if ($totales['dSub10'] > 0) {
            $this->appendText($dom, $gTotSub, 'dSub10', $this->formatNumero($totales['dSub10']));
        }

        $this->appendText($dom, $gTotSub, 'dTotOpe', $this->formatNumero($totales['dTotOpe']));
        $this->appendText($dom, $gTotSub, 'dTotDesc', $this->formatNumero($totales['dTotDesc']));
        $this->appendText($dom, $gTotSub, 'dTotDescGlotem', $this->formatNumero($totales['dTotDescGlotem']));
        $this->appendText($dom, $gTotSub, 'dTotAntItem', $this->formatNumero($totales['dTotAntItem']));
        $this->appendText($dom, $gTotSub, 'dTotAnt', $this->formatNumero($totales['dTotAnt']));
        $this->appendText($dom, $gTotSub, 'dPorcDescTotal', $this->formatNumero($totales['dPorcDescTotal'], 4));
        $this->appendText($dom, $gTotSub, 'dDescTotal', $this->formatNumero($totales['dDescTotal']));
        $this->appendText($dom, $gTotSub, 'dAnticipo', $this->formatNumero($totales['dAnticipo']));
        $this->appendText($dom, $gTotSub, 'dRedon', $this->formatNumero($totales['dRedon'], 4));
        $this->appendText($dom, $gTotSub, 'dTotGralOpe', $this->formatNumero($totales['dTotGralOpe']));

        if ($totales['dIVA5'] > 0) {
            $this->appendText($dom, $gTotSub, 'dIVA5', $this->formatNumero($totales['dIVA5']));
        }
        if ($totales['dIVA10'] > 0) {
            $this->appendText($dom, $gTotSub, 'dIVA10', $this->formatNumero($totales['dIVA10']));
        }

        $this->appendText($dom, $gTotSub, 'dTotIVA', $this->formatNumero($totales['dTotIVA']));
        $this->appendText($dom, $gTotSub, 'dBaseGrav5', $this->formatNumero($totales['dBaseGrav5']));
        $this->appendText($dom, $gTotSub, 'dBaseGrav10', $this->formatNumero($totales['dBaseGrav10']));
        $this->appendText($dom, $gTotSub, 'dTBasGraIVA', $this->formatNumero($totales['dBaseGrav5'] + $totales['dBaseGrav10']));
    }

    private function appendGroup(DOMDocument $dom, DOMElement $parent, string $name): DOMElement
    {
        $element = $dom->createElementNS(self::NS, $name);
        $parent->appendChild($element);

        return $element;
    }

    private function appendText(DOMDocument $dom, DOMElement $parent, string $name, string $value): void
    {
        $element = $dom->createElementNS(self::NS, $name);
        $element->appendChild($dom->createTextNode($value));
        $parent->appendChild($element);
    }

    private function formatNumero(float $valor, int $decimales = 0): string
    {
        if ($decimales === 0) {
            return (string) (int) round($valor);
        }

        $formatted = number_format($valor, $decimales, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    /** Descripciones geo alineadas al catálogo SIFEN (mayúsculas). */
    private function normalizarDescripcionGeo(string $valor): string
    {
        return mb_strtoupper(trim($valor), 'UTF-8');
    }

    private function normalizarTelefono(string $telefono): string
    {
        $digitos = preg_replace('/\D+/', '', trim($telefono)) ?? '';

        if ($digitos === '') {
            return '000000';
        }

        return mb_substr($digitos, 0, 15);
    }

    private function normalizarNombre(string $nombre): string
    {
        $nombre = str_replace('_', ' ', trim($nombre));
        $nombre = preg_replace('/\s+/u', ' ', $nombre) ?? $nombre;

        return trim($nombre);
    }
}
