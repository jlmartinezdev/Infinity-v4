<?php

namespace App\Services\Sifen;

use App\Models\Factura;
use Carbon\Carbon;
use RuntimeException;

class SifenEventoCancelacion
{
    public function __construct(
        private SifenApiBridge $apiBridge,
        private SifenXmlSigner $xmlSigner,
        private SifenSoapClient $soapClient,
        private SifenRespuestaParser $parser,
        private SifenCertificadoService $certificadoService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancelar(Factura $factura, string $motivo): array
    {
        $motivo = trim($motivo);
        $this->assertPuedeCancelar($factura, $motivo);

        if ($this->apiBridge->activo() && $factura->sifen_api_documento_id) {
            try {
                return $this->apiBridge->cancelarDocumento($factura, $motivo);
            } catch (\Throwable $e) {
                if (! $this->certificadoService->disponible()) {
                    throw new RuntimeException(
                        'No se pudo cancelar vía sifen-api: '.$e->getMessage()
                        .'. Usá nota de crédito o revisá que el API soporte /documentos/{id}/cancelar.'
                    );
                }
            }
        }

        return $this->cancelarLocal($factura, $motivo);
    }

    /**
     * @return array<string, mixed>
     */
    private function cancelarLocal(Factura $factura, string $motivo): array
    {
        if (! $this->certificadoService->disponible()) {
            throw new RuntimeException('Certificado SIFEN no configurado para enviar el evento de cancelación.');
        }

        $xml = $this->construirXmlEvento((string) $factura->set_cdc, $motivo);
        $firmado = $this->xmlSigner->firmarEvento($xml, '1');
        $raw = $this->soapClient->enviarEvento($firmado['xml']);
        $parsed = $this->parser->parsearEvento($raw);

        if (! $parsed['aprobado']) {
            throw new RuntimeException(
                'SIFEN rechazó la cancelación'
                .($parsed['codigo'] ? ' ['.$parsed['codigo'].']' : '')
                .($parsed['mensaje'] ? ': '.$parsed['mensaje'] : '.')
            );
        }

        $this->apiBridge->marcarAnulada($factura, [
            'via' => 'local',
            'motivo' => $motivo,
            'codigo' => $parsed['codigo'],
            'mensaje' => $parsed['mensaje'],
            'raw' => $raw,
        ]);

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'via' => 'local',
            'sifen' => $parsed,
        ];
    }

    private function construirXmlEvento(string $cdc, string $motivo): string
    {
        $ns = (string) config('sifen.namespace', 'http://ekuatia.set.gov.py/sifen/xsd');
        $tz = (string) config('sifen.timezone', 'America/Asuncion');
        $skew = (int) config('sifen.clock_skew_seconds', 0);
        $firma = Carbon::now($tz)->subSeconds($skew)->format('Y-m-d\TH:i:s');
        $cdc = htmlspecialchars($cdc, ENT_XML1);
        $motivoXml = htmlspecialchars($motivo, ENT_XML1);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<rGesEve xmlns="'.$ns.'">'
            .'<rEve Id="1">'
            .'<dFecFirma>'.$firma.'</dFecFirma>'
            .'<dVerFor>'.(int) config('sifen.version_formato', 150).'</dVerFor>'
            .'<gGroupTiEvt>'
            .'<rGeVeCan>'
            .'<Id>'.$cdc.'</Id>'
            .'<mOtEve>'.$motivoXml.'</mOtEve>'
            .'</rGeVeCan>'
            .'</gGroupTiEvt>'
            .'</rEve>'
            .'</rGesEve>';
    }

    private function assertPuedeCancelar(Factura $factura, string $motivo): void
    {
        if (! $factura->puedeCancelarPorEvento()) {
            $limite = $factura->fechaLimiteCancelacionEvento();
            if ($limite && $limite->isPast()) {
                throw new RuntimeException(
                    'La ventana de cancelación SIFEN ('.(int) config('sifen.cancelacion_horas', 48)
                    .' h) ya venció. Emití una nota de crédito.'
                );
            }

            throw new RuntimeException('Esta factura no se puede cancelar por evento SIFEN.');
        }

        if (mb_strlen($motivo) < 5) {
            throw new RuntimeException('El motivo debe tener al menos 5 caracteres.');
        }

        if (mb_strlen($motivo) > 500) {
            throw new RuntimeException('El motivo no puede superar 500 caracteres.');
        }
    }
}
