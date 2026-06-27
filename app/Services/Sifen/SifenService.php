<?php

namespace App\Services\Sifen;

use App\Models\Factura;
use App\Models\SifenConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SifenService
{
    public function __construct(
        private CdcGenerator $cdcGenerator,
        private SifenCodigoSeguridad $codigoSeguridad,
        private SifenXmlBuilder $xmlBuilder,
        private SifenXmlValidator $xmlValidator,
        private SifenXmlSigner $xmlSigner,
        private SifenQrGenerator $qrGenerator,
        private SifenSoapClient $soapClient,
        private SifenRespuestaParser $respuestaParser,
        private SifenKudeService $kudeService,
        private SifenCertificadoService $certificadoService,
        private SifenReceptorParser $receptorParser,
        private SifenApiBridge $apiBridge,
    ) {}

    /**
     * Flujo completo: XML → firma → QR → envío SIFEN → KuDE PDF.
     *
     * @return array<string, mixed>
     */
    public function emitirDocumento(Factura $factura, bool $enviarSifen = true): array
    {
        if ($this->apiBridge->activo()) {
            return $this->apiBridge->emitirDocumento($factura, $enviarSifen);
        }

        if ($factura->estado !== 'borrador') {
            throw new RuntimeException('Solo se pueden emitir facturas en estado borrador.');
        }

        $config = $this->obtenerConfiguracion();
        $fechaDe = $this->momentoEmision($factura);
        $preparado = $this->prepararDocumento($factura, false, $fechaDe);
        $factura = $preparado['factura'];

        $fechaFirma = $this->momentoEmision(
            $factura,
            $this->cdcGenerator->extraerFechaEmision($preparado['cdc']),
        );
        $xmlParaFirmar = SifenXmlManipulator::actualizarFechasDe($preparado['xml'], $fechaFirma);

        $firmado = $this->xmlSigner->firmar($xmlParaFirmar, $preparado['cdc']);

        $qrUrl = $this->qrGenerator->construirUrlDesdeXmlFirmado(
            $firmado['xml'],
            $config,
        );

        $this->assertQrUrlValida($qrUrl);

        $xmlFinal = $this->qrGenerator->insertarEnXml($firmado['xml'], $qrUrl);

        SifenXmlManipulator::assertEstructuraFirmaValida($xmlFinal);

        $domValidacion = new \DOMDocument;
        if (! @$domValidacion->loadXML($xmlFinal)) {
            throw new RuntimeException('El XML firmado generado no es válido. Revise certificado, firma y bloque QR.');
        }

        $xmlPath = $this->guardarXml($factura, $preparado['cdc'], $xmlFinal, 'firmado');

        $validacion = $this->xmlValidator->validar($xmlFinal, 'firmado');

        $respuestaSifen = null;
        $estadoEnvio = 'pendiente';

        if ($enviarSifen) {
            if (! $this->certificadoService->disponible()) {
                throw new RuntimeException('Certificado SIFEN no configurado. No se puede enviar a e-Kuatia.');
            }

            $rawRespuesta = $this->soapClient->enviarDocumento($xmlFinal);
            $respuestaSifen = $this->respuestaParser->parsear($rawRespuesta);
            $estadoEnvio = $respuestaSifen['aprobado'] ? 'autorizado' : 'rechazado';

            if (! $respuestaSifen['aprobado']) {
                $factura->update([
                    'set_estado_envio' => $estadoEnvio,
                    'set_xml_respuesta' => $rawRespuesta,
                    'xml_path' => $xmlPath,
                    'set_qr_url' => $qrUrl,
                ]);

                $codigo = $respuestaSifen['codigo'] ?? '?';
                $mensaje = $respuestaSifen['mensaje'] ?? 'Sin mensaje';
                if ($codigo !== '?' && str_starts_with($mensaje, '['.$codigo.']')) {
                    $textoError = 'SIFEN rechazó el DE: '.$mensaje;
                } else {
                    $textoError = 'SIFEN rechazó el DE: ['.$codigo.'] '.$mensaje;
                }

                throw new RuntimeException($textoError);
            }
        }

        $pdfPath = $this->kudeService->generar($factura, $config, $qrUrl);

        $factura->update([
            'estado' => 'emitida',
            'set_estado_envio' => $estadoEnvio,
            'set_qr_url' => $qrUrl,
            'set_fecha_autorizacion' => $respuestaSifen && $respuestaSifen['aprobado'] ? now() : null,
            'set_xml_respuesta' => $respuestaSifen['raw'] ?? null,
            'xml_path' => $xmlPath,
            'pdf_path' => $pdfPath,
        ]);

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $preparado['cdc'],
            'xml' => $xmlFinal,
            'xml_path' => $xmlPath,
            'pdf_path' => $pdfPath,
            'qr_url' => $qrUrl,
            'validacion' => $validacion,
            'totales' => $preparado['totales'],
            'sifen' => $respuestaSifen,
        ];
    }

    /**
     * Prepara el DE: asigna numeración, CDC, genera XML sin firmar.
     *
     * @return array{
     *   factura: Factura,
     *   cdc: string,
     *   xml: string,
     *   xml_path: string,
     *   validacion: array{valido: bool, errores: array<int, string>},
     *   totales: array<string, float>,
     * }
     */
    public function prepararDocumento(Factura $factura, bool $validarXsd = true, ?Carbon $fechaEmisionDe = null): array
    {
        if ($this->apiBridge->activo()) {
            return $this->apiBridge->prepararDocumento($factura, $validarXsd, $fechaEmisionDe);
        }

        $config = $this->obtenerConfiguracion();
        $factura->loadMissing(['cliente', 'detalles.impuesto']);

        $this->assertFacturaLista($factura, $config);

        $fechaEmisionDe ??= $this->momentoEmision($factura);
        $numeroDocumento = $this->resolverNumeroDocumento($factura, $config);
        $codigoSeg = $this->resolverCodigoSeguridad($factura, $numeroDocumento);

        $tipoDe = (int) config('sifen.tipos_documento.'.$factura->tipo_documento, 1);

        $cdcParams = new CdcParams(
            tipoDocumento: $tipoDe,
            ruc: $config->ruc,
            dvRuc: $config->dv_ruc,
            establecimiento: (int) $factura->establecimiento,
            puntoExpedicion: (int) $factura->punto_emision,
            numeroDocumento: $numeroDocumento,
            tipoContribuyente: (int) $config->tipo_contribuyente,
            fechaEmision: $fechaEmisionDe,
            tipoEmision: (int) config('sifen.defaults.tipo_emision', 1),
            codigoSeguridad: $codigoSeg,
        );

        $cdc = $this->cdcGenerator->generar($cdcParams);

        // El XML usa $factura->numero en gTimb/dNumDoc; debe estar asignado antes de construir.
        $factura->numero = $numeroDocumento;

        $resultado = $this->xmlBuilder->construir(
            $factura,
            $config,
            $cdc,
            $codigoSeg,
            $fechaEmisionDe,
        );

        $xmlPath = $this->guardarXml($factura, $cdc, $resultado['xml'], 'borrador');

        $validacion = ['valido' => true, 'errores' => []];
        if ($validarXsd) {
            $validacion = $this->xmlValidator->validar($resultado['xml'], 'borrador');
        }

        $factura->update([
            'numero' => $numeroDocumento,
            'numero_timbrado' => $config->numero_timbrado,
            'timbrado_vigencia_desde' => $config->timbrado_vigencia_desde,
            'timbrado_vigencia_hasta' => $config->timbrado_vigencia_hasta,
            'set_cdc' => $cdc,
            'set_codigo_seguridad' => str_pad((string) $codigoSeg, 9, '0', STR_PAD_LEFT),
            'set_fecha_emision_de' => $fechaEmisionDe,
            'set_estado_envio' => 'pendiente',
            'xml_path' => $xmlPath,
        ]);

        $config->registrarNumeroEmitido($numeroDocumento);

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $cdc,
            'xml' => $resultado['xml'],
            'xml_path' => $xmlPath,
            'validacion' => $validacion,
            'totales' => $resultado['totales'],
        ];
    }

    public function obtenerConfiguracion(): SifenConfiguracion
    {
        $config = SifenConfiguracion::activa();

        if (! $config) {
            throw new RuntimeException(
                'No hay configuración SIFEN activa. Complete la tabla sifen_configuracion.'
            );
        }

        return $config;
    }

    public function configuracionCompleta(): bool
    {
        return SifenConfiguracion::activa() !== null;
    }

    public function certificadoConfigurado(): bool
    {
        return $this->certificadoService->disponible();
    }

    private function assertFacturaLista(Factura $factura, SifenConfiguracion $config): void
    {
        if ($factura->detalles->isEmpty()) {
            throw new RuntimeException('La factura no tiene ítems.');
        }

        if ($factura->esOcasional()) {
            if (blank($factura->receptor_documento)) {
                throw new RuntimeException('La factura ocasional debe tener documento del receptor.');
            }
            if (blank($factura->receptor_nombre)) {
                throw new RuntimeException('La factura ocasional debe tener nombre del receptor.');
            }
        } else {
            if (! $factura->cliente) {
                throw new RuntimeException('La factura no tiene cliente asociado.');
            }

            if (blank($factura->cliente->cedula)) {
                throw new RuntimeException('El cliente debe tener cédula o RUC informado.');
            }
        }

        if (blank($config->ruc) || blank($config->numero_timbrado)) {
            throw new RuntimeException('Configure RUC y timbrado en sifen_configuracion.');
        }
    }

    /**
     * Momento del DE: fecha contable + hora actual, nunca adelantada respecto a SIFEN (error 1004).
     */
    private function momentoEmision(Factura $factura, ?string $cdcFechaYmd = null): Carbon
    {
        $tz = config('sifen.timezone', 'America/Asuncion');
        $skew = max(0, (int) config('sifen.clock_skew_seconds', 120));
        $tope = Carbon::now($tz)->subSeconds($skew);

        if (blank($factura->fecha_emision)) {
            return $cdcFechaYmd ? $this->alinearFechaConCdc($tope, $cdcFechaYmd) : $tope;
        }

        $fechaContable = Carbon::parse($factura->fecha_emision, $tz)->startOfDay();

        if ($fechaContable->greaterThan($tope->copy()->startOfDay())) {
            return $cdcFechaYmd ? $this->alinearFechaConCdc($tope, $cdcFechaYmd) : $tope;
        }

        $resultado = $tope->copy()->setDate(
            (int) $fechaContable->format('Y'),
            (int) $fechaContable->format('m'),
            (int) $fechaContable->format('d'),
        );

        // setDate puede quedar adelantado cerca de medianoche; nunca superar $tope.
        if ($resultado->greaterThan($tope)) {
            $resultado = $tope->copy();
        }

        if ($cdcFechaYmd) {
            $resultado = $this->alinearFechaConCdc($resultado, $cdcFechaYmd);
            if ($resultado->greaterThan($tope)) {
                $resultado = $this->alinearFechaConCdc($tope, $cdcFechaYmd);
            }
        }

        return $resultado;
    }

    private function alinearFechaConCdc(Carbon $fecha, string $cdcYmd): Carbon
    {
        $tz = $fecha->getTimezone()->getName();

        return Carbon::createFromFormat(
            'Ymd H:i:s',
            $cdcYmd.' '.$fecha->format('H:i:s'),
            $tz
        );
    }

    private function resolverNumeroDocumento(Factura $factura, SifenConfiguracion $config): int
    {
        if ($factura->numero) {
            return (int) $factura->numero;
        }

        $desdeFactura = $config->establecimiento === (int) $factura->establecimiento
            && $config->punto_expedicion === (int) $factura->punto_emision;

        if ($desdeFactura) {
            return $config->siguienteNumeroDocumento();
        }

        $ultimo = Factura::query()
            ->where('establecimiento', $factura->establecimiento)
            ->where('punto_emision', $factura->punto_emision)
            ->whereNotNull('numero')
            ->max('numero');

        return ((int) $ultimo) + 1;
    }

    private function resolverCodigoSeguridad(Factura $factura, int $numeroDocumento): int
    {
        if ($factura->set_codigo_seguridad) {
            return (int) $factura->set_codigo_seguridad;
        }

        return $this->codigoSeguridad->generar($numeroDocumento);
    }

    private function guardarXml(Factura $factura, string $cdc, string $xml, string $etapa = 'de'): string
    {
        $directorio = config('sifen.paths.xml');
        File::ensureDirectoryExists($directorio);

        $nombre = sprintf(
            'DE_%s_%s_%s.xml',
            $etapa,
            $cdc,
            now()->format('YmdHis')
        );

        $rutaAbsoluta = $directorio.DIRECTORY_SEPARATOR.$nombre;
        File::put($rutaAbsoluta, $xml);

        $verificado = File::get($rutaAbsoluta);
        if (! str_ends_with(trim($verificado), '</rDE>')) {
            File::delete($rutaAbsoluta);
            throw new RuntimeException('El XML guardado está incompleto (falta cierre </rDE>).');
        }

        $dom = new \DOMDocument;
        if (! @$dom->loadXML($verificado)) {
            File::delete($rutaAbsoluta);
            throw new RuntimeException('El XML guardado no es un documento XML válido.');
        }

        return 'sifen/xml/'.$nombre;
    }

    private function assertQrUrlValida(string $qrUrl): void
    {
        $baseTest = 'https://ekuatia.set.gov.py/consultas-test/qr?';
        $baseProd = 'https://ekuatia.set.gov.py/consultas/qr?';
        $ambiente = config('sifen.ambiente', 'test');
        $baseEsperada = $ambiente === 'production' ? $baseProd : $baseTest;

        if (! str_starts_with($qrUrl, $baseEsperada)) {
            throw new RuntimeException('URL QR inválida: base incorrecta para ambiente '.$ambiente.'.');
        }

        if (str_contains($qrUrl, 'dNumIdRec=')) {
            throw new RuntimeException('URL QR inválida: el parámetro debe ser dNumIDRec (no dNumIdRec).');
        }

        if (! str_contains($qrUrl, 'dRucRec=') && ! str_contains($qrUrl, 'dNumIDRec=')) {
            throw new RuntimeException('URL QR inválida: falta identificación del receptor (dRucRec o dNumIDRec).');
        }

        foreach (['nVersion=', 'Id=', 'dFeEmiDE=', 'dTotGralOpe=', 'dTotIVA=', 'cItems=', 'DigestValue=', 'IdCSC=', 'cHashQR='] as $param) {
            if (! str_contains($qrUrl, $param)) {
                throw new RuntimeException('URL QR inválida: falta parámetro '.$param);
            }
        }
    }
}
