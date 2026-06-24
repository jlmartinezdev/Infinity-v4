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
        $data = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];

        $this->sincronizarFacturaDesdeApi($factura, $data);

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

        try {
            $respuesta = $this->client->emitir($documentoId, $enviarSifen);
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

            $factura->refresh();
            if ($factura->sifen_api_documento_id) {
                try {
                    $remoto = $this->client->buscarPorReferencia($this->referenciaExterna($factura));
                    if ($remoto) {
                        $this->sincronizarFacturaDesdeApi($factura, $remoto);
                    }
                } catch (\Throwable) {
                    // ignorar error de sincronización post-fallo
                }
            }

            throw $e;
        }

        $data = is_array($respuesta['data'] ?? null) ? $respuesta['data'] : [];
        $this->sincronizarFacturaDesdeApi($factura, $data);
        $factura->refresh();

        $this->descargarArchivosLocales($factura, $documentoId);

        $sifen = is_array($respuesta['sifen'] ?? null)
            ? $respuesta['sifen']
            : ($data['sifen'] ?? null);

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
        ];
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
        $cliente = $factura->cliente;
        if (! $cliente || blank($cliente->cedula)) {
            throw new RuntimeException('La factura requiere cliente con cédula o RUC.');
        }

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
            'receptor' => [
                'nombre' => trim($cliente->nombre.' '.$cliente->apellido),
                'documento' => preg_replace('/\D/', '', (string) $cliente->cedula),
                'direccion' => $cliente->direccion,
                'email' => $cliente->email,
                'telefono' => $cliente->telefono,
            ],
            'items' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function sincronizarFacturaDesdeApi(Factura $factura, array $data): void
    {
        $sifen = is_array($data['sifen'] ?? null) ? $data['sifen'] : [];
        $numeracion = $this->parseNumeroCompleto($data['numero_completo'] ?? null);

        $actualizar = array_filter([
            'estado' => $data['estado'] ?? null,
            'numero' => $numeracion['numero'],
            'establecimiento' => $numeracion['establecimiento'],
            'punto_emision' => $numeracion['punto_emision'],
            'set_cdc' => $sifen['cdc'] ?? null,
            'set_qr_url' => $sifen['qr_url'] ?? null,
            'set_estado_envio' => $sifen['estado_envio'] ?? null,
            'set_fecha_autorizacion' => ! empty($sifen['fecha_autorizacion'])
                ? Carbon::parse($sifen['fecha_autorizacion'])
                : null,
            'sifen_api_documento_id' => $data['id'] ?? $factura->sifen_api_documento_id,
        ], fn ($v) => $v !== null);

        if ($actualizar !== []) {
            $factura->update($actualizar);
        }

        if ($numeracion['numero'] !== null) {
            $config = SifenConfiguracion::activa();
            $config?->registrarNumeroEmitido((int) $numeracion['numero']);
        }
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
            'validacion' => ['valido' => true, 'errores' => [], 'aviso' => 'Documento ya emitido en sifen-api; datos sincronizados.'],
            'totales' => [
                'subtotal' => (float) $factura->subtotal,
                'total_impuestos' => (float) $factura->total_impuestos,
                'total' => (float) $factura->total,
            ],
            'sifen' => $sifen,
        ];
    }

    /**
     * @param  array<string, mixed>  $remoto
     * @return array<string, mixed>|null
     */
    private function construirSifenDesdeRemoto(array $remoto): ?array
    {
        $sifen = is_array($remoto['sifen'] ?? null) ? $remoto['sifen'] : [];
        $estadoEnvio = $sifen['estado_envio'] ?? null;

        if ($estadoEnvio === null && ($remoto['estado'] ?? '') === 'emitida') {
            $estadoEnvio = 'autorizado';
        }

        return [
            'codigo' => $estadoEnvio === 'autorizado' ? '0260' : null,
            'mensaje' => $estadoEnvio === 'autorizado' ? '[0260] Documento ya autorizado en sifen-api' : null,
            'estado' => $estadoEnvio === 'autorizado' ? 'Aprobado' : null,
            'cdc' => $sifen['cdc'] ?? null,
            'aprobado' => $estadoEnvio === 'autorizado',
            'raw' => '',
        ];
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
