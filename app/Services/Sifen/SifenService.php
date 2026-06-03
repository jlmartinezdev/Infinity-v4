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
    ) {}

    /**
     * Flujo completo: XML → firma → QR → envío SIFEN → KuDE PDF.
     *
     * @return array<string, mixed>
     */
    public function emitirDocumento(Factura $factura, bool $enviarSifen = true): array
    {
        if ($factura->estado !== 'borrador') {
            throw new RuntimeException('Solo se pueden emitir facturas en estado borrador.');
        }

        $config = $this->obtenerConfiguracion();
        $preparado = $this->prepararDocumento($factura, false);
        $factura = $preparado['factura'];

        $firmado = $this->xmlSigner->firmar($preparado['xml'], $preparado['cdc']);

        $receptor = $this->receptorParser->parse($factura->cliente);
        $fechaEmisionDe = Carbon::parse($factura->set_fecha_emision_de);

        $qrUrl = $this->qrGenerator->construirUrl(
            $preparado['cdc'],
            $fechaEmisionDe,
            $receptor,
            (float) $preparado['totales']['dTotGralOpe'],
            (float) $preparado['totales']['dTotIVA'],
            $factura->detalles->count(),
            $firmado['digest_value'],
            $config,
        );

        $xmlFinal = $this->qrGenerator->insertarEnXml($firmado['xml'], $qrUrl);
        $xmlPath = $this->guardarXml($factura, $preparado['cdc'], $xmlFinal, 'firmado');

        $validacion = $this->xmlValidator->validar($xmlFinal);

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

                throw new RuntimeException(
                    'SIFEN rechazó el DE: ['.($respuestaSifen['codigo'] ?? '?').'] '
                    .($respuestaSifen['mensaje'] ?? 'Sin mensaje')
                );
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
    public function prepararDocumento(Factura $factura, bool $validarXsd = true): array
    {
        $config = $this->obtenerConfiguracion();
        $factura->loadMissing(['cliente', 'detalles.impuesto']);

        $this->assertFacturaLista($factura, $config);

        $fechaEmisionDe = $this->resolverFechaEmision($factura);
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
            $validacion = $this->xmlValidator->validar($resultado['xml']);
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

        if (! $factura->cliente) {
            throw new RuntimeException('La factura no tiene cliente asociado.');
        }

        if (blank($factura->cliente->cedula)) {
            throw new RuntimeException('El cliente debe tener cédula o RUC informado.');
        }

        if (blank($config->ruc) || blank($config->numero_timbrado)) {
            throw new RuntimeException('Configure RUC y timbrado en sifen_configuracion.');
        }
    }

    private function resolverFechaEmision(Factura $factura): Carbon
    {
        if ($factura->set_fecha_emision_de) {
            return Carbon::parse($factura->set_fecha_emision_de);
        }

        $fecha = Carbon::parse($factura->fecha_emision);

        return $fecha->setTimeFromTimeString(now()->format('H:i:s'));
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

        return 'sifen/xml/'.$nombre;
    }
}
