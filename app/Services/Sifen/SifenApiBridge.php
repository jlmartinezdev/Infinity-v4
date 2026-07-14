<?php

namespace App\Services\Sifen;

use App\Models\Factura;
use App\Models\SifenConfiguracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SifenApiBridge
{
    public function __construct(
        private SifenApiClient $client,
    ) {}

    public function activo(): bool
    {
        return $this->client->isConfigured();
    }

    public function apiDisponible(): bool
    {
        if (! $this->activo()) {
            return false;
        }

        try {
            $status = $this->client->status();

            return ($status['config_activa'] ?? false) && ($status['certificado_configurado'] ?? false);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function prepararDocumento(Factura $factura, bool $validarXsd = true, ?Carbon $fechaEmisionDe = null): array
    {
        $factura->loadMissing(['cliente', 'detalles.impuesto']);
        $documentoId = $this->resolverDocumentoRemoto($factura);
        $remoto = $this->client->obtenerDocumento($documentoId);

        if (($remoto['estado'] ?? '') === 'emitida') {
            return $this->restaurarDesdeRemoto($factura, $remoto, $documentoId, false);
        }

        $respuesta = $this->client->preparar($documentoId);
        $data = $this->normalizarRespuestaDocumento($respuesta);

        $this->sincronizarFacturaDesdeApi($factura, $data);
        $this->sincronizarContadorDesdeApi();

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $respuesta['cdc'] ?? ($data['sifen']['cdc'] ?? $factura->set_cdc),
            'xml' => '',
            'xml_path' => $factura->xml_path,
            'validacion' => is_array($respuesta['validacion'] ?? null)
                ? $respuesta['validacion']
                : ['valido' => true, 'errores' => [], 'aviso' => 'Validación ejecutada en sifen-api'],
            'totales' => is_array($respuesta['totales'] ?? null) ? $respuesta['totales'] : [
                'subtotal' => (float) $factura->subtotal,
                'total_impuestos' => (float) $factura->total_impuestos,
                'total' => (float) $factura->total,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emitirDocumento(Factura $factura, bool $enviarSifen = true): array
    {
        if ($factura->estado !== 'borrador') {
            throw new RuntimeException('Solo se pueden emitir facturas en estado borrador.');
        }

        $factura->loadMissing(['cliente', 'detalles.impuesto']);
        $documentoId = $this->resolverDocumentoRemoto($factura);
        $remoto = $this->client->obtenerDocumento($documentoId);

        if (($remoto['estado'] ?? '') === 'emitida') {
            return $this->restaurarDesdeRemoto($factura, $remoto, $documentoId, $enviarSifen);
        }

        // Lote async pendiente: consultar resultado en lugar de reenviar.
        $estadoEnvioRemoto = $remoto['sifen']['estado_envio'] ?? null;
        $nroLoteRemoto = $remoto['sifen']['nro_lote'] ?? null;
        if (
            $enviarSifen
            && filled($nroLoteRemoto)
            && in_array($estadoEnvioRemoto, ['en_proceso', 'pendiente'], true)
        ) {
            $this->sincronizarFacturaDesdeApi($factura, $remoto);

            return $this->consultarResultadoLote($factura);
        }

        try {
            $enviarCorreo = $this->resolverEnviarCorreoApi($factura);
            $receptorCorreo = $this->construirReceptorCorreoPayload($factura);
            $respuesta = $this->client->emitir($documentoId, $enviarSifen, $enviarCorreo, $receptorCorreo);
        } catch (RuntimeException $e) {
            if (
                str_contains($e->getMessage(), 'borrador')
                && $factura->sifen_api_documento_id
            ) {
                try {
                    $remoto = $this->client->obtenerDocumento((int) $factura->sifen_api_documento_id);
                    if (($remoto['estado'] ?? '') === 'emitida') {
                        return $this->restaurarDesdeRemoto($factura, $remoto, (int) $factura->sifen_api_documento_id, $enviarSifen);
                    }
                } catch (\Throwable) {
                    // seguir con el error original
                }
            }

            $this->sincronizarTrasError($factura);

            // Mensaje de lote pendiente: mantener sync local y relanzar como pendiente.
            if ($this->esErrorLotePendiente($e->getMessage())) {
                $factura->refresh();
                throw new RuntimeException(
                    'El lote fue enviado a SIFEN y sigue en procesamiento'
                    .($factura->set_nro_lote ? ' (nº '.$factura->set_nro_lote.')' : '')
                    .'. Use «Consultar lote» en unos minutos.'
                );
            }

            throw $e;
        }

        $data = $this->normalizarRespuestaDocumento($respuesta);
        $this->sincronizarFacturaDesdeApi($factura, $data);
        $factura->refresh();

        $sifen = is_array($respuesta['sifen'] ?? null)
            ? $respuesta['sifen']
            : ($data['sifen'] ?? null);

        // Si quedó en proceso sin autorización, no descargar como emitida.
        if ($enviarSifen && $factura->lotePendienteSifen()) {
            throw new RuntimeException(
                'El lote fue enviado a SIFEN y sigue en procesamiento'
                .($factura->set_nro_lote ? ' (nº '.$factura->set_nro_lote.')' : '')
                .'. Use «Consultar lote» en unos minutos.'
            );
        }

        $this->descargarArchivosLocales($factura, $documentoId);
        $this->sincronizarContadorDesdeApi();

        if ($enviarSifen && is_array($sifen) && ! ($sifen['aprobado'] ?? false)) {
            $codigo = $sifen['codigo'] ?? '?';
            $mensaje = $sifen['mensaje'] ?? ($respuesta['message'] ?? 'Sin mensaje');
            $texto = str_starts_with($mensaje, '['.$codigo.']')
                ? 'SIFEN rechazó el DE: '.$mensaje
                : 'SIFEN rechazó el DE: ['.$codigo.'] '.$mensaje;

            throw new RuntimeException($texto);
        }

        $factura->refresh();

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $respuesta['cdc'] ?? $factura->set_cdc,
            'xml' => $factura->xml_path ? (string) File::get(storage_path($factura->xml_path)) : '',
            'xml_path' => $factura->xml_path,
            'pdf_path' => $factura->pdf_path,
            'qr_url' => $respuesta['qr_url'] ?? $factura->set_qr_url,
            'validacion' => is_array($respuesta['validacion'] ?? null)
                ? $respuesta['validacion']
                : ['valido' => true, 'errores' => []],
            'totales' => [
                'subtotal' => (float) $factura->subtotal,
                'total_impuestos' => (float) $factura->total_impuestos,
                'total' => (float) $factura->total,
            ],
            'sifen' => $sifen,
            'correo' => is_array($respuesta['correo'] ?? null) ? $respuesta['correo'] : null,
            'lote_pendiente' => false,
        ];
    }

    /**
     * Consulta el resultado de un lote async pendiente en sifen-api.
     *
     * @return array<string, mixed>
     */
    public function consultarResultadoLote(Factura $factura): array
    {
        if (! $factura->sifen_api_documento_id) {
            throw new RuntimeException('La factura no tiene documento remoto en sifen-api.');
        }

        $enviarCorreo = $this->resolverEnviarCorreoApi($factura);

        try {
            $respuesta = $this->client->consultarLote((int) $factura->sifen_api_documento_id, $enviarCorreo);
        } catch (RuntimeException $e) {
            $this->sincronizarTrasError($factura);

            if ($this->esErrorLotePendiente($e->getMessage())) {
                throw new RuntimeException(
                    'El lote sigue en procesamiento'
                    .($factura->fresh()->set_nro_lote ? ' (nº '.$factura->fresh()->set_nro_lote.')' : '')
                    .'. Reintente más tarde.'
                );
            }

            throw $e;
        }

        $data = $this->normalizarRespuestaDocumento($respuesta);
        $this->sincronizarFacturaDesdeApi($factura, $data);
        $factura->refresh();

        if ($factura->lotePendienteSifen()) {
            throw new RuntimeException(
                'El lote sigue en procesamiento'
                .($factura->set_nro_lote ? ' (nº '.$factura->set_nro_lote.')' : '')
                .'. Reintente más tarde.'
            );
        }

        if ($factura->estado !== 'emitida') {
            $sifen = is_array($respuesta['sifen'] ?? null) ? $respuesta['sifen'] : ($data['sifen'] ?? []);
            $codigo = is_array($sifen) ? ($sifen['codigo'] ?? '?') : '?';
            $mensaje = is_array($sifen) ? ($sifen['mensaje'] ?? ($respuesta['message'] ?? 'Sin autorización')) : 'Sin autorización';
            throw new RuntimeException('SIFEN no autorizó el DE: ['.$codigo.'] '.$mensaje);
        }

        $this->descargarArchivosLocales($factura, (int) $factura->sifen_api_documento_id);
        $this->sincronizarContadorDesdeApi();

        $sifen = is_array($respuesta['sifen'] ?? null)
            ? $respuesta['sifen']
            : ($data['sifen'] ?? null);

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $respuesta['cdc'] ?? $factura->set_cdc,
            'xml' => $factura->xml_path ? (string) File::get(storage_path($factura->xml_path)) : '',
            'xml_path' => $factura->xml_path,
            'pdf_path' => $factura->pdf_path,
            'qr_url' => $respuesta['qr_url'] ?? $factura->set_qr_url,
            'validacion' => ['valido' => true, 'errores' => []],
            'totales' => [
                'subtotal' => (float) $factura->subtotal,
                'total_impuestos' => (float) $factura->total_impuestos,
                'total' => (float) $factura->total,
            ],
            'sifen' => $sifen,
            'correo' => is_array($respuesta['correo'] ?? null) ? $respuesta['correo'] : null,
            'lote_pendiente' => false,
        ];
    }

    private function sincronizarTrasError(Factura $factura): void
    {
        $factura->refresh();
        if (! $factura->sifen_api_documento_id) {
            try {
                $remoto = $this->client->buscarPorReferencia($this->referenciaExterna($factura));
                if ($remoto) {
                    $this->sincronizarFacturaDesdeApi($factura, $remoto);
                }
            } catch (\Throwable) {
            }

            return;
        }

        try {
            $remoto = $this->client->obtenerDocumento((int) $factura->sifen_api_documento_id);
            $this->sincronizarFacturaDesdeApi($factura, $remoto);
        } catch (\Throwable) {
            try {
                $remoto = $this->client->buscarPorReferencia($this->referenciaExterna($factura));
                if ($remoto) {
                    $this->sincronizarFacturaDesdeApi($factura, $remoto);
                }
            } catch (\Throwable) {
            }
        }
    }

    private function esErrorLotePendiente(string $mensaje): bool
    {
        $mensaje = mb_strtolower($mensaje);

        return str_contains($mensaje, 'en procesamiento')
            || str_contains($mensaje, 'consultar-lote')
            || str_contains($mensaje, 'consultar lote');
    }

    private function resolverEnviarCorreoApi(Factura $factura): ?bool
    {
        if (! config('sifen.enviar_correo_emision', true)) {
            return false;
        }

        return $this->emailClienteValido($factura) ? true : false;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function construirReceptorCorreoPayload(Factura $factura): ?array
    {
        if ($factura->esOcasional()) {
            $email = trim((string) ($factura->receptor_email ?? ''));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return [
                'nombre' => $factura->receptorNombreCompleto(),
                'documento' => preg_replace('/\D/', '', (string) $factura->receptor_documento),
                'direccion' => $factura->receptor_direccion,
                'email' => $email,
                'telefono' => $factura->receptor_telefono,
            ];
        }

        $factura->loadMissing('cliente');
        $cliente = $factura->cliente;
        if (! $cliente) {
            return null;
        }

        $email = trim((string) ($cliente->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
            'documento' => preg_replace('/\D/', '', (string) $cliente->cedula),
            'direccion' => $cliente->direccion,
            'email' => $email,
            'telefono' => $cliente->telefono,
        ];
    }

    private function emailClienteValido(Factura $factura): bool
    {
        return $this->construirReceptorCorreoPayload($factura) !== null;
    }

    private function resolverDocumentoRemoto(Factura $factura): int
    {
        if ($factura->sifen_api_documento_id) {
            return (int) $factura->sifen_api_documento_id;
        }

        $existente = $this->client->buscarPorReferencia($this->referenciaExterna($factura));
        if ($existente && isset($existente['id'])) {
            $factura->update(['sifen_api_documento_id' => (int) $existente['id']]);

            return (int) $existente['id'];
        }

        $respuesta = $this->client->crearDocumento($this->construirPayloadDocumento($factura));
        $data = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];

        if (! isset($data['id'])) {
            throw new RuntimeException('sifen-api no devolvió ID de documento.');
        }

        $factura->update(['sifen_api_documento_id' => (int) $data['id']]);

        return (int) $data['id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function construirPayloadDocumento(Factura $factura): array
    {
        $items = [];
        foreach ($factura->detalles as $index => $detalle) {
            $items[] = [
                'descripcion' => $detalle->descripcion,
                'cantidad' => (float) $detalle->cantidad,
                'precio_unitario' => (float) $detalle->precio_unitario,
                'codigo_item' => (string) ($detalle->servicio_id ?? ($index + 1)),
                'impuesto_codigo' => $detalle->impuesto?->codigo ?? 'IVA10',
            ];
        }

        return [
            'referencia_externa' => $this->referenciaExterna($factura),
            'tipo_documento' => $factura->tipo_documento,
            'fecha_emision' => $factura->fecha_emision?->toDateString() ?? now()->toDateString(),
            'moneda' => $factura->moneda ?? 'PYG',
            'observaciones' => $factura->observaciones,
            'receptor' => $this->construirReceptorPayload($factura),
            'items' => $items,
            'datos_complementarios' => $factura->datos_complementarios,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function construirReceptorPayload(Factura $factura): array
    {
        if ($factura->tipo_documento === 'autofactura') {
            $config = SifenConfiguracion::activa();
            if (! $config) {
                throw new RuntimeException('Configure el emisor SIFEN para autofactura.');
            }

            return [
                'nombre' => $config->razon_social,
                'documento' => $config->ruc.'-'.$config->dv_ruc,
                'direccion' => $config->direccion,
                'email' => $config->email,
                'telefono' => $config->telefono,
            ];
        }

        if ($factura->esOcasional()) {
            if (blank($factura->receptor_documento)) {
                throw new RuntimeException('La factura ocasional requiere documento del receptor.');
            }

            return [
                'nombre' => $factura->receptorNombreCompleto(),
                'documento' => preg_replace('/\D/', '', (string) $factura->receptor_documento),
                'direccion' => $factura->receptor_direccion,
                'email' => $factura->receptor_email,
                'telefono' => $factura->receptor_telefono,
            ];
        }

        $cliente = $factura->cliente;
        if (! $cliente || blank($cliente->cedula)) {
            throw new RuntimeException('La factura requiere cliente con cédula o RUC.');
        }

        return [
            'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
            'documento' => preg_replace('/\D/', '', (string) $cliente->cedula),
            'direccion' => $cliente->direccion,
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sincronizarFacturaDesdeApi(Factura $factura, array $data): void
    {
        $sifen = is_array($data['sifen'] ?? null) ? $data['sifen'] : [];
        $numeracion = $this->parseNumeroCompleto($data['numero_completo'] ?? null);

        $numero = $numeracion['numero'] ?? (isset($data['numero']) ? (int) $data['numero'] : null);
        $establecimiento = $numeracion['establecimiento']
            ?? (isset($data['establecimiento']) ? (int) $data['establecimiento'] : null);
        $puntoEmision = $numeracion['punto_emision']
            ?? (isset($data['punto_emision']) ? (int) $data['punto_emision'] : null);

        $actualizar = array_filter([
            'estado' => $data['estado'] ?? null,
            'numero' => $numero,
            'establecimiento' => $establecimiento,
            'punto_emision' => $puntoEmision,
            'set_cdc' => $sifen['cdc'] ?? ($data['cdc'] ?? null),
            'set_qr_url' => $sifen['qr_url'] ?? null,
            'set_estado_envio' => $sifen['estado_envio'] ?? null,
            'set_nro_lote' => $sifen['nro_lote'] ?? null,
            'set_fecha_autorizacion' => ! empty($sifen['fecha_autorizacion'])
                ? Carbon::parse($sifen['fecha_autorizacion'])
                : null,
            'set_fecha_emision_de' => ! empty($sifen['fecha_emision_de'])
                ? Carbon::parse($sifen['fecha_emision_de'])
                : null,
            'set_xml_respuesta' => $data['set_xml_respuesta'] ?? null,
            'sifen_api_documento_id' => $data['id'] ?? $factura->sifen_api_documento_id,
        ], fn ($v) => $v !== null);

        if ($actualizar !== []) {
            $factura->update($actualizar);
        }

        if ($numero !== null) {
            $config = SifenConfiguracion::activa();
            $config?->registrarNumeroEmitido($numero);
        }
    }

    private function sincronizarContadorDesdeApi(): void
    {
        $ultimoRemoto = $this->client->obtenerUltimoNumeroRemoto();
        if ($ultimoRemoto === null) {
            return;
        }

        $config = SifenConfiguracion::activa();
        $config?->registrarNumeroEmitido($ultimoRemoto);
    }

    /**
     * @param  array<string, mixed>  $respuesta
     * @return array<string, mixed>
     */
    private function normalizarRespuestaDocumento(array $respuesta): array
    {
        $data = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];

        if (! empty($respuesta['cdc'])) {
            $sifen = is_array($data['sifen'] ?? null) ? $data['sifen'] : [];
            $data['sifen'] = array_merge($sifen, ['cdc' => $respuesta['cdc']]);
            $data['cdc'] = $respuesta['cdc'];
        }

        return $data;
    }

    /**
     * El documento ya fue emitido en sifen-api; sincroniza Infinity sin volver a llamar a emitir.
     *
     * @param  array<string, mixed>  $remoto
     * @return array<string, mixed>
     */
    private function restaurarDesdeRemoto(
        Factura $factura,
        array $remoto,
        int $documentoId,
        bool $enviarSifen,
    ): array {
        $this->sincronizarFacturaDesdeApi($factura, $remoto);
        $factura->refresh();
        $this->descargarArchivosLocales($factura, $documentoId);
        $this->sincronizarContadorDesdeApi();

        $sifen = $this->construirSifenDesdeRemoto($remoto);
        $estadoEnvio = $factura->set_estado_envio;

        if ($enviarSifen && $estadoEnvio === 'rechazado') {
            $codigo = $sifen['codigo'] ?? '?';
            $mensaje = $sifen['mensaje'] ?? 'Documento rechazado por SIFEN';
            throw new RuntimeException(
                str_starts_with($mensaje, '['.$codigo.']')
                    ? 'SIFEN rechazó el DE: '.$mensaje
                    : 'SIFEN rechazó el DE: ['.$codigo.'] '.$mensaje
            );
        }

        if ($factura->estado !== 'emitida') {
            $factura->update(['estado' => 'emitida']);
            $factura->refresh();
        }

        return [
            'factura' => $factura->fresh(['cliente', 'detalles.impuesto']),
            'cdc' => $factura->set_cdc,
            'xml' => $factura->xml_path && File::isFile(storage_path($factura->xml_path))
                ? (string) File::get(storage_path($factura->xml_path))
                : '',
            'xml_path' => $factura->xml_path,
            'pdf_path' => $factura->pdf_path,
            'qr_url' => $factura->set_qr_url,
            'validacion' => [
                'valido' => true,
                'errores' => [],
                'aviso' => $this->mensajeSincronizacionRemota($remoto),
            ],
            'totales' => [
                'subtotal' => (float) $factura->subtotal,
                'total_impuestos' => (float) $factura->total_impuestos,
                'total' => (float) $factura->total,
            ],
            'sifen' => $sifen,
            'sincronizado_desde_api' => true,
            'siguiente_numero_remoto' => $this->client->obtenerSiguienteNumeroRemoto(),
        ];
    }

    /**
     * @param  array<string, mixed>  $remoto
     */
    private function mensajeSincronizacionRemota(array $remoto): string
    {
        $numero = $remoto['numero'] ?? '?';
        $siguiente = $this->client->obtenerSiguienteNumeroRemoto();

        $texto = 'Este documento ya estaba emitido en sifen-api (nº '.$numero.'). '
            .'Se sincronizaron CDC, XML y estado local; no se envió un DE nuevo a SIFEN.';

        if ($siguiente !== null) {
            $texto .= ' Para probar el siguiente número ('.$siguiente.'), cree un nuevo borrador de prueba.';
        }

        return $texto;
    }

    /**
     * @param  array<string, mixed>  $remoto
     * @return array<string, mixed>|null
     */
    private function construirSifenDesdeRemoto(array $remoto): ?array
    {
        $sifen = is_array($remoto['sifen'] ?? null) ? $remoto['sifen'] : [];
        $estadoEnvio = $sifen['estado_envio'] ?? null;

        if (filled($sifen['codigo'] ?? null) || filled($sifen['mensaje'] ?? null) || filled($sifen['protocolo'] ?? null)) {
            return [
                'codigo' => $sifen['codigo'] ?? null,
                'mensaje' => $sifen['mensaje'] ?? null,
                'estado' => $sifen['estado'] ?? null,
                'cdc' => $sifen['cdc'] ?? null,
                'protocolo' => $sifen['protocolo'] ?? null,
                'aprobado' => (bool) ($sifen['aprobado'] ?? ($estadoEnvio === 'autorizado')),
                'sincronizado_desde_api' => true,
                'raw' => '',
            ];
        }

        if ($estadoEnvio === 'pendiente' && ($remoto['estado'] ?? '') === 'emitida') {
            return [
                'codigo' => null,
                'mensaje' => 'Documento emitido en sifen-api sin respuesta de SIFEN. Revise el panel de sifen-api.',
                'estado' => null,
                'cdc' => $sifen['cdc'] ?? null,
                'protocolo' => null,
                'aprobado' => false,
                'sincronizado_desde_api' => true,
                'raw' => '',
            ];
        }

        if ($estadoEnvio === 'autorizado') {
            return [
                'codigo' => null,
                'mensaje' => 'Documento ya autorizado en sifen-api (sincronizado; no es un envío nuevo a SIFEN).',
                'estado' => 'Aprobado',
                'cdc' => $sifen['cdc'] ?? null,
                'protocolo' => null,
                'aprobado' => true,
                'sincronizado_desde_api' => true,
                'raw' => '',
            ];
        }

        return null;
    }

    private function descargarArchivosLocales(Factura $factura, int $documentoId): void
    {
        $cdc = $factura->set_cdc;
        if (blank($cdc)) {
            return;
        }

        File::ensureDirectoryExists(config('sifen.paths.xml'));
        File::ensureDirectoryExists(config('sifen.paths.pdf'));

        try {
            $xml = $this->client->descargarXml($documentoId);
            $nombreXml = sprintf('DE_firmado_%s_%s.xml', $cdc, now()->format('YmdHis'));
            $rutaRelXml = 'sifen/xml/'.$nombreXml;
            File::put(storage_path($rutaRelXml), $xml);
            $factura->update(['xml_path' => $rutaRelXml]);

            $fechaDe = SifenXmlManipulator::extraerFechaEmisionDe($xml);
            if ($fechaDe) {
                $factura->update(['set_fecha_emision_de' => $fechaDe]);
            }
        } catch (\Throwable) {
            // XML puede no existir si solo se preparó
        }

        if ($factura->estado !== 'emitida') {
            return;
        }

        try {
            $pdf = $this->client->descargarKude($documentoId);
            $nombrePdf = 'KuDE_'.$cdc.'_'.now()->format('YmdHis').'.pdf';
            $rutaRelPdf = 'sifen/pdf/'.$nombrePdf;
            File::put(storage_path($rutaRelPdf), $pdf);
            $factura->update(['pdf_path' => $rutaRelPdf]);
        } catch (\Throwable) {
            // KuDE opcional si falla descarga
        }
    }

  /**
     * @return array{establecimiento: ?int, punto_emision: ?int, numero: ?int}
     */
    private function parseNumeroCompleto(?string $numeroCompleto): array
    {
        if (blank($numeroCompleto) || ! preg_match('/^(\d{3})-(\d{3})-(\d+)$/', $numeroCompleto, $m)) {
            return ['establecimiento' => null, 'punto_emision' => null, 'numero' => null];
        }

        return [
            'establecimiento' => (int) $m[1],
            'punto_emision' => (int) $m[2],
            'numero' => (int) $m[3],
        ];
    }

    private function referenciaExterna(Factura $factura): string
    {
        return 'infinity-fe-'.$factura->id;
    }
}
