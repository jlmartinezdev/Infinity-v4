<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\Impuesto;
use App\Models\SifenConfiguracion;
use App\Services\Sifen\SifenCertificadoService;
use App\Services\Sifen\SifenApiClient;
use App\Services\Sifen\SifenRespuestaParser;
use App\Services\Sifen\SifenService;
use App\Services\Sifen\SifenXmlSigner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class SifenPruebaController extends Controller
{
    public function index(
        SifenService $sifenService,
        SifenCertificadoService $certificadoService,
        SifenXmlSigner $xmlSigner,
        SifenApiClient $apiClient,
    ) {
        $config = SifenConfiguracion::activa();
        $estado = $this->construirEstado($config, $certificadoService, $xmlSigner, $apiClient);
        $listo = $this->prerrequisitosCompletos($estado);

        $referenciaAprobada = storage_path('sifen/xml/DE_firmado_01052639347001001000006012026061910307565956_20260619172933.xml');
        $diagnosticoCert = $certificadoService->diagnosticarCertificado(
            is_file($referenciaAprobada) ? $referenciaAprobada : null
        );

        $clientes = Cliente::query()
            ->whereNotNull('cedula')
            ->where('cedula', '!=', '')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->limit(200)
            ->get(['cliente_id', 'nombre', 'apellido', 'cedula']);

        $borradores = Factura::with('cliente')
            ->where('estado', 'borrador')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $totalPruebas = Factura::pruebaSifen()->count();

        $recientes = Factura::with('cliente')
            ->pruebaSifen()
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $impuestoDefault = Impuesto::where('codigo', 'IVA10')->first()
            ?? Impuesto::activos()->first();

        $facturasReferencia = Factura::query()
            ->where('estado', 'emitida')
            ->whereNotNull('set_cdc')
            ->where('set_estado_envio', 'autorizado')
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'tipo_documento', 'numero', 'establecimiento', 'punto_emision', 'set_cdc', 'total', 'cliente_id']);

        $catalogoApi = null;
        if (($estado['modo_api'] ?? false) && $apiClient->isConfigured()) {
            try {
                $catalogoApi = $apiClient->catalogoTiposDocumento();
            } catch (\Throwable) {
                $catalogoApi = null;
            }
        }

        $tiposDocumentoLab = Factura::tiposDocumento();
        if (! ($estado['modo_api'] ?? false)) {
            $tiposDocumentoLab = array_diff_key(
                $tiposDocumentoLab,
                array_flip(array_merge(Factura::tiposSoloApi(), Factura::tiposConDocumentoAsociado()))
            );
        }

        return view('configuracion.sifen-prueba', compact(
            'config',
            'estado',
            'listo',
            'clientes',
            'borradores',
            'recientes',
            'totalPruebas',
            'impuestoDefault',
            'diagnosticoCert',
            'facturasReferencia',
            'catalogoApi',
            'tiposDocumentoLab',
        ));
    }

    public function limpiarPruebas(Request $request)
    {
        if (config('sifen.ambiente') !== 'test') {
            return back()->with('error', 'Solo se puede limpiar facturas de prueba en ambiente TEST.');
        }

        $facturas = Factura::pruebaSifen()->get();

        if ($facturas->isEmpty()) {
            return back()->with('success', 'No hay facturas de prueba para eliminar.');
        }

        $eliminadas = 0;
        $archivos = 0;

        DB::transaction(function () use ($facturas, &$eliminadas, &$archivos) {
            foreach ($facturas as $factura) {
                $archivos += $this->eliminarArchivosSifenFactura($factura);
                $factura->detalles()->delete();
                $factura->delete();
                $eliminadas++;
            }
        });

        return redirect()->route('configuracion.sifen.prueba')
            ->with('success', "Se eliminaron {$eliminadas} factura(s) de prueba y {$archivos} archivo(s) en disco.");
    }

    public function crearFactura(Request $request)
    {
        $modoApi = (bool) config('sifen.api.enabled');
        $tiposPermitidos = $modoApi
            ? array_keys(Factura::tiposDocumento())
            : array_keys(array_diff_key(
                Factura::tiposDocumento(),
                array_flip(array_merge(Factura::tiposSoloApi(), Factura::tiposConDocumentoAsociado()))
            ));

        $validated = $request->validate([
            'tipo_documento' => ['required', Rule::in($tiposPermitidos)],
            'cliente_id' => ['required', 'integer', 'exists:clientes,cliente_id'],
            'monto' => ['required', 'numeric', 'min:1000'],
            'descripcion' => ['required', 'string', 'max:255'],
            'impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'factura_referencia_id' => [
                Rule::requiredIf(in_array($request->input('tipo_documento'), Factura::tiposConDocumentoAsociado(), true)),
                'nullable',
                'integer',
                'exists:factura_electronicas,id',
            ],
            'motivo_emision' => ['nullable', 'integer', 'between:1,8'],
            'motivo_traslado' => ['nullable', 'integer'],
            'kilometros' => ['nullable', 'integer', 'between:1,99999'],
            'transportista_nombre' => ['nullable', 'string', 'max:255'],
            'chofer_nombre' => ['nullable', 'string', 'max:255'],
            'chofer_documento' => ['nullable', 'string', 'max:20'],
        ]);

        $tipoDocumento = $validated['tipo_documento'];

        if (in_array($tipoDocumento, Factura::tiposSoloApi(), true) && ! $modoApi) {
            return back()->with('error', 'Autofactura y nota de remisión requieren modo API (SIFEN_API_ENABLED=true).');
        }

        if (in_array($tipoDocumento, Factura::tiposConDocumentoAsociado(), true) && ! $modoApi) {
            return back()->with('error', 'Notas de crédito/débito en laboratorio requieren modo API.');
        }

        $cliente = Cliente::find($validated['cliente_id']);
        if (blank($cliente?->cedula)) {
            return back()->with('error', 'El cliente seleccionado debe tener cédula o RUC.');
        }

        $config = SifenConfiguracion::activa();
        $impuesto = isset($validated['impuesto_id'])
            ? Impuesto::find($validated['impuesto_id'])
            : (Impuesto::where('codigo', 'IVA10')->first() ?? Impuesto::activos()->first());

        $facturaReferencia = null;
        if (! empty($validated['factura_referencia_id'])) {
            $facturaReferencia = Factura::find($validated['factura_referencia_id']);
            if (blank($facturaReferencia?->set_cdc)) {
                return back()->with('error', 'La factura de referencia debe estar autorizada y tener CDC.');
            }
        }

        try {
            $datosComplementarios = $this->construirDatosComplementarios(
                $tipoDocumento,
                $cliente,
                $config,
                $facturaReferencia,
                $validated,
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $etiquetaTipo = Factura::tiposDocumento()[$tipoDocumento] ?? $tipoDocumento;

        $factura = DB::transaction(function () use ($validated, $config, $impuesto, $request, $tipoDocumento, $datosComplementarios, $etiquetaTipo) {
            $factura = Factura::create([
                'cliente_id' => $validated['cliente_id'],
                'tipo_documento' => $tipoDocumento,
                'estado' => 'borrador',
                'fecha_emision' => now()->toDateString(),
                'moneda' => 'PYG',
                'establecimiento' => $config?->establecimiento ?? 1,
                'punto_emision' => $config?->punto_expedicion ?? 1,
                'observaciones' => 'PRUEBA SIFEN - '.$etiquetaTipo.' - '.($request->user()?->name ?? 'sistema').' - '.now()->format('d/m/Y H:i'),
                'datos_complementarios' => $datosComplementarios,
                'usuario_id' => $request->user()?->usuario_id,
                'subtotal' => 0,
                'total_impuestos' => 0,
                'total' => 0,
            ]);

            $calc = FacturaDetalle::calcularDesdePrecio(1, (float) $validated['monto'], $impuesto);

            FacturaDetalle::create([
                'factura_electronica_id' => $factura->id,
                'impuesto_id' => $impuesto?->id,
                'descripcion' => $validated['descripcion'],
                'cantidad' => 1,
                'precio_unitario' => $validated['monto'],
                'subtotal' => $calc['subtotal'],
                'porcentaje_impuesto' => $calc['porcentaje_impuesto'],
                'monto_impuesto' => $calc['monto_impuesto'],
                'total' => $calc['total'],
            ]);

            $factura->load('detalles');
            $factura->recalcularTotales();

            return $factura;
        });

        return redirect()->route('configuracion.sifen.prueba')
            ->with('success', $etiquetaTipo.' de prueba #'.$factura->id.' creada en borrador.')
            ->with('resultado', [
                'accion' => 'crear',
                'tipo_documento' => $tipoDocumento,
                'factura_id' => $factura->id,
                'total' => (float) $factura->total,
            ]);
    }

    public function preparar(Factura $factura, SifenService $sifenService)
    {
        if ($factura->estado !== 'borrador') {
            return back()->with('error', 'Solo se puede preparar un documento en borrador.');
        }

        if ($factura->requiereApi() && ! config('sifen.api.enabled')) {
            return back()->with('error', 'Este tipo de documento solo se puede preparar con modo API activo.');
        }

        try {
            $resultado = $sifenService->prepararDocumento($factura, true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al preparar DE: '.$e->getMessage());
        }

        $sincronizado = (bool) ($resultado['sincronizado_desde_api'] ?? false);

        return redirect()->route('configuracion.sifen.prueba')
            ->with('success', $sincronizado
                ? 'Documento ya emitido en sifen-api: se sincronizó en Infinity (sin número nuevo).'
                : 'Documento electrónico preparado (XML + CDC).')
            ->with('resultado', [
                'accion' => 'preparar',
                'tipo_documento' => $factura->tipo_documento,
                'modo_api' => config('sifen.api.enabled'),
                'factura_id' => $resultado['factura']->id,
                'sifen_api_documento_id' => $resultado['factura']->sifen_api_documento_id,
                'cdc' => $resultado['cdc'],
                'xml_path' => $resultado['xml_path'],
                'validacion' => $resultado['validacion'],
                'totales' => $resultado['totales'],
                'sincronizado_desde_api' => $resultado['sincronizado_desde_api'] ?? false,
                'siguiente_numero_remoto' => $resultado['siguiente_numero_remoto'] ?? null,
                'sifen' => $resultado['sifen'] ?? null,
            ]);
    }

    public function emitirLocal(Factura $factura, SifenService $sifenService)
    {
        if ($factura->estado !== 'borrador') {
            return back()->with('error', 'Solo se puede emitir una factura en borrador.');
        }

        if ($factura->requiereApi() && ! config('sifen.api.enabled')) {
            return back()->with('error', 'Este tipo de documento solo se emite con modo API activo.');
        }

        try {
            $resultado = $sifenService->emitirDocumento($factura, false);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al emitir localmente: '.$e->getMessage());
        }

        return redirect()->route('configuracion.sifen.prueba')
            ->with('success', 'Emitido localmente (firma + QR + KuDE) sin envío a SIFEN.')
            ->with('resultado', [
                'accion' => 'emitir_local',
                'factura_id' => $resultado['factura']->id,
                'cdc' => $resultado['cdc'],
                'xml_path' => $resultado['xml_path'],
                'pdf_path' => $resultado['pdf_path'],
                'qr_url' => $resultado['qr_url'],
                'validacion' => $resultado['validacion'],
            ]);
    }

    public function probarTls(SifenCertificadoService $certificadoService)
    {
        if (config('sifen.api.enabled')) {
            return back()->with('error', 'En modo API use «Probar mTLS vía API» (el certificado está en sifen-api).');
        }

        if (config('sifen.ambiente') !== 'test') {
            return back()->with('error', 'La prueba TLS solo está habilitada con SIFEN_AMBIENTE=test.');
        }

        try {
            $resultado = $certificadoService->probarConexionTls();
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo probar mTLS: '.$e->getMessage());
        }

        $referenciaAprobada = storage_path('sifen/xml/DE_firmado_01052639347001001000006012026061910307565956_20260619172933.xml');
        $diagnostico = $certificadoService->diagnosticarCertificado(
            is_file($referenciaAprobada) ? $referenciaAprobada : null
        );

        if ($resultado['ok'] ?? false) {
            return back()
                ->with('success', 'Conexión mTLS con SIFEN TEST exitosa (HTTP '.($resultado['http_code'] ?? '?').').')
                ->with('tls_probe', $resultado)
                ->with('diagnostico_cert', $diagnostico);
        }

        $mensaje = 'SIFEN rechazó mTLS (HTTP '.($resultado['http_code'] ?? '?').'). ';
        if (($resultado['redirectUrl'] ?? '') !== '') {
            $mensaje .= 'Redirección: '.$resultado['redirectUrl'].'. ';
        }
        $mensaje .= 'El certificado de firma puede ser válido, pero debe estar habilitado para TEST en Marangatu/e-Kuatia.';

        return back()
            ->with('error', $mensaje)
            ->with('tls_probe', $resultado)
            ->with('diagnostico_cert', $diagnostico);
    }

    public function probarApi(SifenApiClient $apiClient)
    {
        $resultado = $apiClient->probarConexion();

        if ($resultado['ok'] ?? false) {
            return back()
                ->with('success', $resultado['mensaje'].(isset($resultado['latencia_ms']) ? ' ('.$resultado['latencia_ms'].' ms)' : ''))
                ->with('api_probe', $resultado);
        }

        return back()
            ->with('error', 'Conexión API falló: '.($resultado['mensaje'] ?? 'Error'))
            ->with('api_probe', $resultado);
    }

    public function probarApiTls(SifenApiClient $apiClient)
    {
        if (config('sifen.ambiente') !== 'test') {
            return back()->with('error', 'La prueba mTLS vía API solo está habilitada en ambiente TEST.');
        }

        $resultado = $apiClient->probarTlsRemoto();

        if ($resultado['ok'] ?? false) {
            return back()
                ->with('success', $resultado['mensaje'])
                ->with('api_tls_probe', $resultado);
        }

        return back()
            ->with('error', 'mTLS vía API falló: '.($resultado['mensaje'] ?? 'Error'))
            ->with('api_tls_probe', $resultado);
    }

    public function emitir(Factura $factura, SifenService $sifenService)
    {
        if ($factura->estado !== 'borrador') {
            return back()->with('error', 'Solo se puede enviar a SIFEN una factura en borrador.');
        }

        if (config('sifen.ambiente') !== 'test') {
            return back()->with('error', 'El envío a SIFEN desde pruebas solo está habilitado con SIFEN_AMBIENTE=test.');
        }

        if ($factura->requiereApi() && ! config('sifen.api.enabled')) {
            return back()->with('error', 'Este tipo de documento solo se envía a SIFEN con modo API activo.');
        }

        try {
            $resultado = $sifenService->emitirDocumento($factura, true);
        } catch (\Throwable $e) {
            $factura->refresh();
            $sifenParseado = $factura->set_xml_respuesta
                ? app(SifenRespuestaParser::class)->parsear($factura->set_xml_respuesta)
                : null;

            return redirect()->route('configuracion.sifen.prueba')
                ->with('error', 'SIFEN rechazó o falló el envío: '.$e->getMessage())
                ->with('resultado', [
                    'accion' => 'emitir_fallido',
                    'modo_api' => config('sifen.api.enabled'),
                    'factura_id' => $factura->id,
                    'sifen_api_documento_id' => $factura->sifen_api_documento_id,
                    'cdc' => $factura->set_cdc,
                    'estado_envio' => $factura->set_estado_envio,
                    'sifen' => $sifenParseado,
                    'respuesta' => $sifenParseado['mensaje'] ?? $this->resumirRespuestaXml($factura->set_xml_respuesta),
                ]);
        }

        $sincronizado = (bool) ($resultado['sincronizado_desde_api'] ?? false);
        $mensajeExito = $sincronizado
            ? 'Documento ya emitido en sifen-api: datos sincronizados en Infinity (no se generó un número nuevo).'
            : 'Documento enviado y autorizado por SIFEN (ambiente de prueba).';
        if (($resultado['correo']['enviado'] ?? false) && ($resultado['correo']['destinatario'] ?? null)) {
            $mensajeExito .= ' Correo enviado a '.$resultado['correo']['destinatario'].'.';
        }

        return redirect()->route('configuracion.sifen.prueba')
            ->with('success', $mensajeExito)
            ->with('resultado', [
                'accion' => $sincronizado ? 'sincronizar_api' : 'emitir',
                'modo_api' => config('sifen.api.enabled'),
                'factura_id' => $resultado['factura']->id,
                'sifen_api_documento_id' => $resultado['factura']->sifen_api_documento_id,
                'cdc' => $resultado['cdc'],
                'xml_path' => $resultado['xml_path'],
                'pdf_path' => $resultado['pdf_path'],
                'qr_url' => $resultado['qr_url'],
                'validacion' => $resultado['validacion'] ?? null,
                'sifen' => $resultado['sifen'],
                'correo' => $resultado['correo'] ?? null,
                'sincronizado_desde_api' => $sincronizado,
                'siguiente_numero_remoto' => $resultado['siguiente_numero_remoto'] ?? null,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function construirEstado(
        ?SifenConfiguracion $config,
        SifenCertificadoService $certificadoService,
        SifenXmlSigner $xmlSigner,
        SifenApiClient $apiClient,
    ): array {
        $modoApi = (bool) config('sifen.api.enabled');
        $apiStatus = null;
        $apiConectada = false;

        if ($modoApi && $apiClient->isConfigured()) {
            try {
                $apiStatus = $apiClient->status();
                $apiConectada = (bool) ($apiStatus['config_activa'] ?? false);
            } catch (\Throwable) {
                $apiConectada = false;
            }
        }

        return [
            'ambiente' => config('sifen.ambiente', 'test'),
            'endpoint' => config('sifen.ws.'.config('sifen.ambiente', 'test').'.recepcion_de_endpoint'),
            'modo_api' => $modoApi,
            'api_url' => config('sifen.api.url'),
            'api_configurada' => $apiClient->isConfigured(),
            'api_conectada' => $apiConectada,
            'api_status' => $apiStatus,
            'api_panel_url' => $apiClient->isConfigured() ? $apiClient->urlPanelConfiguracion() : null,
            'config_activa' => $config !== null,
            'emisor' => $config ? ($config->razon_social.' (RUC '.$config->ruc.'-'.$config->dv_ruc.')') : null,
            'timbrado' => $config?->numero_timbrado,
            'ultimo_numero' => $modoApi && isset($apiStatus['siguiente_numero'])
                ? max(0, (int) $apiStatus['siguiente_numero'] - 1)
                : (int) ($config?->ultimo_numero_factura ?? 0),
            'siguiente_numero' => $modoApi && isset($apiStatus['siguiente_numero'])
                ? (int) $apiStatus['siguiente_numero']
                : ($config?->siguienteNumeroDocumento() ?? 1),
            'numeracion_en_api' => $modoApi,
            'certificado_configurado' => $modoApi
                ? (bool) ($apiStatus['certificado_configurado'] ?? false)
                : $certificadoService->disponible(),
            'certificado_existe' => File::exists(config('sifen.certificado.path')),
            'csc_configurado' => $modoApi
                ? (bool) ($apiStatus['csc_configurado'] ?? false)
                : filled($config?->cscTokenEfectivo()),
            'csc_id' => $config?->cscIdEfectivo(),
            'motor_firma' => $modoApi
                ? ($apiStatus['motor_firma'] ?? 'sifen-api')
                : $xmlSigner->motorFirmaActivo(),
            'actividad_economica_configurada' => $modoApi
                ? (bool) ($apiStatus['actividad_economica_configurada'] ?? false)
                : filled($config?->codigo_actividad_economica),
        ];
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function prerrequisitosCompletos(array $estado): bool
    {
        if ($estado['modo_api'] ?? false) {
            return ($estado['api_configurada'] ?? false)
                && ($estado['api_conectada'] ?? false)
                && ($estado['certificado_configurado'] ?? false)
                && ($estado['csc_configurado'] ?? false)
                && ($estado['actividad_economica_configurada'] ?? false);
        }

        return $estado['config_activa']
            && $estado['certificado_configurado']
            && $estado['csc_configurado'];
    }

    private function resumirRespuestaXml(?string $xml): ?string
    {
        if (blank($xml)) {
            return null;
        }

        $texto = trim(strip_tags($xml));
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return mb_substr($texto, 0, 500).(mb_strlen($texto) > 500 ? '…' : '');
    }

    private function eliminarArchivosSifenFactura(Factura $factura): int
    {
        $eliminados = 0;

        foreach (['xml_path', 'pdf_path'] as $campo) {
            $relativa = $factura->{$campo};
            if (blank($relativa)) {
                continue;
            }

            $absoluta = storage_path($relativa);
            if (File::isFile($absoluta) && File::delete($absoluta)) {
                $eliminados++;
            }
        }

        if (blank($factura->set_cdc)) {
            return $eliminados;
        }

        $cdc = $factura->set_cdc;

        foreach ([config('sifen.paths.xml'), config('sifen.paths.pdf')] as $directorio) {
            if (! is_dir($directorio)) {
                continue;
            }

            foreach (glob($directorio.DIRECTORY_SEPARATOR.'*'.$cdc.'*') ?: [] as $archivo) {
                if (File::isFile($archivo) && File::delete($archivo)) {
                    $eliminados++;
                }
            }
        }

        return $eliminados;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function construirDatosComplementarios(
        string $tipoDocumento,
        Cliente $cliente,
        ?SifenConfiguracion $config,
        ?Factura $facturaReferencia,
        array $input,
    ): ?array {
        if (! in_array($tipoDocumento, array_merge(
            Factura::tiposConDocumentoAsociado(),
            Factura::tiposSoloApi()
        ), true)) {
            return null;
        }

        if (in_array($tipoDocumento, Factura::tiposConDocumentoAsociado(), true)) {
            if (! $facturaReferencia || blank($facturaReferencia->set_cdc)) {
                throw new \InvalidArgumentException('Seleccione una factura autorizada como referencia para la nota.');
            }

            return [
                'motivo_emision' => (int) ($input['motivo_emision'] ?? 2),
                'documento_asociado' => [
                    'tipo' => 1,
                    'cdc' => $facturaReferencia->set_cdc,
                ],
            ];
        }

        if ($tipoDocumento === 'autofactura') {
            if (! $config) {
                throw new \InvalidArgumentException('Configure el emisor SIFEN antes de probar autofactura.');
            }

            $documento = preg_replace('/\D/', '', (string) $cliente->cedula) ?: '0';

            return [
                'vendedor' => [
                    'naturaleza' => 1,
                    'tipo_documento' => 1,
                    'numero_documento' => $documento,
                    'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
                    'direccion' => $cliente->direccion ?: $config->direccion,
                    'numero_casa' => '0',
                    'departamento' => (int) $config->departamento,
                    'departamento_descripcion' => $config->departamento_descripcion,
                    'distrito' => (int) $config->distrito,
                    'distrito_descripcion' => $config->distrito_descripcion,
                    'ciudad' => (int) $config->ciudad,
                    'ciudad_descripcion' => $config->ciudad_descripcion,
                ],
                'lugar_provision' => [
                    'direccion' => $config->direccion,
                    'departamento' => (int) $config->departamento,
                    'departamento_descripcion' => $config->departamento_descripcion,
                    'distrito' => (int) $config->distrito,
                    'distrito_descripcion' => $config->distrito_descripcion,
                    'ciudad' => (int) $config->ciudad,
                    'ciudad_descripcion' => $config->ciudad_descripcion,
                ],
            ];
        }

        if ($tipoDocumento === 'nota_remision') {
            $nombreTransportista = trim((string) ($input['transportista_nombre'] ?? ''));
            if ($nombreTransportista === '') {
                $nombreTransportista = $config?->razon_social ?? 'Transporte propio';
            }

            return [
                'remision' => [
                    'motivo_traslado' => (int) ($input['motivo_traslado'] ?? 1),
                    'kilometros' => (int) ($input['kilometros'] ?? 10),
                    'responsable_emision' => 1,
                ],
                'transporte' => [
                    'modalidad' => 1,
                    'responsable_flete' => 1,
                    'transportista' => [
                        'naturaleza' => 1,
                        'nombre' => $nombreTransportista,
                        'numero_documento_chofer' => preg_replace(
                            '/\D/',
                            '',
                            (string) ($input['chofer_documento'] ?? $cliente->cedula)
                        ),
                        'nombre_chofer' => trim((string) ($input['chofer_nombre'] ?? $cliente->nombre.' '.$cliente->apellido)),
                        'domicilio_fiscal' => $cliente->direccion ?: ($config?->direccion ?? 'Asunción'),
                    ],
                ],
            ];
        }

        return null;
    }
}
